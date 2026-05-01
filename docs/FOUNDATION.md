# Verivote Foundation

## Visual Thesis
Verivote should feel like a secure control room: matte black surfaces, deep purple light, restrained motion, and dense operational layouts that communicate trust, scrutiny, and permanence.

## Content Plan
1. Hero: brand, trust statement, immediate access to active elections and verification.
2. Support: explain the security model, verification pipeline, and privacy separation.
3. Detail: operational dashboards for creators, co-admins, verifiers, and auditors.
4. Final CTA: register, review live events, or access a role dashboard.

## Interaction Thesis
1. Layered panel reveals on page load using opacity and vertical translation.
2. Soft glow/focus transitions on primary actions and verification badges.
3. Sticky dashboard sidebars with compact status indicators for dense admin workflows.

## Folder Structure
```text
Capstone/
├── index.php
├── events.php
├── event.php
├── results.php
├── audit.php
├── README.md
├── config/
│   ├── config.php
│   └── database.php
├── includes/
│   ├── bootstrap.php
│   ├── auth.php
│   ├── audit.php
│   ├── csrf.php
│   ├── helpers.php
│   ├── uploads.php
│   ├── validations.php
│   ├── header.php
│   ├── footer.php
│   ├── flash.php
│   ├── navigation.php
│   └── sidebar.php
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── verify-email.php
│   ├── forgot-password.php
│   ├── reset-password.php
│   └── logout.php
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   └── audits.php
├── creator/
│   ├── dashboard.php
│   ├── event_form.php
│   ├── candidates.php
│   ├── required_fields.php
│   ├── verification_methods.php
│   ├── verifications.php
│   ├── tokens.php
│   ├── results.php
│   └── audit_logs.php
├── coadmin/
│   └── dashboard.php
├── verifier/
│   └── dashboard.php
├── voter/
│   ├── dashboard.php
│   ├── register_event.php
│   ├── cast_vote.php
│   └── verify_vote.php
├── api/
│   ├── event_snapshot.php
│   ├── submission_file.php
│   ├── token_preview.php
│   └── verification_feed.php
├── assets/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── images/
│       └── logo-mark.svg
├── uploads/
│   ├── .htaccess
│   ├── documents/
│   └── photos/
├── database/
│   ├── schema.sql
│   └── seed.sql
└── docs/
    ├── FOUNDATION.md
    └── SECURITY_AND_LIMITATIONS.md
```

## Shared Includes Contract
- `config/config.php`: application constants, app key, mail/notification defaults, upload limits.
- `config/database.php`: PDO connection factory.
- `includes/bootstrap.php`: session bootstrap, helper loading, and shared request context.
- `includes/auth.php`: login, logout, role checks, event access checks, permission checks.
- `includes/audit.php`: tamper-evident audit chain writer and event audit helpers.
- `includes/csrf.php`: token generation and request validation.
- `includes/helpers.php`: escaping, flash messages, redirects, formatting, token hashing, receipt generation.
- `includes/uploads.php`: MIME validation, random naming, safe storage outside executable paths.
- `api/submission_file.php`: authenticated evidence delivery endpoint with audit logging.
- `includes/validations.php`: request validation helpers and common business rule checks.
- `includes/header.php` / `footer.php`: shared shell for public pages.
- `includes/navigation.php`: top navigation.
- `includes/sidebar.php`: dashboard navigation shell for privileged roles.

## Core Data Model
Identity, verification, and ballots are intentionally split:

1. `users` and `voter_profiles`
   - account identity and persistent profile details
2. `voter_event_submissions` and `voter_submission_answers`
   - event-specific registration data and uploaded verification material
3. `verification_methods`, `voter_verifications`, `verification_codes`
   - configurable approval steps and one-time codes
4. `voting_tokens`
   - single-use event credentials, stored hashed with HMAC
5. `ballots`
   - anonymous recorded votes keyed only by an anonymous ballot key and receipt hashes
6. `audit_logs` and `result_snapshots`
   - tamper-evident oversight and publishable integrity checkpoints

## Security Model
- Passwords use `password_hash()` / `password_verify()`.
- Voting tokens are never stored in plain text. The database stores an HMAC-SHA256 digest using the application key.
- Vote records never store `user_id` or submission identifiers directly.
- Ballots are created only inside a transaction with token revalidation and row locking.
- CSRF tokens protect state-changing forms.
- Role checks are enforced server-side for every privileged page.
- Rate limiting is enforced for login, vote token lookups, and verification code entry.
- Uploads are randomised, MIME-checked, size-limited, and stored beneath a non-executable uploads directory.
- Sensitive review files are served through an authenticated PHP endpoint instead of direct public links.
- Audit records are hash-chained with `previous_hash` and `entry_hash` fields.

## Visual System
### Palette
- `--bg-black`: `#050309`
- `--bg-dark`: `#0d0716`
- `--bg-panel`: `#130b22`
- `--bg-panel-soft`: `rgba(21, 12, 36, 0.76)`
- `--purple-primary`: `#7b2cff`
- `--purple-secondary`: `#a855f7`
- `--purple-soft`: `#d2b8ff`
- `--purple-glow`: `rgba(123, 44, 255, 0.28)`
- `--text-light`: `#f4f1ff`
- `--text-muted`: `#a79cbc`
- `--border-dark`: `rgba(186, 160, 255, 0.18)`
- `--success`: `#3ddc97`
- `--warning`: `#f9c74f`
- `--danger`: `#ff6b8b`

### Typography
- Headings: `Space Grotesk`, bold, compact tracking, uppercase labels sparingly.
- Body/UI: `IBM Plex Sans`, dense but readable interface copy.

### Components
- Buttons: solid purple gradient primary, ghost secondary, danger outline for revocation actions.
- Forms: dark inputs with subtle border glow on focus.
- Tables: bordered rows on transparent surfaces with sticky headers in dashboards.
- Cards/Panels: only for operational clusters, not for every section.
- Alerts: success, warning, error, and info variants with left accent bars.
- Status pills: draft, active, approved, rejected, used, revoked, verified.

### Layout Rules
- Public landing hero is full width and cinematic.
- Dashboards are grid-based with a fixed sidebar and content rail.
- Tables and audit data prioritise scanning over decoration.
- Purple glow is reserved for primary actions, selected states, and key integrity indicators.

## Implementation Order
1. Shared config/bootstrap/helpers
2. Schema + seed alignment
3. Auth and session flows
4. Event and verification management
5. Token issuance and vote casting
6. Public results and audit verification
