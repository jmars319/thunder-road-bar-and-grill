<?php
require_once __DIR__ . '/config.php';
require_admin();
header('Content-Type: application/json');
// read JSON body (recipient and csrf_token)
$raw = @file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : [];
$token = $body['csrf_token'] ?? '';
$recipient = isset($body['recipient']) && filter_var($body['recipient'], FILTER_VALIDATE_EMAIL) ? $body['recipient'] : null;
if (!verify_csrf($token)) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit; }

// build a small test message
$to = $recipient ?: ((defined('SMTP_FROM_ADDRESS') ? SMTP_FROM_ADDRESS : ($GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? '')) ?: 'test@example.com');
$subject = 'SMTP Test from Admin';
$testBody = "This is a test email sent from the admin SMTP tester at " . (function_exists('eastern_now') ? eastern_now('c') : date('c')) . "\n";

// attempt to send using the same helper logic as contact.php
function local_send_test($to, $subject, $body) {
    $cfg = __DIR__ . '/config.php';
    if (!file_exists($cfg)) return ['success'=>false,'message'=>'Missing config'];
    require_once $cfg;
    // load auth.json overrides
    $authPath = __DIR__ . '/auth.json';
    $smtpUser = $GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
    $smtpPass = $GLOBALS['SMTP_PASSWORD_OVERRIDE'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
    if (file_exists($authPath)) {
        $r = @file_get_contents($authPath);
        $j = $r ? json_decode($r, true) : null;
        if (is_array($j)) {
            if (!empty($j['smtp_username'])) $smtpUser = $j['smtp_username'];
            if (!empty($j['smtp_password'])) $smtpPass = $j['smtp_password'];
        }
    }
    if (empty($smtpUser) || empty($smtpPass)) return ['success'=>false,'message'=>'SMTP credentials not configured'];
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) return ['success'=>false,'message'=>'PHPMailer not installed (run composer install)'];
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
        $sent = (bool) $mail->send();
        return ['success'=>$sent,'message'=>$sent ? 'Sent' : 'Failed to send via SMTP'];
    } catch (Exception $e) {
        return ['success'=>false,'message'=>'SMTP error: ' . $e->getMessage()];
    }
}

// attempt send
$res = local_send_test($to, $subject, $testBody);
echo json_encode($res);
exit;
