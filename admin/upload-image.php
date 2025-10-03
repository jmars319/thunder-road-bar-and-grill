<?php
/**
 * admin/upload-image.php
 * Receives image uploads from the admin UI, re-encodes and optimizes
 * images, writes thumbnails, updates the content store, and appends
 * an audit log entry.
 *
 * Contract:
 *  - Inputs: multipart/form-data with file input `image` and optional
 *    `type` parameter. CSRF token must be present in POST `csrf_token`
 *    or HTTP header `X-CSRF-TOKEN`.
 *  - Outputs: JSON { success: bool, message: string, filename, url, thumbnail }
 *  - Side effects: moves uploaded file to `uploads/images/`, creates
 *    thumbnail, updates `data/content.json`, and writes audit log.
 *
 * Security notes:
 *  - The endpoint validates image MIME using getimagesize() and
 *    re-encodes images server-side to strip EXIF/metadata. Server-side
 *    CSRF and session auth are required.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // Verify CSRF token
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $image = $_FILES['image'];
    $imageType = preg_replace('/[^a-z0-9_\-]/i', '', ($_POST['type'] ?? 'general'));

    // Basic upload error check
    if (!isset($image['error']) || $image['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit;
    }

    // Validate size (client can be spoofed but check anyway)
    $maxSize = 5 * 1024 * 1024; // 5MB
    // Maximum allowed dimensions for raster and vector images (pixels)
    $maxWidth = 5000;
    $maxHeight = 5000;
    if ($image['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    // Use finfo to get MIME type reliably; getimagesize works for raster images
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeFromFinfo = $finfo ? finfo_file($finfo, $image['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);

    // Special-case: allow SVG but sanitize it. getimagesize() typically
    // does not return useful info for SVGs, so handle SVG via finfo.
    $isSvg = false;
    if (!empty($mimeFromFinfo) && in_array($mimeFromFinfo, ['image/svg+xml', 'text/xml', 'application/xml'])) {
        // Quick content check to avoid false positives
        $contents = @file_get_contents($image['tmp_name']);
        if ($contents !== false && preg_match('/<svg[\s>]/i', $contents)) {
            $isSvg = true;
        }
    }

    if ($isSvg) {
        // Enforce size limit
        if ($image['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'SVG file too large. Maximum size is 5MB.']);
            exit;
        }
        // Basic sanitize and save SVG content (remove scripts, on* attributes, foreignObject, external refs)
        $svg = @file_get_contents($image['tmp_name']);
        if ($svg === false || stripos($svg, '<svg') === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Uploaded SVG appears invalid']);
            exit;
        }
        $svg = sanitizeSvg($svg);
        // try to extract viewBox or width/height to perform a rough dimension check
        $dimsOk = true;
        if (preg_match('/viewBox="?\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*"?/i', $svg, $m)) {
            $vw = (float)$m[3]; $vh = (float)$m[4];
            if ($vw > $maxWidth || $vh > $maxHeight) $dimsOk = false;
        } else {
            if (preg_match('/<svg[^>]*width="?([0-9\.]+)px?"?/i', $svg, $m2)) {
                if ((float)$m2[1] > $maxWidth) $dimsOk = false;
            }
            if (preg_match('/<svg[^>]*height="?([0-9\.]+)px?"?/i', $svg, $m3)) {
                if ((float)$m3[1] > $maxHeight) $dimsOk = false;
            }
        }
        if (!$dimsOk) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'SVG dimensions exceed the allowed maximum']);
            exit;
        }

        // map to svg extension and prepare final filename
        $extension = 'svg';
        $safeType = preg_replace('/[^a-z0-9_\-]/i','', $imageType ?: 'general');
        $filename = $safeType . '-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $uploadPath = rtrim(UPLOAD_DIR, '/') . '/' . $filename;

        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
        if (file_put_contents($uploadPath, $svg) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save sanitized SVG']);
            exit;
        }
        @chmod($uploadPath, 0644);

        // Update content.json with new image path
        updateImagePath($imageType, $filename);

        // Write audit log for the upload
        write_upload_audit([
            'admin' => $_SESSION['admin_username'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'original_name' => substr($image['name'] ?? '', 0, 200),
            'stored_name' => $filename,
            'type' => $imageType,
            'mime' => 'image/svg+xml',
            'size' => (int)($image['size'] ?? 0),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c'))
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'SVG uploaded and sanitized successfully!',
            'filename' => $filename,
            'url' => '../uploads/images/' . $filename,
            'thumbnail' => null,
        ]);
        exit;
    }

    // For raster images continue: use getimagesize on tmp file to get MIME/type reliably
    $info = @getimagesize($image['tmp_name']);
    if ($info === false || !isset($info['mime']) || $mimeFromFinfo === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Uploaded file is not a valid image']);
        exit;
    }
    $mime = $info['mime'];
    // cross-check finfo result when available
    if (!empty($mimeFromFinfo) && $mimeFromFinfo !== $mime) {
        // mismatch between getimagesize and finfo -> reject upload
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Uploaded file mime mismatch']);
        exit;
    }

    // Allowed mime types and map to extension
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($mimeMap[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid image type. Only JPG, PNG, GIF and WebP are allowed.']);
        exit;
    }

    $extension = $mimeMap[$mime];
    // Check image dimensions (prevent absurdly large images)
    $width = isset($info[0]) ? (int)$info[0] : 0;
    $height = isset($info[1]) ? (int)$info[1] : 0;
    if ($width <= 0 || $height <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid image dimensions']);
        exit;
    }
    if ($width > $maxWidth || $height > $maxHeight) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image dimensions too large. Maximum is '.$maxWidth.'x'.$maxHeight.' pixels.']);
        exit;
    }
    // generate a filesystem-safe filename (no user input preserved)
    $safeType = preg_replace('/[^a-z0-9_\-]/i','', $imageType ?: 'general');
    $filename = $safeType . '-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $uploadPath = rtrim(UPLOAD_DIR, '/') . '/' . $filename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Move uploaded file to uploads directory first
    if (!is_dir(UPLOAD_DIR)) {
        if (!@mkdir(UPLOAD_DIR, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
    }

    // Move with strict permissions and ownership as possible
    if (!move_uploaded_file($image['tmp_name'], $uploadPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit;
    }

    // ensure uploaded file has safe permissions
    @chmod($uploadPath, 0644);

    // Always re-encode the image using GD to strip metadata and ensure safe format
    reencodeImage($uploadPath, $mime);

    // Create thumbnails dir
    $thumbDir = rtrim(UPLOAD_DIR, '/') . '/thumbs/';
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);

    // Generate thumbnail (server-side)
    $thumbPath = $thumbDir . $filename;
    createThumbnail($uploadPath, $thumbPath, 320, 180);

    // Update content.json with new image path
    updateImagePath($imageType, $filename);

    // Write audit log for the upload
    write_upload_audit([
        'admin' => $_SESSION['admin_username'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'original_name' => substr($image['name'] ?? '', 0, 200),
        'stored_name' => $filename,
        'type' => $imageType,
        'mime' => $mime,
        'size' => (int)($image['size'] ?? 0),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c'))
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully!',
        'filename' => $filename,
        'url' => '../uploads/images/' . $filename,
        'thumbnail' => file_exists($thumbPath) ? '../uploads/images/thumbs/' . $filename : null,
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No image file provided'
    ]);
}

/**
 * Append an upload audit entry (JSON lines) to data/upload-audit.log
 */
