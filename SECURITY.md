# Security Implementation Guide

## Overview
This Laravel application implements comprehensive security measures including CSRF protection, secure authentication with refresh tokens, and role-based access control.

## Security Features Implemented

### 1. Laravel Sanctum SPA Authentication
- **Stateful Authentication**: Uses cookies for SPA authentication
- **CSRF Protection**: All stateful requests require valid CSRF tokens
- **Token Expiration**: Access tokens expire after 1 hour
- **Secure Cookies**: Encrypted cookies with proper domain configuration

### 2. Refresh Token System
- **Long-lived Tokens**: Refresh tokens valid for 30 days
- **Device Tracking**: Tracks device ID, name, IP address, and user agent
- **Automatic Cleanup**: Expired tokens are automatically cleaned up
- **Revocation**: Tokens can be revoked individually or for all user sessions

### 3. CSRF Protection
- **Custom Middleware**: `ApiCsrfMiddleware` handles CSRF validation
- **Stateful Domain Detection**: Only applies CSRF to requests from configured domains
- **Token Validation**: Validates CSRF tokens from headers or form data
- **Exemptions**: GET, HEAD, OPTIONS requests are exempt from CSRF

### 4. Role-Based Access Control
- **User Roles**: Admin, Staff, Doctor, Patient
- **Route Protection**: Middleware-based role checking
- **Permission System**: Granular permissions for different user types

## API Endpoints

### Authentication Endpoints
```
POST /api/auth/login          - User login (returns access + refresh tokens)
POST /api/auth/logout         - User logout (revokes tokens)
POST /api/auth/refresh        - Refresh access token
GET  /api/auth/csrf-token     - Get CSRF token for SPA
GET  /api/auth/user           - Get current user info
```

### Security Headers
All API responses include:
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin`
- `Access-Control-Allow-Credentials: true`

## Frontend Integration

### 1. CSRF Token Handling
```javascript
// Get CSRF token before making requests
const response = await fetch('/api/auth/csrf-token');
const { csrf_token } = await response.json();

// Include in requests
fetch('/api/protected-endpoint', {
  method: 'POST',
  headers: {
    'X-CSRF-TOKEN': csrf_token,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(data)
});
```

### 2. Authentication Flow
```javascript
// Login
const loginResponse = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ login: 'user@example.com', password: 'password' })
});

const { access_token, refresh_token } = await loginResponse.json();

// Store tokens securely
localStorage.setItem('access_token', access_token);
localStorage.setItem('refresh_token', refresh_token);

// Use access token in requests
fetch('/api/protected-endpoint', {
  headers: {
    'Authorization': `Bearer ${access_token}`,
    'X-CSRF-TOKEN': csrf_token
  }
});
```

### 3. Token Refresh
```javascript
// Refresh access token when expired
const refreshResponse = await fetch('/api/auth/refresh', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ refresh_token: localStorage.getItem('refresh_token') })
});

const { access_token } = await refreshResponse.json();
localStorage.setItem('access_token', access_token);
```

## Security Best Practices

### 1. Token Storage
- **Access Tokens**: Store in memory or secure HTTP-only cookies
- **Refresh Tokens**: Store securely, consider using secure cookies
- **CSRF Tokens**: Store in memory, refresh periodically

### 2. Request Security
- Always include CSRF tokens for stateful requests
- Use HTTPS in production
- Validate all input data
- Implement rate limiting

### 3. Session Management
- Implement proper logout functionality
- Revoke tokens on logout
- Clean up expired tokens regularly
- Monitor for suspicious activity

## Maintenance Commands

### Clean Up Expired Tokens
```bash
# Show what would be deleted (dry run)
php artisan tokens:cleanup --dry-run

# Actually delete expired tokens
php artisan tokens:cleanup
```

### Schedule Token Cleanup
Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('tokens:cleanup')->daily();
}
```

## Configuration

### Sanctum Configuration (`config/sanctum.php`)
```php
'stateful' => [
    'localhost',
    'localhost:3000',
    'localhost:5173',
    '127.0.0.1',
    '127.0.0.1:8000',
    '::1',
],

'expiration' => 60 * 24, // 24 hours
```

### CORS Configuration (`config/cors.php`)
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

## Security Considerations

### 1. Production Deployment
- Use HTTPS only
- Set secure cookie flags
- Implement proper session configuration
- Use environment-specific CORS settings

### 2. Token Security
- Implement token rotation
- Monitor token usage patterns
- Implement device fingerprinting
- Consider implementing 2FA

### 3. API Security
- Implement rate limiting
- Add request logging
- Monitor for suspicious patterns
- Regular security audits

## Troubleshooting

### Common Issues
1. **CSRF Token Mismatch**: Ensure CSRF token is included in requests
2. **Token Expired**: Implement automatic token refresh
3. **CORS Issues**: Check domain configuration in Sanctum
4. **Authentication Failed**: Verify user credentials and account status

### Debug Mode
Enable debug logging in `.env`:
```
LOG_LEVEL=debug
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173
```

This security implementation provides a robust foundation for secure API authentication and should be regularly reviewed and updated as security best practices evolve.





