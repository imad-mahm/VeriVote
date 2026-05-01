<?php
declare(strict_types=1);

function write_audit_log(string $actionType, string $targetTable, string $targetId, string $description, ?int $eventId = null, array $metadata = []): void
{
    $last = fetch_one('SELECT entry_hash FROM audit_logs ORDER BY id DESC LIMIT 1');
    $previousHash = $last['entry_hash'] ?? null;
    $user = current_user();

    $payload = [
        'actor_user_id' => $user['id'] ?? null,
        'event_id' => $eventId,
        'action_type' => $actionType,
        'target_table' => $targetTable,
        'target_id' => $targetId,
        'description' => $description,
        'metadata' => $metadata,
        'ip_address' => client_ip(),
        'user_agent' => user_agent_string(),
        'previous_hash' => $previousHash,
        'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    ];

    $entryHash = secure_digest('audit:' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    execute_statement(
        'INSERT INTO audit_logs (
             actor_user_id, event_id, action_type, target_table, target_id, description, metadata_json,
             ip_address, user_agent, previous_hash, entry_hash
         ) VALUES (
             :actor_user_id, :event_id, :action_type, :target_table, :target_id, :description, :metadata_json,
             :ip_address, :user_agent, :previous_hash, :entry_hash
         )',
        [
            'actor_user_id' => $user['id'] ?? null,
            'event_id' => $eventId,
            'action_type' => $actionType,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'description' => $description,
            'metadata_json' => json_or_null($metadata),
            'ip_address' => client_ip(),
            'user_agent' => user_agent_string(),
            'previous_hash' => $previousHash,
            'entry_hash' => $entryHash,
        ]
    );
}
