<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        // Try to authenticate using email or name
        $credentials = [];
        if (filter_var($request->login, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $request->login;
        } else {
            $credentials['name'] = $request->login;
        }
        $credentials['password'] = $request->password;

        // Multi-tenant: scope login to current lab (platform admin has lab_id=null)
        $labId = app()->bound('current_lab_id') ? app('current_lab_id') : null;
        if ($labId) {
            $credentials['lab_id'] = $labId;
        }

        if (!Auth::attempt($credentials)) {
            // Fallback: try without lab_id (for localhost: any lab user; or platform admin)
            if ($labId && filter_var($request->login, FILTER_VALIDATE_EMAIL)) {
                $fallbackCredentials = ['email' => $request->login, 'password' => $request->password];
                if (Auth::attempt($fallbackCredentials)) {
                    $user = Auth::user();
                    // Allow: user belongs to current lab, OR we're on localhost (any lab works)
                    $isLocalhost = in_array(explode(':', $request->getHost())[0] ?? '', ['localhost', '127.0.0.1']);
                    if ($user->lab_id == $labId || $isLocalhost) {
                        goto login_success;
                    }
                    Auth::logout();
                }
            }
            // Platform admin (lab_id=null) can login from any context - try without lab_id
            if ($labId) {
                unset($credentials['lab_id']);
                if (Auth::attempt($credentials)) {
                    $user = Auth::user();
                    if ($user->lab_id !== null) {
                        Auth::logout();
                        throw ValidationException::withMessages([
                            'login' => ['The provided credentials are incorrect.'],
                        ]);
                    }
                } else {
                    throw ValidationException::withMessages([
                        'login' => ['The provided credentials are incorrect.'],
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'login' => ['The provided credentials are incorrect.'],
                ]);
            }
        }

        login_success:
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated.'],
            ]);
        }

        // Generate refresh token
        $deviceInfo = [
            'device_id' => $request->header('X-Device-ID', Str::uuid()),
            'device_name' => $request->header('X-Device-Name', 'Unknown Device'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $refreshToken = RefreshToken::generate($user, $deviceInfo);

        // Create access token (using Sanctum)
        $accessToken = $user->createToken('access-token', ['*'], now()->addHours(1))->plainTextToken;

        // Include lab in response for frontend context
        $lab = $user->lab_id ? Lab::find($user->lab_id) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->mapRole($user->role),
                'phone' => $user->phone,
                'lab_id' => $user->lab_id,
            ],
            'lab' => $lab ? [
                'id' => $lab->id,
                'name' => $lab->name,
                'slug' => $lab->slug,
                'subdomain' => $lab->subdomain,
            ] : null,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => 3600, // 1 hour
            'message' => 'Login successful',
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke current access token
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        // Revoke refresh token if provided
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            RefreshToken::where('token', $refreshToken)
                       ->where('user_id', $request->user()->id ?? null)
                       ->update(['is_revoked' => true]);
        }

        // For Sanctum API authentication, delete the current access token
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        // Only handle session for web routes, not API routes
        if (!$request->is('api/*')) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = RefreshToken::where('token', $request->refresh_token)
                                   ->where('is_revoked', false)
                                   ->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json([
                'message' => 'Invalid or expired refresh token',
                'error' => 'invalid_refresh_token'
            ], 401);
        }

        $user = $refreshToken->user;
        
        if (!$user->is_active) {
            $refreshToken->revoke();
            return response()->json([
                'message' => 'Account has been deactivated',
                'error' => 'account_deactivated'
            ], 401);
        }

        // Mark refresh token as used
        $refreshToken->markAsUsed();

        // Create new access token
        $accessToken = $user->createToken('access-token', ['*'], now()->addHours(1))->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600, // 1 hour
            'message' => 'Token refreshed successfully',
        ]);
    }

    /**
     * Get CSRF token for SPA
     */
    public function csrfToken(Request $request)
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    public function user(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $lab = $user->lab_id ? Lab::find($user->lab_id) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->mapRole($user->role),
                'phone' => $user->phone,
                'lab_id' => $user->lab_id,
            ],
            'lab' => $lab ? [
                'id' => $lab->id,
                'name' => $lab->name,
                'slug' => $lab->slug,
                'subdomain' => $lab->subdomain,
            ] : null,
        ]);
    }

    /**
     * Map old role values to new role values for frontend compatibility
     */
    private function mapRole($role)
    {
        $roleMapping = [
            'admin' => 'admin',
            'staff' => 'staff',
            'doctor' => 'doctor',
            'lab_tech' => 'staff',
            'accountant' => 'staff',
            'patient' => 'patient',
        ];

        return $roleMapping[$role] ?? 'staff';
    }
} 