function write_upload_audit($entry) {
    $logDir = __DIR__ . '/../data';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/upload-audit.log';
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
    // append to log via temp approach (append is fine but ensure parent exists)
    if (!file_exists(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
    if (file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('upload-image.php: failed to append to log ' . $logFile);
    }
}

/**
 * Optimize uploaded image
 */
function optimizeImage($filepath, $type) {
    // Maximum dimensions
    $maxWidth = 1920;
    $maxHeight = 1080;
    $quality = 85;
    
    // Get image info
    $sz = @getimagesize($filepath);
    if ($sz === false) return;
    list($width, $height) = $sz;
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);
    
    // Create image resource based on type
    // Create image resource based on detected mime type
    $mime = $type;
    switch ($mime) {
        case 'image/jpeg': $source = @imagecreatefromjpeg($filepath); break;
        case 'image/png': $source = @imagecreatefrompng($filepath); break;
        case 'image/gif': $source = @imagecreatefromgif($filepath); break;
        case 'image/webp': $source = (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filepath) : false); break;
        default: return;
    }
    if (!$source) return;
    
    // Create new image
    $destination = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($type === 'image/png') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }
    
    // Resize
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save optimized image
    switch ($mime) {
        case 'image/jpeg': imagejpeg($destination, $filepath, $quality); break;
        case 'image/png': imagepng($destination, $filepath, 9); break;
        case 'image/gif': imagegif($destination, $filepath); break;
        case 'image/webp': if (function_exists('imagewebp')) imagewebp($destination, $filepath, $quality); break;
    }
    
    // Free memory
    imagedestroy($source);
    imagedestroy($destination);
}

/**
 * Re-encode image to strip metadata and ensure safe format
 */
