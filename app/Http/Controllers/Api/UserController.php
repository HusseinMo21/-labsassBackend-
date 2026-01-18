<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('exclude_role')) {
            $query->where('role', '!=', $request->exclude_role);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $perPage = $request->has('per_page') ? (int)$request->per_page : 15;
        $users = $query->latest()->paginate($perPage);

        // Transform the data to show real patient names for patient users
        $users->getCollection()->transform(function ($user) {
            if ($user->role === 'patient') {
                // Try to get the real patient name from patient_credentials
                $username = str_replace('@patients.local', '', $user->email);
                $credential = \DB::table('patient_credentials')
                    ->where('username', $username)
                    ->first();
                
                if ($credential) {
                    $patient = \App\Models\Patient::find($credential->patient_id);
                    if ($patient && !empty($patient->name)) {
                        $user->display_name = $patient->name;
                        $user->real_name = $patient->name;
                        $user->patient_id = $patient->id;
                    } else {
                        $user->display_name = $user->name;
                        $user->real_name = null;
                    }
                } else {
                    $user->display_name = $user->name;
                    $user->real_name = null;
                }
            } else {
                $user->display_name = $user->name;
                $user->real_name = null;
            }
            
            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,staff,doctor,patient',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,staff,doctor,patient',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prevent role changes for patients - they must always remain patients
        if ($user->role === 'patient' && $request->role !== 'patient') {
            return response()->json([
                'message' => 'Cannot change patient role. Patients must always remain as patients.',
            ], 422);
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $user->role === 'patient' ? 'patient' : $request->role, // Force patient role if user is patient
            'is_active' => $request->is_active ?? $user->is_active,
        ];

        // Update password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function destroy(User $user)
    {
        // Prevent deletion of the current user
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 422);
        }

        // Check if user has any related data
        $hasRelatedData = false;
        $relatedDataInfo = [];
        
        // Check visit tests
        $visitTestsCount = $user->visitTests()->count();
        if ($visitTestsCount > 0) {
            $hasRelatedData = true;
            $relatedDataInfo[] = "Visit Tests: {$visitTestsCount}";
        }
        
        // Check inventory items
        $inventoryCount = $user->inventoryItems()->count();
        if ($inventoryCount > 0) {
            $hasRelatedData = true;
            $relatedDataInfo[] = "Inventory Items: {$inventoryCount}";
        }
        
        // Check refresh tokens
        $refreshTokensCount = $user->refreshTokens()->count();
        if ($refreshTokensCount > 0) {
            $hasRelatedData = true;
            $relatedDataInfo[] = "Refresh Tokens: {$refreshTokensCount}";
        }
        
        if ($hasRelatedData) {
            return response()->json([
                'message' => 'Cannot delete user with existing data. Consider deactivating instead.',
                'related_data' => $relatedDataInfo,
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function toggleStatus(User $user)
    {
        // Prevent deactivation of the current user
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot deactivate your own account',
            ], 422);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "User {$status} successfully",
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function getRoles()
    {
        $roles = [
            ['value' => 'admin', 'label' => 'Administrator'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'doctor', 'label' => 'Doctor'],
            ['value' => 'patient', 'label' => 'Patient'],
        ];

        return response()->json($roles);
    }

    public function getStats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'by_role' => [
                'admin' => User::where('role', 'admin')->count(),
                'lab_tech' => User::where('role', 'lab_tech')->count(),
                'accountant' => User::where('role', 'accountant')->count(),
            ],
        ];

        return response()->json($stats);
    }
} 