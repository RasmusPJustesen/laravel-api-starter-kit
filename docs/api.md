# API overview

Use /docs for the complete generated reference, including enabled features and the selected collaboration name.

| Method | Path | Purpose |
| --- | --- | --- |
| POST | /register or /spa/register | Register a user |
| POST | /login or /spa/login | Authenticate, possibly returning a 202 MFA challenge |
| POST | /two-factor-challenge or /spa/two-factor-challenge | Consume a short-lived challenge |
| POST | /token/refresh | Rotate a bearer credential |
| POST | /logout | Revoke the current credential |
| GET | /me | Current profile |
| PATCH | /user | Update profile |
| DELETE | /user | Delete account with confirmation |
| PATCH | /user/password | Change password and revoke credentials |
| POST | /forgot-password | Request password reset |
| POST | /reset-password | Reset password |
| POST | /email/verification-notification | Resend verification |
| GET | /email/verify/{id}/{hash} | Verify signed email |
| POST | /user/confirm-password | Confirm password for sensitive operations |
| GET | /user/confirmed-password | Check current confirmation |
| GET | /user/two-factor-authentication | MFA status |
| POST | /user/two-factor-authentication | Begin enrollment |
| GET | /user/two-factor-secret-key | Enrollment key and otpauth URL |
| GET | /user/two-factor-qr-code | QR SVG and otpauth URL |
| POST | /user/confirmed-two-factor-authentication | Confirm setup using current code |
| DELETE | /user/two-factor-authentication | Disable with a second factor |
| GET, POST | /user/two-factor-recovery-codes | Read or regenerate recovery codes |
| GET | /user/sessions | List token and browser devices |
| PATCH | /user/sessions/{session} | Rename a device |
| DELETE | /user/sessions/{session} | Revoke a different device |
| DELETE | /user/sessions/others | Revoke all other credentials |

A recent password confirmation is tied to the specific session/token. MFA enrollment, recovery codes, device mutations and ownership transfers use confirmation; MFA disable/regeneration also require a valid factor. Password changes and resets invalidate existing credentials. The current device is marked in session results; use logout to terminate it. Legacy database sessions can be revoked but not renamed. Browser/device/OS metadata is best-effort.

Errors contain a stable error_details array with status, code, detail and an optional source. Validation failures retain Laravel's errors field map as well. Never branch on translated human-readable messages.

## Optional teams

The paths below assume the default name, team. With a custom name, substitute the generated plural/singular identifiers.

- GET, POST /teams; GET, PATCH, DELETE /teams/{team}; POST /teams/{team}/leave.
- GET /teams/{team}/members; PATCH, DELETE /teams/{team}/members/{user}.
- GET, POST /teams/{team}/invitations; DELETE /teams/{team}/invitations/{invitation}.
- GET /user/invitations; POST /invitations/{invitation}/accept; DELETE /invitations/{invitation}.
- POST /teams/{team}/ownership/{user}: transfer ownership to an existing member after password confirmation.

Owner, Admin and Member are the provided roles. Owners can manage all operations; admins can update the entity and manage invitations; members have basic access. Ownership transfer is explicit; shared-team owners cannot delete their account until ownership is transferred or the shared teams are removed. Extend the generated permission enum and policies for your application. A team membership is not automatically a tenancy boundary for new application models: enforce scope and policies for all resources you add.

## Extension events

Use Laravel's Registered, Verified and PasswordReset events and the starter-kit's AccountSecurityChanged and DeviceSessionsRevoked events. The optional module emits generated entity lifecycle, invitation and ownership-transfer events. Events are application extension points; listeners should avoid sensitive credential logging and handle failures safely.
