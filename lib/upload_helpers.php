<?php
declare(strict_types=1);

/**
 * Save an uploaded image file to a non-public uploads folder.
 * Returns the stored filesystem path (relative to project root) on success.
 * Throws RuntimeException on validation or move failure.
 *
 * @param array|string $file Typically one element from $_FILES (e.g. $_FILES['image'])
 * @return string Stored path (e.g. 'uploads/images/abcd1234.webp')
 * @throws RuntimeException
 */
function saveUploadedImage($file): string
{
    // Basic shape check
    if (!is_array($file) || !isset($file['error'])) {
        throw new RuntimeException('Invalid upload data');
    }

    // Check PHP upload error
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Uploaded file is too large');
        case UPLOAD_ERR_PARTIAL:
            throw new RuntimeException('File was only partially uploaded');
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file was uploaded');
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new RuntimeException('Missing temporary folder');
        case UPLOAD_ERR_CANT_WRITE:
            throw new RuntimeException('Failed to write uploaded file to disk');
        case UPLOAD_ERR_EXTENSION:
        default:
            throw new RuntimeException('File upload error (code: ' . (int)$file['error'] . ')');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file');
    }

    // Use finfo to determine real MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime === false) {
        throw new RuntimeException('Unable to determine file MIME type');
    }

    $ext = null;
    switch ($mime) {
        case 'image/jpeg':
            $ext = 'jpg';
            break;
        case 'image/png':
            $ext = 'png';
            break;
        case 'image/webp':
            $ext = 'webp';
            break;
        default:
            throw new RuntimeException('Unsupported image type: ' . $mime);
    }

    // Prepare non-public uploads directory (outside webroot if possible). Use configured UPLOAD_DIR if defined.
    $baseDir = defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/') : 'uploads/images';
    // Ensure absolute path
    $absBase = __DIR__ . '/../' . ltrim($baseDir, '/');

    if (!is_dir($absBase)) {
        if (!@mkdir($absBase, 0755, true)) {
            throw new RuntimeException('Failed to create upload directory');
        }
    }

    // Generate a random filename (32 hex chars) to avoid collisions
    $random = bin2hex(random_bytes(16));
    $filename = $random . '.' . $ext;
    $destPath = $absBase . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to move uploaded file');
    }

    // Set secure permissions
    @chmod($destPath, 0640);

    // Return path relative to project root (same format as UPLOAD_DIR constant)
    return rtrim($baseDir, '/') . '/' . $filename;
}
