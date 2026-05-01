# Verivote — Demo Walkthrough Guide

A presenter's guide to demonstrating every role and flow in Verivote. Follow the sections in order for a full end-to-end story, or jump to a specific role as needed.

---

## 0. Before the Demo

1. Start XAMPP: **Apache** and **MySQL** must both be running.
2. Load `http://localhost/Capstone/` — the landing page should render without errors.
3. Open a terminal tailing `logs/app.log` so the audience can see backend activity in real time:
   ```
   tail -f C:/xampp/htdocs/Capstone/logs/app.log
   ```
4. Have two browsers (or one regular + one incognito window) ready so you can switch between the **creator** and **voter** without repeatedly logging in and out.

### Seed Accounts
All demo accounts use the password **`Verivote!123`** except the voter.

| Role            | Email                          | Password       |
|-----------------|--------------------------------|----------------|
| Super Admin     | `superadmin@verivote.test`     | `Verivote!123` |
| Event Creator   | `creator@verivote.test`        | `Verivote!123` |
| Co-Admin        | `coadmin@verivote.test`        | `Verivote!123` |
| Trusted Verifier| `verifier@verivote.test`       | `Verivote!123` |
| Voter           | `voter@verivote.test`          | `VoterPass!123`|

### Talking Points (Opening)
- PHP 8 + MySQL, no frameworks — written from scratch to show full control over auth, sessions, and data integrity.
- Five distinct roles, each with its own dashboard and scoped permissions.
- Security-first: CSRF on every form, prepared statements everywhere, rate-limited logins, anonymous ballots, hash-chained audit log.

---

## 1. Public Visitor Flow (no login)

**Goal:** Show what anyone on the internet can see before they register.

1. **Home** → `http://localhost/Capstone/`
   Highlight: clean public landing with event promo and call-to-action.
2. **Events** → click *Events* in the nav (`/events.php`).
   Show the list of active and scheduled public events.
3. **Event detail** → click an event (`/event.php?id=...`).
   Point out event title, description, dates, and candidate list.
4. **Results** → `/results.php`.
   Show published results of closed events. Note that results are only visible once the creator publishes a snapshot.
5. **Audit** → `/audit.php`.
   Show the public audit trail. Explain that every state-changing action writes a **hash-chained** entry, so any tampering breaks the chain.

> **Talking point:** The public can verify election integrity without logging in or exposing any voter identity.

---

## 2. Voter Registration & Phone Verification

**Goal:** Show account creation and multi-factor identity verification.

1. Click **Register** → `/auth/register.php`.
2. Fill in full name, email, phone, password (mention the password policy).
3. Submit → system creates the account and sends an SMS OTP.
4. Landed on **Verify Phone** → `/auth/verify-email.php` (despite the filename, this is phone verification).
   - In development mode, the page shows the latest queued OTP as a helper box.
5. Enter the code → phone is marked verified.

> **Talking points**
> - SMS OTP through a hand-rolled sender (mirrors the SMTP pipeline).
> - Rate-limited: after too many attempts, user is temporarily locked out.
> - Passwords are hashed with `password_hash()` (bcrypt); no plaintext storage.

### Forgot Password (optional aside)
1. Go to `/auth/forgot-password.php`, enter a known email, submit.
2. Check the Gmail inbox — a real reset email arrives via Gmail SMTP.
3. Click the link → `/auth/reset-password.php?token=...` → set a new password.

> **Security callout:** Tokens are stored as HMAC-SHA256 digests, single-use, time-limited, and **race-safe** — a conditional `UPDATE ... WHERE used_at IS NULL` ensures the token can only redeem once even under concurrent clicks.

---

## 3. Event Creator Flow

**Goal:** Show how an election is configured end to end.

Log in as `creator@verivote.test`.

### 3.1 Creator Dashboard
- `/creator/dashboard.php` — shows events owned by this creator plus recent audit rows.

### 3.2 Create / Edit an Event
1. Open an event or click **New Event** → `/creator/event_form.php`.
2. Walk through: **title**, **description**, **start/end dates**, **status** (draft → scheduled → active → closed → archived), **visibility** (public / private).
3. Save.

