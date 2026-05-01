# Verivote MVP — Demo Flow Guide

A walkthrough of every user type in the Verivote secure online voting platform.

---

## Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `superadmin@verivote.test` | `Verivote!123` |
| Event Creator | `creator@verivote.test` | `Verivote!123` |
| Co-Admin | `coadmin@verivote.test` | `Verivote!123` |
| Trusted Verifier | `verifier@verivote.test` | `Verivote!123` |
| Voter | `voter@verivote.test` | `VoterPass!123` |

---

## Public Flow (No Login Required)

Anyone visiting the site can:

1. **Landing page** (`/`) — See platform overview, active event promotions, and live metrics (users, events, ballots cast).
2. **Browse events** (`/events.php`) — View all active and public voting events.
3. **Event detail** (`/event.php?slug=...`) — Read event description, see candidates/options, required registration fields, and which verification methods are used.
4. **Results** (`/results.php`) — View published result snapshots for closed events.
5. **Audit trail** (`/audit.php`) — Inspect the hash-chained public audit log. Every action is recorded and tamper-evident.

---

## Flow 1 — Voter

### Step 1: Register
- Go to `/auth/register.php`
- Enter full name, email, phone number, and a password (10+ chars, must contain uppercase, lowercase, and a number)
- Submit the form — an SMS OTP is sent to your phone (email fallback if SMS unavailable)

### Step 2: Verify Phone
- Go to `/auth/verify-email.php`
- Enter the 6-digit OTP received by SMS
- Account is now fully active

### Step 3: Log In
- Go to `/auth/login.php`
- Enter email (or phone) and password
- Redirected to `/voter/dashboard.php`

### Step 4: Register for an Event
- From the dashboard or `/events.php`, find a public event and click **Register**
- Go to `/voter/register_event.php?event=<slug>`
- Fill in any required fields the event creator has defined (e.g., Student ID, upload a photo ID)
- Submit — your submission status is now **Pending**

### Step 5: Complete Verification
Depending on the event's verification policy, you may need to complete one or more of:
- **Email OTP** — A code is emailed; enter it on the verification page
- **SMS OTP** — A code is sent by SMS; enter it on the verification page
- **Document Review** — Upload a document; event staff reviews it
- **Trusted Verifier** — An in-person check by a designated verifier

Each step moves your submission from **Pending → Under Review → Approved**.

### Step 6: Receive a Voting Token
- Once approved, the event creator issues a single-use voting token sent via SMS (or email fallback)
- The token appears in your dashboard under **Active Tokens**

### Step 7: Cast Your Vote
- Go to `/voter/cast_vote.php`
- Enter the token and select your candidate/option
- Submit — your ballot is recorded anonymously (not linked to your identity)
- A personal receipt hash is generated for your records

### Step 8: Verify Your Vote
- Go to `/voter/verify_vote.php`
- Enter your receipt hash to confirm your ballot was recorded correctly
- This verifies your vote without exposing your identity on the public audit trail

---

## Flow 2 — Event Creator

### Step 1: Log In
- Go to `/auth/login.php` → redirected to `/creator/dashboard.php`
- Dashboard shows owned events with counts: submissions in review, tokens issued, ballots cast

### Step 2: Create an Event
- Click **New Event** or go to `/creator/event_form.php`
- Fill in:
  - **Title** and description
  - **Start / End dates** and timezone
  - **Visibility** — Public (listed on /events.php) or Private (invite only)
  - **Status** — Draft → Scheduled → Active → Closed → Archived
  - **Verification policy** — All Required / Any One / Custom
  - **Max votes per token**, result visibility timing
- Save the event

### Step 3: Add Candidates / Options
- Go to `/creator/candidates.php?event=<id>`
- Add each candidate: name, bio, optional photo
- Drag to reorder; remove unwanted entries

### Step 4: Define Required Fields
- Go to `/creator/required_fields.php?event=<id>`
- Add fields voters must fill in when registering (e.g., "Student ID Number" as text, or "Photo ID" as file upload)
- Fields are presented to voters during event registration

### Step 5: Configure Verification Methods
- Go to `/creator/verification_methods.php?event=<id>`
- Enable any combination of:
  - Email OTP
  - SMS OTP
  - Document review (manual staff check)
  - Trusted verifier (in-person)
- Set the policy: voters must complete **all** or **any one** of the enabled methods

