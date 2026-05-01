CREATE DATABASE IF NOT EXISTS verivote CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verivote;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS result_snapshots;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS ballots;
DROP TABLE IF EXISTS voting_tokens;
DROP TABLE IF EXISTS voter_verifications;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS verification_methods;
DROP TABLE IF EXISTS voter_submission_answers;
DROP TABLE IF EXISTS voter_event_submissions;
DROP TABLE IF EXISTS event_required_fields;
DROP TABLE IF EXISTS candidates_or_options;
DROP TABLE IF EXISTS verifiers;
DROP TABLE IF EXISTS co_admins;
DROP TABLE IF EXISTS event_admins;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS voter_profiles;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(40) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    phone_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    ballot_type ENUM('single_choice') NOT NULL DEFAULT 'single_choice',
    status ENUM('draft', 'scheduled', 'active', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
    result_visibility ENUM('private', 'public_after_close', 'public_live') NOT NULL DEFAULT 'public_after_close',
    personal_verification_enabled TINYINT(1) NOT NULL DEFAULT 1,
    public_audit_enabled TINYINT(1) NOT NULL DEFAULT 1,
    allow_self_registration TINYINT(1) NOT NULL DEFAULT 1,
    max_votes_per_token TINYINT UNSIGNED NOT NULL DEFAULT 1,
    verification_policy ENUM('all_required', 'any_one', 'custom') NOT NULL DEFAULT 'all_required',
    eligibility_rules TEXT NULL,
    event_notice TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE voter_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    date_of_birth DATE NULL,
    address_line VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    national_id_number VARCHAR(120) NULL,
    passport_number VARCHAR(120) NULL,
    profile_photo_path VARCHAR(255) NULL,
    government_id_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE event_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assignment_type ENUM('owner', 'event_admin') NOT NULL DEFAULT 'owner',
    permissions_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_admin (event_id, user_id),
    CONSTRAINT fk_event_admins_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE co_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    permissions_json JSON NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_co_admin (event_id, user_id),
    CONSTRAINT fk_coadmins_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_coadmins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_coadmins_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE verifiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    verifier_type ENUM('mo5tar', 'manual', 'in_person') NOT NULL DEFAULT 'mo5tar',
    assigned_by BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_verifier (event_id, user_id),
    CONSTRAINT fk_verifiers_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_verifiers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_verifiers_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE candidates_or_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    option_label VARCHAR(190) NOT NULL,
    option_description TEXT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_candidates_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE event_required_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(120) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type ENUM('text', 'email', 'phone', 'date', 'textarea', 'select', 'file', 'image', 'id_number', 'passport', 'address') NOT NULL DEFAULT 'text',
    placeholder VARCHAR(190) NULL,
    help_text VARCHAR(255) NULL,
    options_json JSON NULL,
    validation_rules_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_system_field TINYINT(1) NOT NULL DEFAULT 1,
    field_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_field (event_id, field_key),
    CONSTRAINT fk_required_fields_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE voter_event_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    submission_reference VARCHAR(36) NOT NULL UNIQUE,
    status ENUM('pending', 'under_review', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approval_notes TEXT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    last_reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_submission (event_id, user_id),
    CONSTRAINT fk_submissions_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE voter_submission_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NULL,
    field_key VARCHAR(120) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type VARCHAR(40) NOT NULL,
    text_value TEXT NULL,
    file_path VARCHAR(255) NULL,
    original_filename VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_answers_submission FOREIGN KEY (submission_id) REFERENCES voter_event_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_answers_field FOREIGN KEY (field_id) REFERENCES event_required_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE verification_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    method_key VARCHAR(80) NOT NULL,
    label VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    requires_reviewer TINYINT(1) NOT NULL DEFAULT 0,
    sequence_order INT UNSIGNED NOT NULL DEFAULT 1,
    config_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_method (event_id, method_key),
    CONSTRAINT fk_verification_methods_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE voter_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    verification_method_id BIGINT UNSIGNED NOT NULL,
    is_required_snapshot TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'waived') NOT NULL DEFAULT 'pending',
    verifier_user_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_submission_method (submission_id, verification_method_id),
    CONSTRAINT fk_voter_verifications_submission FOREIGN KEY (submission_id) REFERENCES voter_event_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_voter_verifications_method FOREIGN KEY (verification_method_id) REFERENCES verification_methods(id) ON DELETE CASCADE,
    CONSTRAINT fk_voter_verifications_user FOREIGN KEY (verifier_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE verification_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    submission_id BIGINT UNSIGNED NULL,
    verification_id BIGINT UNSIGNED NULL,
    purpose ENUM('account_email', 'account_phone', 'event_email', 'event_sms') NOT NULL,
    destination VARCHAR(190) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_codes_submission FOREIGN KEY (submission_id) REFERENCES voter_event_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_codes_verification FOREIGN KEY (verification_id) REFERENCES voter_verifications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE voting_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    issued_by BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_reference VARCHAR(32) NOT NULL UNIQUE,
    token_last4 CHAR(4) NOT NULL,
    anonymous_ballot_key CHAR(64) NOT NULL,
    public_token_hash CHAR(64) NOT NULL,
    delivery_channel ENUM('portal', 'email', 'sms', 'print') NOT NULL DEFAULT 'portal',
    status ENUM('issued', 'used', 'revoked', 'expired') NOT NULL DEFAULT 'issued',
    expires_at DATETIME NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoked_by BIGINT UNSIGNED NULL,
    usage_ip_address VARCHAR(64) NULL,
    usage_user_agent VARCHAR(255) NULL,
    KEY idx_token_hash (token_hash),
    KEY idx_token_event_submission (event_id, submission_id),
    CONSTRAINT fk_tokens_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_tokens_submission FOREIGN KEY (submission_id) REFERENCES voter_event_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_tokens_issuer FOREIGN KEY (issued_by) REFERENCES users(id),
    CONSTRAINT fk_tokens_revoker FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ballots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    candidate_option_id BIGINT UNSIGNED NOT NULL,
    anonymous_ballot_key CHAR(64) NOT NULL,
    option_snapshot VARCHAR(190) NOT NULL,
    receipt_hash CHAR(64) NOT NULL UNIQUE,
    public_receipt_hash CHAR(64) NOT NULL UNIQUE,
    ballot_hash CHAR(64) NOT NULL UNIQUE,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cast_ip_address VARCHAR(64) NULL,
    cast_user_agent VARCHAR(255) NULL,
    UNIQUE KEY uniq_ballot_key (event_id, anonymous_ballot_key),
    CONSTRAINT fk_ballots_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_ballots_candidate FOREIGN KEY (candidate_option_id) REFERENCES candidates_or_options(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    event_id BIGINT UNSIGNED NULL,
    channel ENUM('email', 'sms', 'system') NOT NULL DEFAULT 'system',
    destination VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    delivery_code VARCHAR(50) NULL,
    metadata_json JSON NULL,
    status ENUM('queued', 'sent', 'failed', 'read') NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    event_id BIGINT UNSIGNED NULL,
    action_type VARCHAR(100) NOT NULL,
    target_table VARCHAR(100) NOT NULL,
    target_id VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    previous_hash CHAR(64) NULL,
    entry_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_event (event_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE result_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    generated_by BIGINT UNSIGNED NOT NULL,
    snapshot_type ENUM('manual', 'scheduled', 'closure') NOT NULL DEFAULT 'manual',
    total_ballots INT UNSIGNED NOT NULL DEFAULT 0,
    snapshot_json JSON NOT NULL,
    integrity_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_snapshots_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_snapshots_user FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(80) NOT NULL,
    subject_key VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rate_limit (scope_key, subject_key)
) ENGINE=InnoDB;
