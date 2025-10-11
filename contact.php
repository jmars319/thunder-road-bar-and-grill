<?php
/**
 * contact.php
 * Public contact/job application endpoint.
 *
 * Behavior summary:
 * - Accepts POST submissions from public forms. Returns JSON for XHR
 *   clients or redirects for normal form submissions.
 * - Performs light rate-limiting per IP stored in `data/rate_limits.json`.
 * - Uses a honeypot field to silently drop obvious bots (returns success
 *   so bots don't learn about server-side protections).
 * - Persists submissions to `data/applications.json` for later review.
 *
 * Security notes:
 * - This is intentionally simple. For production consider integrating
 *   an external mail provider, robust rate-limiting, and spam filtering.
 */

function s($v) { return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8'); }
// Try to enable PHPMailer if Composer autoload is present
$usePHPMailer = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $usePHPMailer = class_exists('\PHPMailer\PHPMailer\PHPMailer');
}

// Helper: atomic write of JSON to avoid partial/corrupt files. Returns true on success.
function atomic_write_json($path, $data, $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) {
    $tmp = $path . '.tmp';
    $json = json_encode($data, $flags);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    @chmod($path, 0640);
    return true;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
if ($isAjax) header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) { http_response_code(405); echo json_encode(['success' => false, 'errors' => ['Method not allowed']]); }
    else { header('Location: /'); }
    exit;
}

$now = time();
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$first_name = s($_POST['first_name'] ?? '');
$last_name = s($_POST['last_name'] ?? '');
$email = s($_POST['email'] ?? '');
$phone = s($_POST['phone'] ?? '');
$address = s($_POST['address'] ?? '');
$age = s($_POST['age'] ?? '');
$eligible_to_work = s($_POST['eligible_to_work'] ?? '');
$position_desired = s($_POST['position_desired'] ?? '');
$employment_type = s($_POST['employment_type'] ?? '');
$why_work_here = s($_POST['why_work_here'] ?? '');
$availability = is_array($_POST['availability'] ?? null) ? $_POST['availability'] : [];
// support generic contact forms that post a 'message' field
$message = s($_POST['message'] ?? '');
// determine submission type: 'application' if position_desired present, otherwise 'contact'
$submission_type = $position_desired ? 'application' : 'contact';
$honeypot = trim($_POST['hp_field'] ?? '');

$dataDir = __DIR__ . '/data'; @mkdir($dataDir, 0755, true);
$applicationsFile = $dataDir . '/applications.json';
$rateFile = $dataDir . '/rate_limits.json';

// Resume upload settings
$resumesDir = __DIR__ . '/uploads/resumes/';
@mkdir($resumesDir, 0755, true);
define('APPLICATION_MAX_RESUME_BYTES', 5 * 1024 * 1024); // 5MB
// allowed mime types/extensions (we'll validate by both ext and basic mime)
$allowedResumeExt = ['pdf','doc','docx'];
$allowedResumeMime = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

$rateLimits = [];
if (file_exists($rateFile)) {
    $r = @file_get_contents($rateFile);
    $rateLimits = $r ? json_decode($r, true) : [];
    if (!is_array($rateLimits)) $rateLimits = [];
}
foreach ($rateLimits as $ip => $times) {
    $rateLimits[$ip] = array_values(array_filter((array)$times, fn($t) => ($now - (int)$t) < 600));
    if (empty($rateLimits[$ip])) unset($rateLimits[$ip]);
}
if ((isset($rateLimits[$clientIp]) ? count($rateLimits[$clientIp]) : 0) >= 5) {
    if ($isAjax) { http_response_code(429); echo json_encode(['success' => false, 'errors' => ['Too many submissions']]); }
    else { header('Location: /index.html?contact_error=1#contact'); }
    exit;
}

if ($honeypot !== '') {
    if ($isAjax) { echo json_encode(['success' => true]); }
    else { header('Location: /index.php?contact_success=1#contact'); }
    exit;
}

// Validation: differ based on submission type
$errors = [];
if ($first_name === '') $errors[] = 'First name required';
if ($last_name === '') $errors[] = 'Last name required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
if ($phone === '') $errors[] = 'Phone required';
if ($submission_type === 'application') {
    if ($position_desired === '') $errors[] = 'Position desired required';
    if ($why_work_here === '') $errors[] = 'Tell us why you want to work here';

    // Age validation: enforce different minimums for certain positions
    $ageInt = is_numeric($age) ? intval($age) : null;
    $requiredMin = 16;
    if (in_array(strtolower($position_desired), ['server', 'bartender'])) {
        $requiredMin = 18;
    }
    if ($ageInt === null || $ageInt < $requiredMin) {
        $errors[] = "Minimum age for the selected position is {$requiredMin} years";
    }
} else {
    // generic contact: require a message
    if (trim($message) === '') $errors[] = 'Message is required';
}
if (!empty($errors)) {
    if ($isAjax) { http_response_code(400); echo json_encode(['success' => false, 'errors' => $errors]); }
    else { header('Location: /index.html?contact_error=1#contact'); }
    exit;
}

