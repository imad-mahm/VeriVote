CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean') NOT NULL DEFAULT 'string',
    category VARCHAR(80) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, category) VALUES
-- Password policy
('password_min_length',             '10',        'integer', 'password'),
('password_require_uppercase',      '1',         'boolean', 'password'),
('password_require_lowercase',      '1',         'boolean', 'password'),
('password_require_numbers',        '1',         'boolean', 'password'),
('password_require_special',        '0',         'boolean', 'password'),
('bypass_password_validation',      '0',         'boolean', 'password'),

-- Verification
('account_verification_method',         'sms',   'string',  'verification'),
('verification_code_expiry_minutes',    '15',    'integer', 'verification'),
('max_verification_code_attempts',      '6',     'integer', 'verification'),

-- Security / rate-limiting
('max_login_attempts',              '6',         'integer', 'security'),
('login_lockout_window_minutes',    '15',        'integer', 'security'),
('password_reset_expiry_minutes',   '30',        'integer', 'security'),

-- Platform
('platform_name',                   'Verivote',  'string',  'platform'),
('default_country_code',            '961',       'string',  'platform'),
('allow_voter_self_registration',   '1',         'boolean', 'platform'),
('maintenance_mode',                '0',         'boolean', 'platform');
