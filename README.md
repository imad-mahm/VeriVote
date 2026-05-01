# Verivote MVP

Verivote is a multi-page PHP 8 + MySQL voting platform built with plain HTML, plain CSS, and vanilla JavaScript. The project is structured as a serious academic MVP focused on secure event administration, layered voter verification, one-time ballot credentials, anonymous ballot storage, and public/private vote verification.

## Stack
- Frontend: HTML, CSS, vanilla JavaScript
- Backend: PHP 8+
- Database: MySQL 8 / MariaDB

## Project Layout
- `docs/FOUNDATION.md`: folder structure, shared includes, schema model, and visual system.
- `database/schema.sql`: full MySQL schema.
- `database/seed.sql`: demo roles, accounts, events, fields, methods, ballots, and snapshots.
- `config/`: application and database configuration.
- `includes/`: shared bootstrap, auth, CSRF, audit, validation, upload, and layout includes.
- `auth/`, `admin/`, `creator/`, `coadmin/`, `verifier/`, `voter/`: role and workflow pages.
- `api/`: lightweight JSON endpoints for audit and token previews.
- `assets/`: shared Verivote branding styles, JS, and logo assets.

## Local Setup
1. Create local environment file from template.
```bash
cp .env.example .env
```
On Windows PowerShell:
```powershell
Copy-Item .env.example .env
```
2. Update `.env` with your local database and SMS provider values.
3. Create a MySQL database user with permission to create and modify the `verivote` database.
4. Import the schema:
```bash
mysql -u root -p < database/schema.sql
```
5. Import the seed data:
```bash
mysql -u root -p verivote < database/seed.sql
```
6. Start the PHP server from the project root:
```bash
php -S localhost:8000
```
7. Open `http://localhost:8000`.
If you already imported an older version of the schema, re-import `database/schema.sql` and `database/seed.sql` because the verification workflow tables were extended in this phase.
For existing deployments, run `database/migrations/2026_04_22_phone_required_unique.sql` after cleaning missing/duplicate phones.

## SMS Configuration
- Set `SMS_ENABLED=1`, `SMS_PROVIDER=easy_sendsms`, `SMS_SENDER_ID`, `EASYSENDSMS_BASE_URL`, and `EASYSENDSMS_SEND_PATH`.
- Recommended for EasySendSMS REST v1:
  - `EASYSENDSMS_AUTH_TYPE=apikey`
  - `EASYSENDSMS_API_KEY=...`
  - Endpoint defaults: `https://restapi.easysendsms.app/v1/rest/sms/send`
- Compatibility modes are still available if your account is configured differently:
  - `bearer`, `x_api_key`, or `basic`
- Optional behavior:
  - `SMS_FALLBACK_TO_EMAIL=1` enables email fallback when token SMS delivery fails.
  - `PHONE_DEFAULT_COUNTRY_CODE=961` controls normalization for local numbers without country prefix.

## Gmail SMTP Configuration
Verivote ships with a hand-rolled SMTP client (no Composer dependency) that delivers password-reset links, email-OTP verification codes, and voting-token email fallbacks through Gmail.