### Step 6: Assign Co-Admins and Verifiers (optional)
- Assign co-admins to help manage the event with scoped permissions
- Assign trusted verifiers who will handle in-person identity checks

### Step 7: Review Voter Submissions
- Go to `/creator/verifications.php?event=<id>`
- See all pending submissions; click into each to review:
  - Field answers typed by the voter
  - Uploaded documents (via secure links)
  - Verification method status for each step
- Approve or reject individual verification steps with optional notes

### Step 8: Issue Voting Tokens
- Go to `/creator/tokens.php?event=<id>`
- For each approved voter, issue a single-use token
- Token is sent via SMS (email fallback)
- Tokens can be revoked if needed

### Step 9: Publish Results
- After the event closes, go to `/creator/results.php?event=<id>`
- Publish a results snapshot — becomes publicly visible on `/results.php`
- Control the timing of public visibility

### Step 10: Review Audit Logs
- Go to `/creator/audit_logs.php?event=<id>`
- View all event-scoped activity: registrations, verifications, token issuances, ballots cast
- Hash-chained for tamper detection

---

## Flow 3 — Co-Admin

### Step 1: Log In
- Go to `/auth/login.php` → redirected to `/coadmin/dashboard.php`
- Dashboard lists only events the co-admin has been explicitly assigned to

### Step 2: Access Event Tools
- Tools available depend on permissions granted by the event creator:
  - Review voter submissions and verification steps
  - Approve/reject verification methods
  - Issue or revoke tokens
- Co-admins cannot create or delete events, change event settings, or access events they are not assigned to

### Step 3: Perform Assigned Tasks
- Navigate into an assigned event and perform whichever tasks the creator has permitted
- All actions are audit-logged under the co-admin's account

---

## Flow 4 — Trusted Verifier

### Step 1: Log In
- Go to `/auth/login.php` → redirected to `/verifier/dashboard.php`
- Dashboard shows a queue of pending **Trusted Verifier** steps across assigned events

### Step 2: Review Pending Queue
- Each item in the queue shows a voter's submission awaiting in-person or manual identity confirmation
- Click into a submission to see the voter's submitted information

### Step 3: Approve or Reject
- After performing the identity check (in-person meeting, document inspection, etc.):
  - **Approve** — marks the trusted-verifier step as complete; voter continues through verification chain
  - **Reject** — marks it as rejected; a reason/note is required
- All decisions are audit-logged

---

## Flow 5 — Super Admin

### Step 1: Log In
- Go to `/auth/login.php` → redirected to `/admin/dashboard.php`
- Dashboard shows platform-wide metrics: total users, events, verifications in queue, ballots cast, recent audit entries

### Step 2: Create Privileged Accounts
- Go to `/admin/users.php`
- Create accounts for:
  - **Event Creators** — will be able to create and manage events
  - **Co-Admins** — assigned to events by creators
  - **Trusted Verifiers** — assigned to events by creators
- These accounts are auto-verified (no OTP required)

### Step 3: Monitor Platform Activity
- Go to `/admin/audits.php`
- View the full platform-wide audit trail (300 most recent entries)
- Every user action is recorded with a hash-chain for tamper detection: registrations, logins, verification decisions, token issuances, ballots cast, settings changes

### Step 4: Manage Platform Settings
- Go to `/admin/settings.php`
- Configure platform-level settings (SMS provider, email config, rate limits, etc.)

---

## Verification Policy Reference

| Policy | Meaning |
|--------|---------|
| `all_required` | Voter must complete every enabled verification method |
| `any_one` | Voter must complete at least one enabled verification method |
| `custom` | Voter must complete a specific subset defined by the creator |

## Event Status Lifecycle

```
Draft → Scheduled → Active → Closed → Archived
```

- **Draft** — Being configured; not visible to voters
- **Scheduled** — Locked configuration; visible but not yet accepting submissions
- **Active** — Open for voter registration, verification, and voting
- **Closed** — No new votes accepted; results can be published
- **Archived** — Historical record; read-only

## Ballot Privacy Model

- Ballots are stored with an anonymous key — **not linked to a user ID**
- Voting tokens are stored as HMAC-SHA256 digests; the raw token is never stored
- Voters receive a personal receipt hash to self-verify their ballot
- The public audit trail records that a ballot was cast, but does not reveal the voter's identity
