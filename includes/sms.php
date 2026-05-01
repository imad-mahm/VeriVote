<?php
declare(strict_types=1);

interface SmsProvider
{
    public function send(string $to, string $message, array $context = []): array;
}

final class EasySendSmsProvider implements SmsProvider
{
    private function buildDestination(string $to): ?string
    {
        $normalized = normalize_phone_number($to);
        if ($normalized === null) {
            return null;
        }

        return ltrim($normalized, '+');
    }

    private function resolveMessageType(string $message): string
    {
        return preg_match('/[^\x00-\x7F]/', $message) ? '1' : '0';
    }

    private function sanitizeSenderId(string $senderId): string
    {
        $senderId = trim($senderId);
        if ($senderId === '') {
            return 'Verivote';
        }

        if (preg_match('/^\+?[0-9]+$/', $senderId)) {
            $hasPlus = str_starts_with($senderId, '+');
            $digits = preg_replace('/\D+/', '', $senderId) ?? '';
            $digits = substr($digits, 0, 15);

            return $hasPlus ? '+' . $digits : $digits;
        }

        $senderId = preg_replace('/[^A-Za-z0-9]/', '', $senderId) ?? '';
        if ($senderId === '') {
            return 'Verivote';
        }

        return substr($senderId, 0, 11);
    }

    public function send(string $to, string $message, array $context = []): array
    {
        $baseUrl = (string) app_config('sms.easy_sendsms.base_url', '');
        $sendPath = (string) app_config('sms.easy_sendsms.send_path', '/v1/rest/sms/send');
        $senderId = (string) app_config('sms.easy_sendsms.sender_id', app_config('sms.sender_id', ''));
        $destination = $this->buildDestination($to);

        if ($baseUrl === '' || $destination === null) {
            return [
                'success' => false,
                'provider_code' => $baseUrl === '' ? 'provider_not_configured' : 'invalid_destination_format',
                'provider_message' => $baseUrl === '' ? 'EasySendSMS base URL is not configured.' : 'Destination must be in international format.',
                'http_status' => 0,
                'raw_response' => null,
            ];
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($sendPath, '/');
        $payload = [
            'from' => $this->sanitizeSenderId($senderId),
            'to' => $destination,
            'text' => $message,
            'type' => $this->resolveMessageType($message),
        ];
        if (isset($context['scheduled_at']) && is_string($context['scheduled_at']) && trim($context['scheduled_at']) !== '') {
            $payload['scheduled'] = trim($context['scheduled_at']);
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $authType = strtolower((string) app_config('sms.easy_sendsms.auth_type', 'apikey'));
        $token = (string) app_config('sms.easy_sendsms.token', '');
        $apiKey = (string) app_config('sms.easy_sendsms.api_key', '');
        $apiSecret = (string) app_config('sms.easy_sendsms.api_secret', '');

        if (($authType === 'apikey' || $authType === 'api_key') && $apiKey !== '') {
            $headers[] = 'apikey: ' . $apiKey;
        } elseif ($authType === 'bearer' && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        } elseif ($authType === 'x_api_key' && $apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
            if ($apiSecret !== '') {
                $headers[] = 'X-API-Secret: ' . $apiSecret;
            }
        } elseif ($authType === 'basic' && $apiKey !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret);
        } else {
            return [
                'success' => false,
                'provider_code' => 'missing_credentials',
                'provider_message' => 'EasySendSMS credentials are missing or invalid for the selected auth_type.',
                'http_status' => 0,
                'raw_response' => null,
            ];
        }

        $timeout = max(3, (int) app_config('sms.timeout_seconds', 15));
        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'success' => false,
                'provider_code' => 'curl_init_failed',
                'provider_message' => 'Could not initialize SMS transport.',
                'http_status' => 0,
                'raw_response' => null,
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'provider_code' => 'curl_error',
                'provider_message' => $error !== '' ? $error : 'Transport error while calling SMS provider.',
                'http_status' => $httpStatus,
                'raw_response' => null,
            ];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $payloadSuccess = $httpStatus >= 200 && $httpStatus < 300;
        $partialSuccess = false;

        if (is_array($decoded)) {
            if (array_key_exists('error', $decoded)) {
                $payloadSuccess = false;
            } elseif (array_key_exists('success', $decoded)) {
                $payloadSuccess = (bool) $decoded['success'] && $payloadSuccess;
            } elseif (strtoupper((string) ($decoded['status'] ?? '')) === 'OK') {
                $messageIds = $decoded['messageIds'] ?? null;
                if (is_array($messageIds) && $messageIds !== []) {
                    $okCount = 0;
                    $errCount = 0;
                    foreach ($messageIds as $item) {
                        $item = (string) $item;
                        if (str_starts_with($item, 'OK:')) {
                            $okCount++;
                        } elseif (str_starts_with($item, 'ERR:')) {
                            $errCount++;
                        }
                    }

                    $payloadSuccess = $payloadSuccess && $okCount > 0;
                    $partialSuccess = $okCount > 0 && $errCount > 0;
                }
            }
        }

        return [
            'success' => $payloadSuccess,
            'partial_success' => $partialSuccess,
            'provider_code' => (string) ($decoded['error'] ?? ($decoded['code'] ?? ($decoded['status'] ?? $httpStatus))),
            'provider_message' => (string) ($decoded['description'] ?? ($decoded['message'] ?? ($payloadSuccess ? 'sent' : 'failed'))),
            'http_status' => $httpStatus,
            'raw_response' => $decoded ?? $raw,
        ];
    }
}