1. The Gmail account you want to send from must have **2-Step Verification enabled** (https://myaccount.google.com/security → *2-Step Verification* → *Turn on*).
2. Generate an **App Password** at https://myaccount.google.com/apppasswords. Pick "Mail" + custom name "Verivote". Google returns a 16-character password (shown once — copy it immediately).
3. Fill `.env`:
   ```
   EMAIL_ENABLED=1
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_ENCRYPTION=starttls
   SMTP_USERNAME=your.address@gmail.com
   SMTP_PASSWORD=xxxx xxxx xxxx xxxx   # the 16-char app password (spaces optional)
   SMTP_FROM_EMAIL=your.address@gmail.com
   SMTP_FROM_NAME=Verivote
   ```
4. Optional tuning: `EMAIL_RETRY_ATTEMPTS` (default 2), `EMAIL_RETRY_DELAY_MS` (default 750), `EMAIL_TIMEOUT_SECONDS` (default 15), `SMTP_REPLY_TO`.

Notes:
- Gmail rejects a normal account password over SMTP — it must be an App Password.
- Free Gmail caps outgoing SMTP at roughly 500 recipients/day, which is plenty for capstone demos.
- Delivery is synchronous: each `send_email_notification()` call connects, authenticates, sends, and updates the `notifications` row's `status` to `sent` or `failed` before the request returns. Failures write an `email_delivery_failed` audit entry with the SMTP reply code.

## Seed Accounts
- Super Admin: `superadmin@verivote.test` / `Verivote!123`
- Event Creator: `creator@verivote.test` / `Verivote!123`
- Co-Admin: `coadmin@verivote.test` / `Verivote!123`
- Trusted Verifier: `verifier@verivote.test` / `Verivote!123`
- Voter: `voter@verivote.test` / `VoterPass!123`

## Main Flows
- Public users can browse events, published results, and audit pages.
- Voters register accounts, verify phone ownership by SMS, submit event-specific registration data, complete configured verification methods, and receive a one-time voting token after approval.
- Event creators configure events, required fields, candidate options, verifications, co-admins, verifiers, tokens, results, and audit logs.
- Co-admins assist only within explicitly assigned permissions.
- Trusted verifiers handle in-person or manual anti-impersonation checks.
- Super admins create privileged accounts and monitor platform-wide audit activity.

## Suggested Demo Walkthrough
1. Log in as `creator@verivote.test`.
2. Open an event and show:
   - event settings
   - candidate management
   - required-field management
   - verification-method management
   - co-admin / verifier assignment
3. Log in as `voter@verivote.test` or register a new voter account.
4. Verify the voter phone using the SMS OTP (development helper is visible from queued SMS notifications).
5. Submit an event registration with dynamic required fields and a document upload.
6. Log back in as creator or co-admin and review the submission:
   - open secure evidence links
   - approve or reject method-level verifications
   - show blocker summary
7. Log in as `verifier@verivote.test` and complete the trusted verifier step with notes.
8. Finalize the submission as approved and issue a token (SMS-first delivery with optional email fallback).
9. Cast a vote using the token from the voter flow.
10. Verify the private receipt and compare it with the public audit page.

## Additional Documentation
- Architecture and security notes: [`docs/SECURITY_AND_LIMITATIONS.md`](docs/SECURITY_AND_LIMITATIONS.md)

## Security / Privacy Design Choices
- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- Ballot tokens are stored as HMAC-SHA256 digests using the application key, never in plain text.
- Ballots do not store `user_id` or submission identifiers directly. They use an anonymous ballot key plus private/public receipt hashes.
- Vote casting revalidates the token inside a transaction before the ballot insert and token consumption write.
- CSRF protection is enforced on every state-changing form.
- Prepared statements are used for all database access.
- Rate limiting is applied to login, verification codes, and vote token previews.
- Uploads are MIME-validated, size-limited, randomly named, and stored under a non-executable uploads directory.
- Audit entries are chained through `previous_hash` and `entry_hash` to make tampering more detectable.

## Hosting Notes
- The included `uploads/.htaccess` blocks PHP execution under Apache-compatible hosting.
- If you deploy with Nginx, add an equivalent rule denying script execution in `/uploads`.
- Email notifications are dispatched synchronously via Gmail SMTP (when `EMAIL_ENABLED=1`) and recorded in the `notifications` table with provider status.
- SMS attempts are written into `notifications` and sent through the EasySendSMS integration when configured.

## Development Notes
- This is a serious MVP, not a finished production system.
- Before any public deployment, rotate `APP_KEY`, move secrets out of source, enforce HTTPS, configure real mail/SMS delivery, and put uploaded files behind web server rules or controlled download endpoints.

## Known Limitations
- Email delivery uses Gmail SMTP with an App Password; it will no-op (row status = `failed`, provider_code = `email_disabled`) unless `EMAIL_ENABLED=1` and `SMTP_USERNAME`/`SMTP_PASSWORD` are configured.
- EasySendSMS endpoint/auth settings must match your provider account before SMS delivery will succeed.
- Verification policy UI supports supported method families, but not arbitrary custom verification logic.
- Rejected submissions are resubmitted in place with audit snapshots rather than using a separate revision table.
- The project does not yet include automated integration tests or a production deployment configuration.
