# Configuration and deployment

See .env.example and config/starter-kit.php for defaults.

| Setting | Purpose |
| --- | --- |
| STARTER_KIT_AUTH | both, stateful or stateless |
| STARTER_KIT_REGISTRATION | Enable public registration |
| STARTER_KIT_PASSWORD_RESET | Enable password-reset endpoints |
| STARTER_KIT_EMAIL_VERIFICATION | Enable email verification |
| STARTER_KIT_TWO_FACTOR | Enable TOTP enrollment |
| STARTER_KIT_REQUIRE_TWO_FACTOR | Require MFA before protected operations |
| STARTER_KIT_ACCOUNT_DELETION | Allow self-service account deletion |
| STARTER_KIT_DEVICE_MANAGEMENT | Enable device management endpoints |
| STARTER_KIT_REMEMBER_ME | Enable persistent browser logins |
| AUTH_REMEMBER_DAYS | Persistent browser credential lifetime, days |
| SANCTUM_TOKEN_EXPIRATION | Token lifetime, minutes |
| AUTH_PASSWORD_TIMEOUT | Password confirmation lifetime, seconds |
| AUTH_RESET_EXPIRATION | Password reset lifetime, minutes |
| AUTH_CHALLENGE_LIFETIME | MFA challenge lifetime, seconds |
| AUTH_RATE_LIMIT | Authentication requests per minute |
| AUTH_VERIFICATION_RATE_LIMIT | Verification requests per minute |
| AUTH_SENSITIVE_RATE_LIMIT | Sensitive mutations per minute |
| AUTH_SENSITIVE_READ_RATE_LIMIT | Sensitive reads per minute |
| AUTH_SECURITY_NOTIFICATIONS | Send account security notifications |
| AUTH_NEW_DEVICE_NOTIFICATIONS | Include new device login notifications |
| API_LOCALES | Comma-separated allowed request languages (default en) |

Configure frontend clients in config/frontends.php or config/frontend-clients.php. Set FRONTEND_WEB_URL, FRONTEND_WEB_ORIGIN, FRONTEND_MOBILE_URL and optional FRONTEND_MOBILE_ORIGIN for the default configuration. Explicit SANCTUM_STATEFUL_DOMAINS and CORS_ALLOWED_ORIGINS override automatic derived lists. Rebuild configuration and route caches after changing feature flags.

Localization negotiates Accept-Language against API_LOCALES, returns Content-Language and uses Laravel's translator. English is default. A starter Danish JSON translation is included as an example; add full framework validation/auth translations and any application-specific translations for your supported languages. API error codes remain untranslated.

## Production checklist

- Set APP_ENV=production, APP_DEBUG=false, APP_URL to the actual HTTPS API address and a secret APP_KEY. Restrict hosts/proxies at the web server and application; never trust forwarded headers from arbitrary clients.
- Use same-site browser domains, SESSION_SECURE_COOKIE=true, an appropriate SESSION_DOMAIN, and database-backed sessions. Keep cookies HttpOnly where appropriate. Allow only owned, trusted frontend origins.
- Use a shared cache with atomic locks in multi-instance deployments and a consistent database. Restrict database access and maintain encrypted backups.
- Configure real email transport and a queue worker for queued notifications. Run the scheduler for token, challenge, persistent device and invitation pruning.
- Migrate the database, rebuild caches, and verify / and /ready after deployment. Keep docs restricted; the readiness route tests database connectivity, not every external dependency.
- Review permissions and all added endpoints, dependency advisories and authentication rate limits. Do not expose tokens or credentials in logs or analytics. Independently review the security model before production.

This template does not provide billing, enterprise SSO, social login, WebAuthn, push notifications or application-specific tenancy isolation.
