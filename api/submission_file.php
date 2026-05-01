<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$answerId = (int) ($_GET['answer'] ?? 0);
$answer = $answerId > 0 ? fetch_submission_answer_by_id($answerId) : null;

if (!$answer || empty($answer['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}

if (!user_can_access_event_evidence((int) $answer['event_id'])) {
    http_response_code(403);
    exit('You do not have permission to access this file.');
}

$absolutePath = secure_upload_absolute_path((string) $answer['file_path']);

if ($absolutePath === null) {
    http_response_code(404);
    exit('Stored file not found.');
}

write_audit_log(
    'submission_file_accessed',
    'voter_submission_answers',
    (string) $answer['id'],
    'Sensitive submission evidence was accessed.',
    (int) $answer['event_id'],
    ['submission_reference' => $answer['submission_reference']]
);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($absolutePath);
$filename = $answer['original_filename'] ?: basename($absolutePath);
$safeFilename = str_replace('"', '', basename($filename));
$encodedFilename = rawurlencode($filename);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($absolutePath));
header('Content-Disposition: inline; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . $encodedFilename);
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
