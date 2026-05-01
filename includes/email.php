<?php
declare(strict_types=1);

interface EmailProvider
{
    public function send(string $to, string $subject, string $body, array $context = []): array;
}

final class GmailSmtpProvider implements EmailProvider
{
    public function send(string $to, string $subject, string $body, array $context = []): array
    {
        $host = (string) app_config('email.gmail_smtp.host', 'smtp.gmail.com');
        $port = (int) app_config('email.gmail_smtp.port', 587);
        $username = (string) app_config('email.gmail_smtp.username', '');
        $password = (string) app_config('email.gmail_smtp.password', '');
        $encryption = strtolower((string) app_config('email.gmail_smtp.encryption', 'starttls'));
        $fromEmail = (string) app_config('email.gmail_smtp.from_email', '');
        $fromName = (string) app_config('email.gmail_smtp.from_name', 'Verivote');
        $replyTo = (string) app_config('email.gmail_smtp.reply_to', '');
        $timeout = max(3, (int) app_config('email.timeout_seconds', 15));

        if ($host === '' || $port <= 0) {
            return $this->failure('provider_not_configured', 'SMTP host or port is not configured.');
        }
        if ($username === '' || $password === '') {
            return $this->failure('missing_credentials', 'SMTP username or password is missing.');
        }
        if ($fromEmail === '') {
            $fromEmail = $username;
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('invalid_from_address', 'SMTP from address is invalid.');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('invalid_destination', 'Recipient email address is invalid.');
        }

        $scheme = ($encryption === 'tls' || $encryption === 'ssl' || $port === 465) ? 'ssl' : 'tcp';
        $dsn = $scheme . '://' . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($dsn, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            return $this->failure(
                'connect_failed',
                'Unable to connect to SMTP server: ' . ($errstr !== '' ? $errstr : 'unknown error') . ' (#' . $errno . ')'
            );
        }
        stream_set_timeout($socket, $timeout);

        $log = [];
        try {
            $greeting = $this->readResponse($socket, $log);
            if ($greeting['code'] !== 220) {
                return $this->failure('bad_greeting', $greeting['message'], $log);
            }

            $ehloHost = $this->resolveEhloHost();
            $this->writeCommand($socket, 'EHLO ' . $ehloHost, $log);
            $ehlo = $this->readResponse($socket, $log);
            if ($ehlo['code'] !== 250) {
                return $this->failure('ehlo_failed', $ehlo['message'], $log);
            }

            if ($scheme === 'tcp' && $encryption !== 'none') {
                $this->writeCommand($socket, 'STARTTLS', $log);
                $tls = $this->readResponse($socket, $log);
                if ($tls['code'] !== 220) {
                    return $this->failure('starttls_refused', $tls['message'], $log);
                }
                $cryptoMethods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethods);
                if ($cryptoOk !== true) {
                    return $this->failure('tls_handshake_failed', 'Could not negotiate TLS with SMTP server.', $log);
                }
                $this->writeCommand($socket, 'EHLO ' . $ehloHost, $log);
                $ehlo2 = $this->readResponse($socket, $log);
                if ($ehlo2['code'] !== 250) {
                    return $this->failure('ehlo_after_tls_failed', $ehlo2['message'], $log);
                }
            }

            $this->writeCommand($socket, 'AUTH LOGIN', $log);
            $authStart = $this->readResponse($socket, $log);
            if ($authStart['code'] !== 334) {
                return $this->failure('auth_not_supported', $authStart['message'], $log);
            }
            $this->writeCommand($socket, base64_encode($username), $log, true);
            $userReply = $this->readResponse($socket, $log);
            if ($userReply['code'] !== 334) {
                return $this->failure('auth_username_rejected', $userReply['message'], $log);
            }
            $this->writeCommand($socket, base64_encode($password), $log, true);
            $authReply = $this->readResponse($socket, $log);
            if ($authReply['code'] !== 235) {
                return $this->failure('auth_failed', $authReply['message'], $log);
            }

            $this->writeCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', $log);
            $mailFrom = $this->readResponse($socket, $log);
            if ($mailFrom['code'] !== 250) {
                return $this->failure('mail_from_rejected', $mailFrom['message'], $log);
            }

            $this->writeCommand($socket, 'RCPT TO:<' . $to . '>', $log);
            $rcptTo = $this->readResponse($socket, $log);
            if ($rcptTo['code'] !== 250 && $rcptTo['code'] !== 251) {
                return $this->failure('rcpt_to_rejected', $rcptTo['message'], $log);
            }

            $this->writeCommand($socket, 'DATA', $log);
            $dataReady = $this->readResponse($socket, $log);
            if ($dataReady['code'] !== 354) {
                return $this->failure('data_refused', $dataReady['message'], $log);
            }

            $payload = $this->buildMessage($fromEmail, $fromName, $to, $subject, $body, $replyTo, $context);
            fwrite($socket, $payload . "\r\n.\r\n");
            $dataDone = $this->readResponse($socket, $log);
            if ($dataDone['code'] !== 250) {
                return $this->failure('message_rejected', $dataDone['message'], $log);
            }

            @$this->writeCommand($socket, 'QUIT', $log);
            @$this->readResponse($socket, $log);

            return [
                'success' => true,
                'provider_code' => (string) $dataDone['code'],
                'provider_message' => $dataDone['message'],
                'http_status' => 0,
                'raw_response' => $log,
            ];
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    private function resolveEhloHost(): string
    {
        $appUrl = (string) app_config('app_url', '');
        if ($appUrl !== '') {
            $parsed = parse_url($appUrl);
            if (is_array($parsed) && !empty($parsed['host'])) {
                return (string) $parsed['host'];
            }
        }
        $server = (string) ($_SERVER['SERVER_NAME'] ?? '');
        if ($server !== '') {
            return $server;
        }
        $hostname = gethostname();
        return $hostname !== false ? $hostname : 'localhost';
    }

    private function writeCommand($socket, string $command, array &$log, bool $sensitive = false): void
    {
        fwrite($socket, $command . "\r\n");
        $log[] = '> ' . ($sensitive ? '***' : $command);
    }

    private function readResponse($socket, array &$log): array
    {
        $lines = [];
        $code = 0;
        while (!feof($socket)) {
            $line = fgets($socket, 8192);
            if ($line === false) {
                break;
            }
            $log[] = '< ' . rtrim($line, "\r\n");
            $lines[] = rtrim($line, "\r\n");
            if (strlen($line) < 4) {
                break;
            }
            $code = (int) substr($line, 0, 3);
            $separator = substr($line, 3, 1);
            if ($separator !== '-') {
                break;
            }
        }

        return [
            'code' => $code,
            'message' => implode(' | ', $lines),
        ];
    }

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        string $replyTo,
        array $context
    ): string {
        $fromHeader = $fromName !== ''
            ? $this->encodeHeader($fromName) . ' <' . $fromEmail . '>'
            : $fromEmail;

        $headers = [
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->resolveEhloHost() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Verivote',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        if (!empty($context['reply_to']) && is_string($context['reply_to']) && filter_var($context['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $context['reply_to'] . '>';
        }

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);
        $dotStuffed = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;

