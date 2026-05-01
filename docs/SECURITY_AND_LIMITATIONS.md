# Verivote Security and Limitations

## Core Security Model
- Identity and ballots are separated in storage.
  - `users`, `voter_profiles`, and `voter_event_submissions` store identity and evidence.
  - `ballots` store anonymous ballot keys, receipt hashes, and selected option snapshots.
- Voting tokens are never stored in plain text.
  - A raw token is shown only at issuance time.
  - The database stores an HMAC digest plus non-sensitive references such as `token_reference` and `token_last4`.
- Receipt verification is split into two views.
  - Private receipt lookup lets a voter confirm their own ballot.
  - Public audit pages expose only privacy-preserving receipt and token hashes.

## Anti-Impersonation Controls
- Event creators can define required fields dynamically per event.
- Event verification methods are configurable from the UI.
- Supported v1 verification families:
  - account phone verification (SMS OTP)
  - email verification
  - SMS verification
  - document review
  - manual review
  - trusted verifier approval
- Trusted verifier decisions require notes and are audit-logged.

## Evidence Handling
- Uploaded files are stored under `uploads/` with randomized filenames.
- Direct public file links are avoided for review surfaces.
- Sensitive submission files are now served through an authenticated endpoint that:
  - checks event-level evidence access
  - verifies the file path remains inside the uploads directory
  - records access in the audit log

## Auditability
- Sensitive privileged actions are written to `audit_logs`.
- Each audit entry includes:
  - actor
  - event
  - target table and record
  - metadata
  - previous hash
  - entry hash
- This is tamper-evident within the application database, but not independently anchored outside the system.

## MVP Limitations
- Email notification delivery is queue-only in the database.
- SMS delivery depends on external EasySendSMS credentials and endpoint compatibility.
- Rejected submissions are resubmitted in place with audit snapshots, not tracked in a dedicated revision table.
- Verification policy `custom` is stored in the schema, but the UI currently behaves like required-step review rather than a full policy builder.
- Public deployment hardening is incomplete:
  - no HTTPS enforcement in-app
  - no background job queue
  - no external audit anchoring
  - no production email sender integration