// Handle resume upload (optional)
if (!empty($_FILES['resume']) && is_array($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
    $rf = $_FILES['resume'];
    if ($rf['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading resume file';
    } else {
        if ($rf['size'] > APPLICATION_MAX_RESUME_BYTES) {
            $errors[] = 'Resume must be 5MB or smaller';
        } else {
            $orig = basename($rf['name']);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $finfoMime = mime_content_type($rf['tmp_name']) ?: '';
            if (!in_array($ext, $allowedResumeExt) || !in_array($finfoMime, $allowedResumeMime)) {
                // allow if extension matches and mime is one of the common types; else reject
                $errors[] = 'Resume must be a PDF or Word document (pdf, doc, docx)';
            } else {
                // generate safe filename
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
                $ts = time();
                $newName = $ts . '-' . bin2hex(random_bytes(6)) . '-' . $safe;
                $dest = $resumesDir . $newName;
                if (!@move_uploaded_file($rf['tmp_name'], $dest)) {
                    $errors[] = 'Failed to save resume file';
                } else {
                    @chmod($dest, 0644);
                    // store in entry
                    $resume_filename = 'uploads/resumes/' . $newName;
                    $resume_original_name = $orig;
                }
            }
        }
    }
}

if (!empty($errors)) {
    if ($isAjax) { http_response_code(400); echo json_encode(['success' => false, 'errors' => $errors]); }
    else { header('Location: /index.html?contact_error=1#contact'); }
    exit;
}

$availability_text = !empty($availability) ? implode(', ', $availability) : 'Not specified';
$to = 'thundergrillmidway@gmail.com';
$subject = 'Job Application: ' . $first_name . ' ' . $last_name . ' - ' . $position_desired;
$body = "Restaurant Job Application\n\n";
$body .= "Name: {$first_name} {$last_name}\nEmail: {$email}\nPhone: {$phone}\nAddress: {$address}\n\n";
$body .= "Position: {$position_desired}\nEmployment type: {$employment_type}\nAvailability: {$availability_text}\n\n";
$headers = "From: {$first_name} {$last_name} <{$email}>\r\nReply-To: {$email}\r\nX-Mailer: PHP/" . phpversion();

$mailSent = false;
// Helper: try SMTP via PHPMailer when available and configured, otherwise fall back to mail()
function send_via_smtp_if_available($to, $subject, $body, $fromHeader) {
    // Configuration constants live in admin/config.php; try to include it safely
    $cfg = __DIR__ . '/admin/config.php';
    if (!file_exists($cfg)) return false;
    require_once $cfg;

    // Only attempt if Composer autoload is present
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) return false;

    // Prefer overrides loaded into $GLOBALS by admin/config.php
    $smtpUser = $GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
    $smtpPass = $GLOBALS['SMTP_PASSWORD_OVERRIDE'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
    if (empty($smtpUser) || empty($smtpPass)) return false;

    // PHPMailer usage
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        return (bool) $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

if (!empty($to)) {
    // prefer SMTP when available and configured
    $mailSent = send_via_smtp_if_available($to, $subject, $body, $headers);
    if (!$mailSent && function_exists('mail')) {
        $mailSent = (bool) @mail($to, $subject, $body, $headers);
    }
}

// Use eastern_now() when available so stored entries are in US/Eastern time
if (file_exists(__DIR__ . '/admin/config.php')) {
    // include in a way that doesn't force admin session behavior
    @include_once __DIR__ . '/admin/config.php';
}

$entry = [
    'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c', $now),
    'ip' => $clientIp,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'age' => $age,
    'eligible_to_work' => $eligible_to_work,
    'position_desired' => $position_desired,
    'employment_type' => $employment_type,
    'why_work_here' => $why_work_here,
    'availability' => $availability,
    'sent' => $mailSent,
    'resume_filename' => null,
    'resume_original_name' => null,
];

$applications = [];
if (file_exists($applicationsFile)) {
    $m = @file_get_contents($applicationsFile);
    $applications = $m ? json_decode($m, true) : [];
    if (!is_array($applications)) $applications = [];
}
$applications[] = $entry;
if (!atomic_write_json($applicationsFile, $applications)) {
    error_log('contact.php: failed to write applications file: ' . $applicationsFile);
}

$rateLimits[$clientIp][] = $now;
if (!atomic_write_json($rateFile, $rateLimits, JSON_PRETTY_PRINT)) {
    error_log('contact.php: failed to write rate limits file: ' . $rateFile);
}

if ($isAjax) { echo json_encode(['success' => true, 'sent' => (bool) $mailSent]); exit; }
header('Location: /index.php?contact_success=1#contact');
exit;

