<?php
/**
 * admin/reserve.php
 * Public reservation handler (non-authenticated). Validates minimal
 * fields and appends reservation entries to `data/reservations.json`.
 *
 * Contract:
 *  - Inputs: POST { name, phone, date, time, event_type }
 *  - Outputs: redirects back to the public site with success or
 *    error query params. No JSON API provided for this endpoint.
 *
 * Notes:
 *  - This endpoint is intentionally simple; for higher-volume sites
 *    consider adding rate-limiting and stronger validation.
 */

require_once __DIR__ . '/config.php';
// No auth required for public reservation
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php#reservation');
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$event = trim($_POST['event_type'] ?? '');
$guestsRaw = trim((string)($_POST['guests'] ?? ''));

$guests = 1;
if ($guestsRaw !== '') {
    // allow numeric strings, coerce to int, require at least 1
    $gclean = preg_replace('/[^0-9]/', '', $guestsRaw);
    if ($gclean === '' || !is_numeric($gclean)) {
        $errors[] = 'Invalid number of guests';
    } else {
        $guests = max(1, intval($gclean));
    }
}

$errors = [];
if ($name === '') $errors[] = 'Name is required';
if ($phone === '') $errors[] = 'Phone is required';
if ($date === '') $errors[] = 'Date is required';
if ($time === '') $errors[] = 'Time is required';

if (!empty($errors)) {
    // Simple fallback: redirect back with a basic query param
    $msg = urlencode(implode('; ', $errors));
    header('Location: ../index.php#reservation?error=' . $msg);
    exit;
}

$resFile = __DIR__ . '/../data/reservations.json';
if (!is_dir(dirname($resFile))) @mkdir(dirname($resFile), 0755, true);

$entry = [
    'name' => $name,
    'phone' => $phone,
    'date' => $date,
    'time' => $time,
    'event_type' => $event,
    'guests' => $guests,
    // record reservation timestamp in US/Eastern when possible
    'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c')),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

$list = [];
if (file_exists($resFile)) {
    $j = @file_get_contents($resFile);
    $list = $j ? json_decode($j, true) : [];
    if (!is_array($list)) $list = [];
}
$list[] = $entry;
$json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json !== false) { file_put_contents($resFile . '.tmp', $json, LOCK_EX); @rename($resFile . '.tmp', $resFile); } else { error_log('reserve.php: failed to encode reservations'); }

// Append to a simple reservation audit log so admins can quickly see recent activity
$auditFile = __DIR__ . '/../data/reservation-audit.json';
// Attempt to send notification email if configured (prefer PHPMailer SMTP)
$mailSent = false;
$trySend = defined('RESERVATION_NOTIFICATION_EMAIL') && !empty(RESERVATION_NOTIFICATION_EMAIL);
if ($trySend) {
    $to = RESERVATION_NOTIFICATION_EMAIL;
    $subject = 'New Reservation Request: ' . $name . ' on ' . $date . ' ' . $time;
    $body = "Reservation request\n\nName: {$name}\nPhone: {$phone}\nDate: {$date}\nTime: {$time}\nGuests: {$guests}\nEvent: {$event}\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
    $headers = "From: Reservations <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\nReply-To: {$phone}\r\nX-Mailer: PHP/" . phpversion();

    // Try PHPMailer via Composer and SMTP settings in admin/config.php
    $cfg = __DIR__ . '/config.php';
    if (file_exists($cfg)) require_once $cfg;
    $smtpAttempted = false;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        $smtpUser = $GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $smtpPass = $GLOBALS['SMTP_PASSWORD_OVERRIDE'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
        if (!empty($smtpUser) && !empty($smtpPass)) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
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
            $mailSent = (bool) $mail->send();
            $smtpAttempted = true;
        } catch (Exception $e) {
            $smtpAttempted = true;
            $mailSent = false;
        }
        }
    }

    // Fallback to mail() if PHPMailer wasn't available or failed
    if (!$smtpAttempted && function_exists('mail')) {
        $mailSent = (bool) @mail($to, $subject, $body, $headers);
    }
}

$auditEntry = [ 'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c')), 'name' => $name, 'phone' => $phone, 'date' => $date, 'time' => $time, 'guests' => $guests, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'sent' => $mailSent ];
$auditList = [];
if (file_exists($auditFile)) {
    $aj = @file_get_contents($auditFile);
    $auditList = $aj ? json_decode($aj, true) : [];
    if (!is_array($auditList)) $auditList = [];
}
$auditList[] = $auditEntry;
// keep last 200 entries to avoid unbounded growth
if (count($auditList) > 200) $auditList = array_slice($auditList, -200);
$aj = json_encode($auditList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($aj !== false) { file_put_contents($auditFile . '.tmp', $aj, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); } else { error_log('reserve.php: failed to encode audit list'); }

// Redirect back with success anchor and include guest count for confirmation
header('Location: ../index.php#reservation?success=1&guests=' . urlencode((string)$guests));
exit;
