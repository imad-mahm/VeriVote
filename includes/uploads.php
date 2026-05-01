<?php
declare(strict_types=1);

function ensure_upload_directories(): void
{
    $baseDir = app_config('uploads.base_dir');
    $directories = [
        $baseDir,
        $baseDir . '/documents',
        $baseDir . '/photos',
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}

function store_uploaded_file(array $file, string $bucket = 'documents', ?array $allowedMimes = null): array
{
    $allowedMimes = $allowedMimes ?: app_config('uploads.allowed_mimes', []);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    if (($file['size'] ?? 0) > (int) app_config('uploads.max_file_size')) {
        throw new RuntimeException('Uploaded file is too large.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        default => 'bin',
    };

    $relativeDir = 'uploads/' . trim($bucket, '/');
    $absoluteDir = BASE_PATH . '/' . $relativeDir;

    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(18)) . '.' . $extension;
    $target = $absoluteDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Could not store uploaded file.');
    }

    return [
        'path' => $relativeDir . '/' . $filename,
        'original_name' => basename((string) ($file['name'] ?? 'upload')),
        'mime' => $mime,
    ];
}
