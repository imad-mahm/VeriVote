USE verivote;

INSERT INTO roles (id, name, slug, description) VALUES
    (1, 'Super Admin', 'super_admin', 'Platform owner with global oversight'),
    (2, 'Event Creator', 'event_creator', 'Creates and manages voting events'),
    (3, 'Co-Admin', 'co_admin', 'Limited event management access'),
    (4, 'Trusted Verifier', 'verifier', 'Mo5tar or in-person verifier'),
    (5, 'Voter', 'voter', 'Standard voter account')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT INTO users (
    id, role_id, full_name, email, phone, password_hash, status, email_verified_at, phone_verified_at, created_at, updated_at
) VALUES
    (1, 1, 'Platform Super Admin', 'superadmin@verivote.test', '+12025550100', '$2y$10$4HcjldA8G/lEDYlmdglAwehuaYtJzik9g8cVFv8V4AiW4Hi8/xHOG', 'active', '2026-04-01 08:00:00', '2026-04-01 08:00:00', NOW(), NOW()),
    (2, 2, 'Election Creator', 'creator@verivote.test', '+12025550101', '$2y$10$4HcjldA8G/lEDYlmdglAwehuaYtJzik9g8cVFv8V4AiW4Hi8/xHOG', 'active', '2026-04-01 08:00:00', '2026-04-01 08:00:00', NOW(), NOW()),
    (3, 3, 'Operations Co-Admin', 'coadmin@verivote.test', '+12025550102', '$2y$10$4HcjldA8G/lEDYlmdglAwehuaYtJzik9g8cVFv8V4AiW4Hi8/xHOG', 'active', '2026-04-01 08:00:00', '2026-04-01 08:00:00', NOW(), NOW()),
    (4, 4, 'Trusted Verifier', 'verifier@verivote.test', '+12025550103', '$2y$10$4HcjldA8G/lEDYlmdglAwehuaYtJzik9g8cVFv8V4AiW4Hi8/xHOG', 'active', '2026-04-01 08:00:00', '2026-04-01 08:00:00', NOW(), NOW()),
    (5, 5, 'Demo Voter', 'voter@verivote.test', '+12025550104', '$2y$10$uUN5ke4UeLRedkvCJLnM0O0py8GnLUDLOyKrKesxNScGfrhAi86DG', 'active', '2026-04-01 08:00:00', '2026-04-01 08:00:00', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role_id = VALUES(role_id),
    phone = VALUES(phone),
    status = VALUES(status),
    email_verified_at = VALUES(email_verified_at),
    phone_verified_at = VALUES(phone_verified_at);

INSERT INTO voter_profiles (
    user_id, date_of_birth, address_line, city, country, national_id_number, passport_number
) VALUES
    (5, '1995-05-12', '44 Cedar Avenue', 'Beirut', 'Lebanon', 'LB-20199455', 'P-449921')
ON DUPLICATE KEY UPDATE
    date_of_birth = VALUES(date_of_birth),
    address_line = VALUES(address_line),
    city = VALUES(city),
    country = VALUES(country),
    national_id_number = VALUES(national_id_number),
    passport_number = VALUES(passport_number);

INSERT INTO events (
    id, created_by, title, slug, description, ballot_type, status, start_at, end_at, timezone,
    result_visibility, personal_verification_enabled, public_audit_enabled, allow_self_registration,
    max_votes_per_token, verification_policy, eligibility_rules, event_notice, created_at, updated_at
) VALUES
    (
        1, 2, '2026 Global Technology Council Election', '2026-global-technology-council-election',
        'Elect one representative for the Verivote demonstration council using a verifiable single-choice ballot.',
        'single_choice', 'active', '2026-04-15 08:00:00', '2026-04-28 20:00:00', 'UTC',
        'public_live', 1, 1, 1, 1, 'all_required',
        'Applicants must provide identity data matching their registration profile and complete all required verification methods.',
        'Personal vote receipts are available after ballot submission. Public audit hashes refresh after each ballot.',
        NOW(), NOW()
    ),
    (
        2, 2, '2026 Civic Oversight Board Referendum', '2026-civic-oversight-board-referendum',
        'Closed demonstration event with published result snapshot and public audit artifacts.',
        'single_choice', 'closed', '2026-03-01 09:00:00', '2026-03-03 21:00:00', 'UTC',
        'public_after_close', 1, 1, 0, 1, 'all_required',
        'Closed sample event used for public result verification.',
        'This event is retained for analytics and audit demonstrations.',
        NOW(), NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    status = VALUES(status),
    start_at = VALUES(start_at),
    end_at = VALUES(end_at),
    result_visibility = VALUES(result_visibility),
    event_notice = VALUES(event_notice);

INSERT INTO event_admins (event_id, user_id, assignment_type, permissions_json) VALUES
    (1, 2, 'owner', JSON_OBJECT('manage_event', true, 'manage_candidates', true, 'manage_fields', true, 'review_verifications', true, 'issue_tokens', true, 'view_results', true)),
    (2, 2, 'owner', JSON_OBJECT('manage_event', true, 'manage_candidates', true, 'manage_fields', true, 'review_verifications', true, 'issue_tokens', true, 'view_results', true))
ON DUPLICATE KEY UPDATE
    assignment_type = VALUES(assignment_type),
    permissions_json = VALUES(permissions_json);

INSERT INTO co_admins (event_id, user_id, assigned_by, permissions_json, is_active) VALUES
    (1, 3, 2, JSON_OBJECT('review_verifications', true, 'manage_candidates', false, 'issue_tokens', true, 'view_results', true), 1)
ON DUPLICATE KEY UPDATE
    permissions_json = VALUES(permissions_json),
    is_active = VALUES(is_active);

INSERT INTO verifiers (event_id, user_id, verifier_type, assigned_by, is_active) VALUES
    (1, 4, 'mo5tar', 2, 1)
ON DUPLICATE KEY UPDATE
    verifier_type = VALUES(verifier_type),
    is_active = VALUES(is_active);

INSERT INTO candidates_or_options (id, event_id, option_label, option_description, display_order, is_active) VALUES
    (1, 1, 'Amina Rahal', 'Independent candidate focused on open civic infrastructure.', 1, 1),
    (2, 1, 'Daniel Noor', 'Security policy candidate prioritising public verification standards.', 2, 1),
    (3, 1, 'Lina Haddad', 'Governance candidate focused on turnout integrity and auditability.', 3, 1),
    (4, 2, 'Approve Referendum', 'Approve the oversight board charter.', 1, 1),
    (5, 2, 'Reject Referendum', 'Reject the oversight board charter.', 2, 1)
ON DUPLICATE KEY UPDATE
    option_label = VALUES(option_label),
    option_description = VALUES(option_description),
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);

INSERT INTO event_required_fields (
    event_id, field_key, field_label, field_type, placeholder, help_text, options_json,
    validation_rules_json, is_required, is_system_field, field_order
) VALUES
    (1, 'full_name', 'Full Name', 'text', 'Enter your legal full name', 'Must match your identity document.', NULL, JSON_OBJECT('min_length', 3), 1, 1, 1),
    (1, 'email', 'Email Address', 'email', 'name@example.com', 'Used for account and event verification.', NULL, JSON_OBJECT('format', 'email'), 1, 1, 2),
    (1, 'phone', 'Phone Number', 'phone', '+12025550199', 'Used for SMS verification and token delivery.', NULL, JSON_OBJECT('format', 'phone'), 1, 1, 3),
    (1, 'date_of_birth', 'Date of Birth', 'date', NULL, 'Used for eligibility checks.', NULL, NULL, 1, 1, 4),
    (1, 'passport_number', 'Passport Number', 'passport', 'Enter passport number', 'Stored privately and reviewed by event admins.', NULL, JSON_OBJECT('max_length', 40), 1, 1, 5),
    (1, 'id_document', 'Identity Document Upload', 'file', NULL, 'Upload a PNG, JPG, or PDF.', NULL, JSON_OBJECT('mime', JSON_ARRAY('image/jpeg', 'image/png', 'application/pdf')), 1, 0, 6),
    (1, 'residency_note', 'Residency Note', 'textarea', 'Optional context for regional eligibility', 'Optional free-text context for reviewers.', NULL, JSON_OBJECT('max_length', 500), 0, 0, 7)
ON DUPLICATE KEY UPDATE
    field_label = VALUES(field_label),
    field_type = VALUES(field_type),
    placeholder = VALUES(placeholder),
    help_text = VALUES(help_text),
    validation_rules_json = VALUES(validation_rules_json),
    is_required = VALUES(is_required),
    field_order = VALUES(field_order);

INSERT INTO verification_methods (
    event_id, method_key, label, description, is_required, requires_reviewer, sequence_order, config_json, is_active
) VALUES
    (1, 'sms_verification', 'SMS Verification', 'A one-time SMS code confirms phone ownership.', 1, 0, 1, JSON_OBJECT('expiry_minutes', 15), 1),
    (1, 'document_review', 'Document Review', 'An event reviewer validates the uploaded document.', 1, 1, 2, JSON_OBJECT('allowed_roles', JSON_ARRAY('event_creator', 'co_admin')), 1),
    (1, 'trusted_verifier', 'Trusted Verifier Approval', 'A Mo5tar or in-person verifier confirms the voter physically.', 1, 1, 3, JSON_OBJECT('allowed_roles', JSON_ARRAY('verifier')), 1),
    (1, 'manual_review', 'Manual Admin Review', 'Final manual approval before token issuance.', 1, 1, 4, JSON_OBJECT('allowed_roles', JSON_ARRAY('event_creator', 'co_admin')), 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    is_required = VALUES(is_required),
    requires_reviewer = VALUES(requires_reviewer),
    sequence_order = VALUES(sequence_order),
    config_json = VALUES(config_json),
    is_active = VALUES(is_active);

-- No pre-existing submission for voter@verivote.test on Event 1.
-- This ensures the registration form (with document upload) is visible for demo purposes.
-- During a live demo: log in as voter@verivote.test → open Event 1 → fill the form → upload a document.
-- Then switch to creator@verivote.test → Verifications → review the uploaded document.

INSERT INTO audit_logs (
    actor_user_id, event_id, action_type, target_table, target_id, description, metadata_json, ip_address, user_agent, previous_hash, entry_hash
) VALUES
    (2, 1, 'event_created', 'events', '1', 'Creator opened a new active election event.', JSON_OBJECT('status', 'active'), '127.0.0.1', 'seed', NULL, SHA2('seed-event-1', 256)),
    (3, 1, 'document_reviewed', 'voter_event_submissions', '1', 'Co-admin approved document review.', JSON_OBJECT('submission_reference', 'SUB-VERI-2026-0001'), '127.0.0.1', 'seed', SHA2('seed-event-1', 256), SHA2('seed-doc-review-1', 256));

INSERT INTO ballots (
    event_id, candidate_option_id, anonymous_ballot_key, option_snapshot, receipt_hash, public_receipt_hash, ballot_hash, submitted_at, cast_ip_address, cast_user_agent
) VALUES
    (2, 4, SHA2('anon-1', 256), 'Approve Referendum', SHA2('receipt-1-private', 256), SHA2('receipt-1-public', 256), SHA2('ballot-1', 256), '2026-03-02 10:01:00', '127.0.0.1', 'seed'),
    (2, 4, SHA2('anon-2', 256), 'Approve Referendum', SHA2('receipt-2-private', 256), SHA2('receipt-2-public', 256), SHA2('ballot-2', 256), '2026-03-02 10:16:00', '127.0.0.1', 'seed'),
    (2, 5, SHA2('anon-3', 256), 'Reject Referendum', SHA2('receipt-3-private', 256), SHA2('receipt-3-public', 256), SHA2('ballot-3', 256), '2026-03-02 10:28:00', '127.0.0.1', 'seed');

INSERT INTO result_snapshots (
    event_id, generated_by, snapshot_type, total_ballots, snapshot_json, integrity_hash
) VALUES
    (
        2, 2, 'closure', 3,
        JSON_OBJECT(
            'Approve Referendum', JSON_OBJECT('votes', 2, 'percentage', 66.67),
            'Reject Referendum', JSON_OBJECT('votes', 1, 'percentage', 33.33)
        ),
        SHA2('snapshot-event-2', 256)
    );

INSERT INTO notifications (
    user_id, event_id, channel, destination, subject, body, delivery_code, metadata_json, status, created_at
) VALUES
    (5, 1, 'sms', '+12025550104', 'SMS verification queued', 'Your Verivote event SMS verification code is queued for delivery in this MVP environment.', '482913', JSON_OBJECT('purpose', 'event_sms'), 'queued', NOW()),
    (5, NULL, 'email', 'voter@verivote.test', 'Account ready', 'Your Verivote account is active in the seed dataset.', NULL, JSON_OBJECT('purpose', 'welcome'), 'sent', NOW());