### 3.3 Candidates
1. `/creator/candidates.php?event=<id>`.
2. Add candidates with name, bio, and optional photo.
3. Show reorder / remove controls.

### 3.4 Required Voter Fields
1. `/creator/required_fields.php?event=<id>`.
2. Add a **file upload** field (e.g. "Student ID photo") and a **text** field ("Student number").
3. Mark which ones are required.

> **Talking point:** Required fields are fully dynamic per event — no schema change needed to ask for new voter info.

### 3.5 Verification Methods
1. `/creator/verification_methods.php?event=<id>`.
2. Add one or more of:
   - **email_verification** — system emails an OTP to the voter.
   - **sms_verification** — system SMS-es an OTP.
   - **document_review** — co-admin manually reviews uploaded docs.
   - **trusted_verifier** — in-person/manual approval by a designated verifier.
3. Order matters — voters must clear every method.

### 3.6 Assign Co-Admins & Verifiers
Walk through the assignment UI on the event (co-admins for document review, verifiers for trusted-verifier method).

### 3.7 Tokens
- `/creator/tokens.php?event=<id>` — the creator can issue or revoke voting tokens.
- Tokens are issued to **approved voters only**.
- Delivery: SMS first; email fallback if SMS is disabled or fails.

### 3.8 Results & Audit
- `/creator/results.php?event=<id>` — publish a results snapshot when the event closes.
- `/creator/audit_logs.php?event=<id>` — scoped audit log for this event only.

---

## 4. Voter Event Registration Flow

**Goal:** Show a verified voter joining an event.

Log in as `voter@verivote.test`.

1. **Voter dashboard** → `/voter/dashboard.php`.
   - Show *Registered events* and *Quick Actions* (Register / Vote / Verify receipt).
2. Click **Browse events** → pick the active demo event.
3. Click **Register** → `/voter/register_event.php?event=<id>`.
4. Fill the required fields (text + upload).
5. Submit → submission appears on the dashboard with status **pending**.
6. Complete each **verification method** in order:
   - Email OTP: click "Send code", check inbox (or dev helper), paste code.
   - SMS OTP: same flow, via SMS.
   - Document review / trusted verifier: shown as "Pending reviewer".

> **Talking point:** The voter cannot skip a step — each method locks the next until completed.

---

## 5. Co-Admin Review Flow

**Goal:** Show delegated review work without granting full admin.

Log in as `coadmin@verivote.test` (or stay as creator; permissions overlap by design).

1. `/coadmin/dashboard.php` or `/creator/verifications.php?event=<id>`.
2. Open a pending submission.
3. Show the evidence viewer — uploaded files open through a signed short-lived link (no public URL).
4. Approve or reject each method-level verification with notes.

> **Talking point:** Reviewers only see events they're assigned to. Evidence is fetched through an access-checked handler, not served from a public path.

---

## 6. Trusted Verifier Flow

**Goal:** Show in-person identity confirmation.

Log in as `verifier@verivote.test`.

1. `/verifier/dashboard.php` — queue of pending trusted-verifier tasks for assigned events.
2. Pick a voter, enter notes (**required**), pick **Approve** or **Reject**, save.
3. The method is marked reviewed and the submission's last-reviewed timestamp updates.

> **Talking point:** Note field is mandatory — prevents silent approvals and creates a reviewer-attributed audit trail.

---

## 7. Finalize Submission & Issue Token

**Goal:** Show the final gate before voting.

Back as **creator** or **co-admin**:

1. Open the voter's submission on `/creator/verifications.php`.
2. When every method is approved, the **Approve submission** button unlocks.
3. Approve → audit entry `submission.approved` written.
4. Open `/creator/tokens.php?event=<id>` → click **Issue token** for that voter.
5. Token is delivered by SMS (and/or email fallback).

> **Security callouts**
> - Token is shown to the voter **once**; the DB only stores an HMAC-SHA256 digest.
> - Each token is single-use and bound to a user + event.
> - Issuance, use, and revocation all log structured events (`token.issued`, `token.used`, `token.revoked`).

---

## 8. Cast a Vote

