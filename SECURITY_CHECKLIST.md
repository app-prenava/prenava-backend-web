# Prenava Security Checklist

## Completed Security Fixes

### ✅ 1. JWT Token Expiration
- **File**: `config/jwt.php`
- **Fix**: Changed `ttl` from `null` (never expiring) to `60` minutes
- **Impact**: Tokens now expire after 1 hour, reducing security risk

### ✅ 2. CORS Configuration
- **File**: `config/cors.php`
- **Fix**: Added environment-based CORS configuration
- **Impact**: Can now restrict API access to specific domains

### ✅ 3. Proxy Trust Configuration
- **File**: `app/Http/Middleware/TrustProxies.php`
- **Fix**: Changed from trusting all proxies (`'*'`) to configurable list
- **Impact**: Prevents header spoofing attacks

### ✅ 4. Security Headers Middleware
- **File**: `app/Http/Middleware/SecurityHeaders.php` (NEW)
- **Features**:
  - X-Frame-Options: DENY (prevents clickjacking)
  - X-Content-Type-Options: nosniff (prevents MIME sniffing)
  - X-XSS-Protection: 1; mode=block (browser XSS filter)
  - Strict-Transport-Security (HSTS) on HTTPS
  - Content-Security-Policy (CSP)
  - Permissions-Policy
  - Server header obfuscation

### ✅ 5. Input Sanitization Middleware
- **File**: `app/Http/Middleware/SanitizeInput.php` (NEW)
- **Features**:
  - Trims whitespace
  - Removes null bytes
  - HTML entity encoding (XSS prevention)
  - Removes control characters
  - Recursive array sanitization

## Required Environment Variables

Add these to your `.env` file:

```bash
# JWT Configuration
JWT_TTL=60                    # Token lifetime in minutes (1 hour)
JWT_REFRESH_TTL=20160         # Refresh token lifetime (2 weeks)

# CORS Configuration
# Comma-separated list of allowed origins
# For production, replace with your actual domains
CORS_ALLOWED_ORIGINS=https://prenava.app,https://www.prenava.app

# Trusted Proxies (if using load balancer/CDN)
# Leave empty if not using a proxy
# For Cloudflare: TRUSTED_PROXIES=*
# For specific IPs: TRUSTED_PROXIES=192.168.1.1,10.0.0.1
TRUSTED_PROXIES=
```

## Security Verification Commands

### 1. Check Security Headers
```bash
curl -I https://prenavabe.cloud/api/auth/login
```

Expected headers:
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Strict-Transport-Security: max-age=31536000` (on HTTPS)
- `Content-Security-Policy: ...`

### 2. Test CORS Restrictions
```bash
curl -H "Origin: https://malicious-site.com" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS https://prenavabe.cloud/api/auth/login
```

Expected: Access-Control-Allow-Origin should NOT be present for unlisted origins

### 3. Test JWT Token Expiration
After login, the token should expire after 60 minutes.

### 4. Run OWASP ZAP Scan
```bash
# Install ZAP first
zap-cli quick-scan --self-contained https://prenavabe.cloud
```

## Remaining Security Recommendations

### 🟡 Medium Priority

1. **Rate Limiting Per Endpoint**
   - Currently using global `throttle:api`
   - Consider implementing endpoint-specific rate limits
   - File: `app/Http/Kernel.php`

2. **API Key/Secret Management**
   - Ensure no secrets are hardcoded
   - Use environment variables for all sensitive data
   - Rotate JWT_SECRET periodically

3. **Error Messages**
   - Ensure error messages don't leak sensitive information
   - Use generic messages for authentication failures

### 🔴 High Priority

1. **HTTPS Only**
   - Redirect all HTTP traffic to HTTPS
   - Configure server (Nginx/Apache) properly

2. **SQL Injection Prevention**
   - Use Laravel's Eloquent ORM (parameterized queries)
   - Avoid raw SQL queries with user input

3. **File Upload Security**
   - Validate file types
   - Scan uploaded files for malware
   - Store uploads outside public directory

## Security Headers Reference

| Header | Purpose | Current Value |
|--------|---------|---------------|
| X-Frame-Options | Prevents clickjacking | DENY |
| X-Content-Type-Options | Prevents MIME sniffing | nosniff |
| X-XSS-Protection | Browser XSS filter | 1; mode=block |
| Strict-Transport-Security | Enforces HTTPS | max-age=31536000 |
| Content-Security-Policy | Controls resource loading | Configured |
| Permissions-Policy | Controls browser features | Configured |

## Post-Implementation Tasks

- [ ] Deploy security fixes to staging
- [ ] Test all API endpoints with new security headers
- [ ] Run security scan (OWASP ZAP)
- [ ] Update CORS_ALLOWED_ORIGINS in production
- [ ] Configure TRUSTED_PROXIES if using CDN/load balancer
- [ ] Document JWT_SECRET rotation procedure
- [ ] Train team on security best practices