function sms_provider(): SmsProvider
{
    static $provider = null;

    if ($provider instanceof SmsProvider) {
        return $provider;
    }

    $providerName = strtolower((string) app_config('sms.provider', 'easy_sendsms'));
    $provider = match ($providerName) {
        'easy_sendsms' => new EasySendSmsProvider(),
        default => new EasySendSmsProvider(),
    };

    return $provider;
}

function normalize_phone_number(?string $value, ?string $defaultCountryCode = null): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[\s\-\(\)\.]+/', '', $value) ?? '';
    if ($value === '') {
        return null;
    }

    if (str_starts_with($value, '00')) {
        $value = '+' . substr($value, 2);
    }

    $defaultCountryCode = $defaultCountryCode ?: (string) app_config('phone.default_country_code', '961');
    $defaultCountryCode = preg_replace('/\D+/', '', $defaultCountryCode) ?: '961';
    $normalized = '';

    if (str_starts_with($value, '+')) {
        $digits = preg_replace('/\D+/', '', substr($value, 1)) ?? '';
        $normalized = '+' . $digits;
    } else {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, $defaultCountryCode)) {
            $normalized = '+' . $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '+' . $defaultCountryCode . ltrim($digits, '0');
        } elseif (strlen($digits) >= 10) {
            $normalized = '+' . $digits;
        } else {
            $normalized = '+' . $defaultCountryCode . $digits;
        }
    }

    if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized)) {
        return null;
    }

    return $normalized;
}

function phone_is_valid(?string $value): bool
{
    return normalize_phone_number($value) !== null;
}

function sms_template(string $templateKey, array $variables): string
{
    $template = (string) app_config('sms.templates.' . $templateKey, '');
    if ($template === '') {
        return '';
    }

    $replacements = [];
    foreach ($variables as $key => $value) {
        $replacements['{' . $key . '}'] = (string) $value;
    }

    return strtr($template, $replacements);
}

function sms_account_verification_message(string $code): string
{
    return sms_template('account_verification', [
        'code' => $code,
        'minutes' => (string) app_config('security.verification_code_expiry_minutes', 15),
    ]);
}

function sms_event_verification_message(string $code, string $eventTitle): string
{
    return sms_template('event_verification', [
        'code' => $code,
        'event_title' => $eventTitle,
        'minutes' => (string) app_config('security.verification_code_expiry_minutes', 15),
    ]);
}

function sms_token_delivery_message(string $token, string $eventTitle, string $expiresAt): string
{
    return sms_template('token_delivery', [
        'token' => $token,
        'event_title' => $eventTitle,
        'expires_at' => $expiresAt,
    ]);
}