**Goal:** The payoff — actually voting, anonymously.

Switch back to the **voter** browser.

1. Open the SMS/email with the token, or have it ready.
2. `/voter/cast_vote.php` → enter the token.
3. The page validates the token and shows the ballot with candidates.
4. Pick a choice, submit.
5. The voter receives a **private receipt hash**. Copy it.

> **Security callouts (say these slowly)**
> - Vote casting runs in a DB transaction: token is revalidated **inside** the transaction, then the ballot is inserted and the token burned. Concurrent double-submit is impossible.
> - The `ballots` table has **no foreign key to users** — there is no way to map a ballot back to a voter.
> - The ballot row stores an **anonymous ballot key**, a private receipt (voter's proof), and a public receipt (appears on audit page).

---

## 9. Verify the Receipt

**Goal:** Show voter-side verifiability.

1. `/voter/verify_vote.php` → paste the private receipt.
2. Page confirms: "Your ballot is recorded with choice X."
3. Open `/audit.php` (public) in a fresh tab → the matching **public receipt** is listed for anyone to see.

> **Talking point:** The voter can prove their ballot counted without revealing *how* they voted to anyone else. The public can verify *that* a ballot exists without knowing *who* cast it.

---

## 10. Super Admin Flow

**Goal:** Show platform-level oversight.

Log in as `superadmin@verivote.test`.

1. `/admin/dashboard.php` — platform-wide stats.
2. `/admin/users.php` — create/suspend privileged accounts (creators, co-admins, verifiers).
3. `/admin/audits.php` — full hash-chained audit log across the platform.

> **Talking point:** Super admins can read everything but cannot alter historical audit rows — the `entry_hash` chain would break and be flagged immediately.

---

## 11. Show the Backend Activity Log

Close the loop by showing `logs/app.log` live during the demo:

```
[2026-04-24 12:46:02] [INFO] [uid=5 ip=127.0.0.1 POST /voter/cast_vote.php] token.used {"token_id":14,"event_id":1}
[2026-04-24 12:46:02] [INFO] [uid=5 ip=127.0.0.1 POST /voter/cast_vote.php] vote.cast {"event_id":1,"ballot_ref":"..."}
```

> **Talking point:** Every critical backend event — login, SMS send, SMTP send, token issue/use, vote cast, submission review — is logged in a human-readable append-only file, so an operator can trace any incident chronologically without running SQL.

---

## 12. Suggested 10-Minute Demo Script

If you only have ten minutes, run this condensed path:

1. **Opening (30 s)** — landing page, stack pitch, log tail open.
2. **Creator (2 min)** — open an active event, show candidates, required fields, verification methods.
3. **Voter register (2 min)** — register for that event, upload doc, complete email OTP.
4. **Review (1 min)** — switch to creator, approve submission.
5. **Token (1 min)** — issue token, read it from the SMS/email.
6. **Cast vote (1 min)** — vote, copy private receipt.
7. **Verify (1 min)** — verify receipt, show public audit page.
8. **Wrap (1 min 30 s)** — show `app.log` scroll, summarize security properties: anonymous ballots, hash-chained audit, single-use HMAC tokens, CSRF + rate limits everywhere.

---

## 13. Common Demo Pitfalls

- **Emails not appearing in Inbox** — check Gmail's *All Mail* or *Spam*; self-addressed messages often route there. The SMTP row in `notifications` will show `status=sent`.
- **"Too many attempts" during demo** — clear rate-limit rows:
  ```sql
  DELETE FROM rate_limits;
  ```
- **OTP not received** — in development, the verify page surfaces the latest queued code at the bottom as a helper.
- **Token not working** — confirm the event status is `active` and the voter's submission is `approved`.
- **Reset link says invalid** — tokens are single-use; request a new one, don't reuse old emails.

---

## 14. One-Liner Pitch (for Q&A)

> Verivote is a zero-dependency PHP voting platform that pairs layered voter verification with anonymous, single-use HMAC-bound ballots, proves every ballot through split private/public receipts, and makes tampering detectable via a hash-chained audit log — all behind a role-based dashboard that separates creators, co-admins, verifiers, and admins.
