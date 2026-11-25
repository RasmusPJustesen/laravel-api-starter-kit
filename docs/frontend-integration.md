# Frontend integration

The backend is independent of the frontend. An application can register several browser/native clients using `config/frontends.php` or the generated `config/frontend-clients.php`. Each entry has an ID, URL, authentication preference, optional browser origin, and paths for password reset, email verification and login. The configured default is used for unknown or absent `X-Client` values. Client identifiers are routing/metadata hints, never credentials or authorization.

## Browser (Nuxt or other SPA)

Use same-site frontend and API domains, HTTPS and a compatible session cookie domain. A browser origin configured as stateful is included in Sanctum's stateful domain list and the CORS allowlist unless you explicitly override either list. Send `Accept: application/json`, `X-Client: web`, `credentials: include` and the decoded `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` on mutating requests.

1. GET /sanctum/csrf-cookie.
2. POST /spa/login with email, password and optional remember/device_name.
3. On 204, the session is authenticated; on 202, send the challenge_token and code or recovery_code to POST /spa/two-factor-challenge.
4. GET /me with credentials included. POST /logout with CSRF protection to revoke the current browser device.

Do not copy cookies into local storage. If using SSR, forward cookies only to your trusted API origin, and ensure the browser receives any necessary Set-Cookie response headers. Cross-site deployments may require a different authentication architecture.

## Native mobile or external API

POST /login with email/password and device_name; successful response contains token. For MFA, POST /two-factor-challenge with challenge_token and the second factor. POST /register returns the user resource and meta.token. Store tokens in the platform's protected credential storage, not browser local storage. Send Authorization: Bearer TOKEN and X-Client: mobile. POST /token/refresh rotates the current token; replace the old value atomically. POST /logout invalidates it.

## Email links

Password reset emails open the configured frontend's password_reset_path with token and email. Submit those values plus the new password and confirmation to POST /reset-password. Treat tokens as secrets: avoid analytics and referrer leakage on these pages.

Email verification uses Laravel's short-lived signed API URL. If email_verification_path is configured, the frontend receives that signed URL in the verification_url query parameter; validate that it targets your configured API origin and GET it unchanged. Do not construct your own signed URLs or follow arbitrary third-party URLs.

Team invitations link to the configured client login page. An authenticated, verified invitee can retrieve pending invitations from GET /user/invitations and accept or decline the matching invitation. For native apps, use HTTPS universal/app links on a domain you control. The native platform association files are your application's responsibility.

## TypeScript

After migrating the development database, run npm install and composer api:generate. Import the generated OpenAPI paths/components into your frontend HTTP layer. Responses follow the documented resource formats. Authentication tokens, challenge tokens and recovery codes should never be logged. Refresh the contract whenever backend routes, resources or validation change.