function send_sms_notification(?int $userId, ?int $eventId, string $destination, string $subject, string $body, ?string $deliveryCode = null, array $metadata = []): array
{
    $normalizedDestination = normalize_phone_number($destination);
    $notificationMetadata = array_merge($metadata, [
        'provider' => (string) app_config('sms.provider', 'easy_sendsms'),
        'destination_normalized' => $normalizedDestination,
    ]);

    $notificationId = queue_notification(
        $userId,
        $eventId,
        'sms',
        $normalizedDestination ?? trim($destination),
        $subject,
        $body,
        $deliveryCode,
        $notificationMetadata
    );

    log_activity('sms.queued', [
        'notification_id' => $notificationId,
        'user_id' => $userId,
        'event_id' => $eventId,
        'destination' => $normalizedDestination ?? trim($destination),
        'subject' => $subject,
    ]);

    if ($normalizedDestination === null) {
        execute_statement(
            'UPDATE notifications
             SET status = "failed", metadata_json = :metadata_json
             WHERE id = :id',
            [
                'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                    'provider_code' => 'invalid_phone',
                    'provider_message' => 'Invalid destination phone number.',
                ])),
                'id' => $notificationId,
            ]
        );

        log_activity('sms.invalid_destination', [
            'notification_id' => $notificationId,
            'destination' => trim($destination),
        ], 'WARN');

        return [
            'success' => false,
            'notification_id' => $notificationId,
            'provider_code' => 'invalid_phone',
            'provider_message' => 'Invalid destination phone number.',
        ];
    }

    if (!(bool) app_config('sms.enabled', true)) {
        execute_statement(
            'UPDATE notifications
             SET status = "failed", metadata_json = :metadata_json
             WHERE id = :id',
            [
                'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                    'provider_code' => 'sms_disabled',
                    'provider_message' => 'SMS sending is disabled by configuration.',
                ])),
                'id' => $notificationId,
            ]
        );

        log_activity('sms.disabled', [
            'notification_id' => $notificationId,
            'destination' => $normalizedDestination,
        ], 'WARN');

        return [
            'success' => false,
            'notification_id' => $notificationId,
            'provider_code' => 'sms_disabled',
            'provider_message' => 'SMS sending is disabled by configuration.',
        ];
    }

    $maxAttempts = max(1, (int) app_config('sms.retry_attempts', 3));
    $delayMs = max(0, (int) app_config('sms.retry_delay_ms', 750));
    $result = [
        'success' => false,
        'notification_id' => $notificationId,
        'provider_code' => 'unknown_error',
        'provider_message' => 'SMS sending failed.',
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        log_activity('sms.api_call', [
            'notification_id' => $notificationId,
            'destination' => $normalizedDestination,
            'attempt' => $attempt,
            'provider' => (string) app_config('sms.provider', 'easy_sendsms'),
        ]);

        $providerResult = sms_provider()->send($normalizedDestination, $body, [
            'subject' => $subject,
            'event_id' => $eventId,
            'user_id' => $userId,
            'metadata' => $metadata,
            'attempt' => $attempt,
        ]);

        $result = array_merge($result, $providerResult, [
            'notification_id' => $notificationId,
            'attempt' => $attempt,
        ]);

        error_log('sms_delivery_attempt ' . json_encode([
            'notification_id' => $notificationId,
            'attempt' => $attempt,
            'success' => (bool) ($providerResult['success'] ?? false),
            'provider_code' => $providerResult['provider_code'] ?? null,
            'provider_message' => $providerResult['provider_message'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (!empty($providerResult['success'])) {
            execute_statement(
                'UPDATE notifications
                 SET status = "sent",
                     delivered_at = NOW(),
                     metadata_json = :metadata_json
                 WHERE id = :id',
                [
                    'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                        'attempts' => $attempt,
                        'provider_code' => $providerResult['provider_code'] ?? null,
                        'provider_message' => $providerResult['provider_message'] ?? null,
                        'http_status' => $providerResult['http_status'] ?? null,
                        'raw_response' => $providerResult['raw_response'] ?? null,
                    ])),
                    'id' => $notificationId,
                ]
            );

            if (function_exists('write_audit_log')) {
                write_audit_log('sms_delivery_succeeded', 'notifications', (string) $notificationId, 'SMS delivery succeeded.', $eventId, [
                    'provider_code' => $providerResult['provider_code'] ?? null,
                    'attempts' => $attempt,
                ]);
            }

            log_activity('sms.sent', [
                'notification_id' => $notificationId,
                'destination' => $normalizedDestination,
                'attempts' => $attempt,
                'provider_code' => $providerResult['provider_code'] ?? null,
                'provider_message' => $providerResult['provider_message'] ?? null,
                'http_status' => $providerResult['http_status'] ?? null,
            ]);

            return $result;
        }

        log_activity('sms.attempt_failed', [
            'notification_id' => $notificationId,
            'attempt' => $attempt,
            'provider_code' => $providerResult['provider_code'] ?? null,
            'provider_message' => $providerResult['provider_message'] ?? null,
            'http_status' => $providerResult['http_status'] ?? null,
        ], 'WARN');

        if ($attempt < $maxAttempts && $delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    execute_statement(
        'UPDATE notifications
         SET status = "failed", metadata_json = :metadata_json
         WHERE id = :id',
        [
            'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                'attempts' => $maxAttempts,
                'provider_code' => $result['provider_code'] ?? null,
                'provider_message' => $result['provider_message'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'raw_response' => $result['raw_response'] ?? null,
            ])),
            'id' => $notificationId,
        ]
    );

    if (function_exists('write_audit_log')) {
        write_audit_log('sms_delivery_failed', 'notifications', (string) $notificationId, 'SMS delivery failed.', $eventId, [
            'provider_code' => $result['provider_code'] ?? null,
            'attempts' => $maxAttempts,
        ]);
    }

    log_activity('sms.failed', [
        'notification_id' => $notificationId,
        'destination' => $normalizedDestination,
        'attempts' => $maxAttempts,
        'provider_code' => $result['provider_code'] ?? null,
        'provider_message' => $result['provider_message'] ?? null,
        'http_status' => $result['http_status'] ?? null,
    ], 'ERROR');

    return $result;
}