function reencodeImage($filepath, $mime) {
    if (!function_exists('getimagesize')) return false;
    $info = @getimagesize($filepath);
    if ($info === false) return false;
    $type = $info[2];

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($filepath); break;
        case IMAGETYPE_PNG: $src = @imagecreatefrompng($filepath); break;
        case IMAGETYPE_GIF: $src = @imagecreatefromgif($filepath); break;
        case IMAGETYPE_WEBP: $src = (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filepath) : false); break;
        default: return false;
    }
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopy($dst, $src, 0,0,0,0, $w, $h);

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($dst, $filepath, 90); break;
        case IMAGETYPE_PNG: $ok = imagepng($dst, $filepath, 9); break;
        case IMAGETYPE_GIF: $ok = imagegif($dst, $filepath); break;
        case IMAGETYPE_WEBP: if (function_exists('imagewebp')) $ok = imagewebp($dst, $filepath, 90); break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

    /**
     * Create a thumbnail with GD
     */
    function createThumbnail($src, $dest, $maxW = 320, $maxH = 180) {
        if (!function_exists('getimagesize')) return false;
        $info = @getimagesize($src);
        if ($info === false) return false;
        list($width, $height, $type) = $info;

        $ratio = min($maxW / $width, $maxH / $height, 1);
        $newW = (int)round($width * $ratio);
        $newH = (int)round($height * $ratio);

        switch ($type) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
            case IMAGETYPE_PNG: $img = @imagecreatefrompng($src); break;
            case IMAGETYPE_GIF: $img = @imagecreatefromgif($src); break;
            case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
            default: return false;
        }
        if (!$img) return false;

        $thumb = imagecreatetruecolor($newW, $newH);
        // preserve transparency
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        imagecopyresampled($thumb, $img, 0,0,0,0, $newW, $newH, $width, $height);

        // ensure dest dir
        $dDir = dirname($dest);
        if (!is_dir($dDir)) @mkdir($dDir, 0755, true);

        $ok = false;
        switch ($type) {
            case IMAGETYPE_JPEG: $ok = imagejpeg($thumb, $dest, 85); break;
            case IMAGETYPE_PNG: $ok = imagepng($thumb, $dest, 9); break;
            case IMAGETYPE_GIF: $ok = imagegif($thumb, $dest); break;
            case IMAGETYPE_WEBP: if (function_exists('imagewebp')) $ok = imagewebp($thumb, $dest, 85); break;
        }

        imagedestroy($img);
        imagedestroy($thumb);
        return $ok;
    }

/**
 * Update image path in content.json
 */
function updateImagePath($type, $filename) {
    $contentFile = CONTENT_FILE;
    
    if (file_exists($contentFile)) {
        $content = json_decode(file_get_contents($contentFile), true);
    } else {
        $content = ['images' => []];
    }
    
    // Update image path
    $content['images'][$type] = $filename;
    $content['last_updated'] = date('Y-m-d H:i:s');
    
    // Save
    $json = json_encode($content, JSON_PRETTY_PRINT);
    if ($json === false) { error_log('upload-image.php: failed to encode content for ' . $contentFile); }
    else {
        if (file_put_contents($contentFile . '.tmp', $json, LOCK_EX) !== false) @rename($contentFile . '.tmp', $contentFile);
    }
}

/**
 * Very small SVG sanitizer: removes <script>, on* attributes, foreignObject,
 * external resource references (xlink:href with http), and <!ENTITY> declarations.
 * This is intentionally conservative but not a full security-proof sanitizer.
 */
function sanitizeSvg($svg) {
    // Remove XML external entities
    $svg = preg_replace('/<!ENTITY[^>]*>/i', '', $svg);
    // Remove script/style blocks
    $svg = preg_replace('#<script[\s\S]*?</script>#i', '', $svg);
    $svg = preg_replace('#<style[\s\S]*?</style>#i', '', $svg);
    // Remove foreignObject which can embed HTML
    $svg = preg_replace('#<foreignObject[\s\S]*?</foreignObject>#i', '', $svg);
    // Remove on* event attributes like onclick, onload
    $svg = preg_replace_callback('/\s(on[a-zA-Z]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^>\s]+)/i', function($m){ return ' '; }, $svg);
    // Remove javascript: hrefs and xlink:href with external protocols
    $svg = preg_replace('/(href|xlink:href)\s*=\s*("javascript:[^"\']*"|\'javascript:[^"\']*\'|"https?:\/\/[^"\']*"|\'https?:\/\/[^"\']*\')/i', '$1="#"', $svg);
    // Strip potentially harmful <object>, <iframe>, <embed>
    $svg = preg_replace('#<(object|iframe|embed)[\s\S]*?</\1>#i', '', $svg);
    // Remove xmlns:xlink to reduce some external refs
    $svg = preg_replace('/xmlns:xlink="[^"]*"/i', '', $svg);
    // Return trimmed string
    return trim($svg);
}
?>