        return implode("\r\n", $headers) . "\r\n\r\n" . $dotStuffed;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function failure(string $code, string $message, array $log = []): array
    {
        return [
            'success' => false,
            'provider_code' => $code,
            'provider_message' => $message,
            'http_status' => 0,
            'raw_response' => $log,
        ];
    }
}

function email_provider(): EmailProvider
{
    static $provider = null;

    if ($provider instanceof EmailProvider) {
        return $provider;
    }

    $providerName = strtolower((string) app_config('email.provider', 'gmail_smtp'));
    $provider = match ($providerName) {
        'gmail_smtp' => new GmailSmtpProvider(),
        default => new GmailSmtpProvider(),
    };

    return $provider;
}

function send_email_notification(?int $userId, ?int $eventId, string $destination, string $subject, string $body, ?string $deliveryCode = null, array $metadata = []): array
{
    $destination = trim($destination);
    $notificationMetadata = array_merge($metadata, [
        'provider' => (string) app_config('email.provider', 'gmail_smtp'),
    ]);

    $notificationId = queue_notification(
        $userId,
        $eventId,
        'email',
        $destination,
        $subject,
        $body,
        $deliveryCode,
        $notificationMetadata
    );

    log_activity('email.queued', [
        'notification_id' => $notificationId,
        'user_id' => $userId,
        'event_id' => $eventId,
        'destination' => $destination,
        'subject' => $subject,
    ]);

    if ($destination === '' || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
        execute_statement(
            'UPDATE notifications
             SET status = "failed", metadata_json = :metadata_json
             WHERE id = :id',
            [
                'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                    'provider_code' => 'invalid_email',
                    'provider_message' => 'Invalid destination email address.',
                ])),
                'id' => $notificationId,
            ]
        );

        log_activity('email.invalid_destination', [
            'notification_id' => $notificationId,
            'destination' => $destination,
        ], 'WARN');

        return [
            'success' => false,
            'notification_id' => $notificationId,
            'provider_code' => 'invalid_email',
            'provider_message' => 'Invalid destination email address.',
        ];
    }

    if (!(bool) app_config('email.enabled', false)) {
        execute_statement(
            'UPDATE notifications
             SET status = "failed", metadata_json = :metadata_json
             WHERE id = :id',
            [
                'metadata_json' => json_or_null(array_merge($notificationMetadata, [
                    'provider_code' => 'email_disabled',
                    'provider_message' => 'Email sending is disabled by configuration.',
                ])),
                'id' => $notificationId,
            ]
        );

        log_activity('email.disabled', [
            'notification_id' => $notificationId,
            'destination' => $destination,
        ], 'WARN');

        return [
            'success' => false,
            'notification_id' => $notificationId,
            'provider_code' => 'email_disabled',
            'provider_message' => 'Email sending is disabled by configuration.',
        ];
    }

    $maxAttempts = max(1, (int) app_config('email.retry_attempts', 2));
    $delayMs = max(0, (int) app_config('email.retry_delay_ms', 750));
    $result = [
        'success' => false,
        'notification_id' => $notificationId,
        'provider_code' => 'unknown_error',
        'provider_message' => 'Email sending failed.',
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        log_activity('email.smtp_call', [
            'notification_id' => $notificationId,
            'destination' => $destination,
            'attempt' => $attempt,
            'provider' => (string) app_config('email.provider', 'gmail_smtp'),
        ]);

        $providerResult = email_provider()->send($destination, $subject, $body, [
            'event_id' => $eventId,
            'user_id' => $userId,
            'metadata' => $metadata,
            'attempt' => $attempt,
        ]);

        $result = array_merge($result, $providerResult, [
            'notification_id' => $notificationId,
            'attempt' => $attempt,
        ]);

        error_log('email_delivery_attempt ' . json_encode([
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
                    ])),
                    'id' => $notificationId,
                ]
            );

            if (function_exists('write_audit_log')) {
                write_audit_log('email_delivery_succeeded', 'notifications', (string) $notificationId, 'Email delivery succeeded.', $eventId, [
                    'provider_code' => $providerResult['provider_code'] ?? null,
                    'attempts' => $attempt,
                ]);
            }

            log_activity('email.sent', [
                'notification_id' => $notificationId,
                'destination' => $destination,
                'attempts' => $attempt,
                'provider_code' => $providerResult['provider_code'] ?? null,
                'provider_message' => $providerResult['provider_message'] ?? null,
            ]);

            return $result;
        }

        log_activity('email.attempt_failed', [
            'notification_id' => $notificationId,
            'attempt' => $attempt,
            'provider_code' => $providerResult['provider_code'] ?? null,
            'provider_message' => $providerResult['provider_message'] ?? null,
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
            ])),
            'id' => $notificationId,
        ]
    );

    if (function_exists('write_audit_log')) {
        write_audit_log('email_delivery_failed', 'notifications', (string) $notificationId, 'Email delivery failed.', $eventId, [
            'provider_code' => $result['provider_code'] ?? null,
            'attempts' => $maxAttempts,
        ]);
    }

    log_activity('email.failed', [
        'notification_id' => $notificationId,
        'destination' => $destination,
        'attempts' => $maxAttempts,
        'provider_code' => $result['provider_code'] ?? null,
        'provider_message' => $result['provider_message'] ?? null,
    ], 'ERROR');

    return $result;
}
