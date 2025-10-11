<?php
// admin/api/email_api.php
// Secured REST API for Email Scheduler

require_once __DIR__ . '/../config.php';
require_admin();

header('Content-Type: application/json');

// Require core class
require_once __DIR__ . '/../../lib/email_scheduler.php';

try {
    $scheduler = new EmailScheduler(__DIR__ . '/../../data/email_scheduler.sqlite');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize scheduler: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Read input JSON for POST/PUT
$input = null;
if (in_array($method, ['POST', 'PUT'])) {
    $body = file_get_contents('php://input');
    $input = json_decode($body, true);
    if ($input === null && $body !== '') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }
}

// Helper
function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// For state-changing requests, verify CSRF token
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf_token'] ?? ($input['csrf_token'] ?? null));
    if (!verify_csrf($csrf)) {
        respond(['error' => 'Invalid CSRF token'], 403);
    }
}

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'campaigns':
                    respond(['campaigns' => $scheduler->getCampaigns()]);
                    break;
                case 'campaign':
                    $id = $_GET['id'] ?? null;
                    if (!$id) respond(['error' => 'Campaign ID required'], 400);
                    $c = $scheduler->getCampaign($id);
                    if (!$c) respond(['error' => 'Campaign not found'], 404);
                    respond(['campaign' => $c]);
                    break;
                case 'config':
                    $cfg = $scheduler->getEmailConfig();
                    if ($cfg) unset($cfg['email_password']);
                    respond(['config' => $cfg]);
                    break;
                case 'logs':
                    $campaignId = $_GET['campaign_id'] ?? null;
                    respond(['logs' => $scheduler->getEmailLogs($campaignId)]);
                    break;
                default:
                    respond(['error' => 'Unknown action'], 400);
            }
            break;

        case 'POST':
            switch ($action) {
                case 'campaign':
                    $required = ['name','subject','body','recipients','send_days','send_time'];
                    foreach ($required as $r) {
                        if (!isset($input[$r])) respond(['error' => "Missing required field: $r"], 400);
                    }
                    // normalize recipients: allow newline-separated string
                    if (is_string($input['recipients'])) {
                        $input['recipients'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $input['recipients']))));
                    }
                    $id = $scheduler->createCampaign($input);
                    respond(['success' => true, 'id' => $id, 'message' => 'Campaign created successfully']);
                    break;

                case 'supplier':
                    $campaignId = $input['campaign_id'] ?? null;
                    if (!$campaignId) respond(['error' => 'Campaign ID required'], 400);
                    if (empty($input['name']) || empty($input['url']) || empty($input['selectors'])) {
                        respond(['error' => 'Missing required fields: name, url, selectors'], 400);
                    }
                    $res = $scheduler->addSupplier($campaignId, $input);
                    respond(['success' => (bool)$res, 'message' => 'Supplier added successfully']);
                    break;

                case 'config':
                    $req = ['smtp_server','smtp_port','email_address','email_password'];
                    foreach ($req as $r) if (!isset($input[$r])) respond(['error'=>'Missing required field: '.$r],400);
                    $res = $scheduler->saveEmailConfig($input);
                    respond(['success' => (bool)$res, 'message' => 'Email configuration saved successfully']);
                    break;

                case 'send':
                    $campaignId = $input['campaign_id'] ?? null;
                    if (!$campaignId) respond(['error' => 'Campaign ID required'], 400);
                    $res = $scheduler->sendCampaignEmail($campaignId);
                    if ($res) respond(['success'=>true,'message'=>'Campaign sent successfully']);
                    respond(['success'=>false,'message'=>'Failed to send campaign'],500);
                    break;

                case 'test-scrape':
                    $url = $input['url'] ?? null;
                    $selectors = $input['selectors'] ?? null;
                    if (!$url || !$selectors) respond(['error' => 'URL and selectors required'], 400);
                    $result = $scheduler->scrapeSupplierData($url, $selectors);
                    if ($result === null) respond(['success'=>false,'message'=>'Failed to scrape website. Check URL and selectors.','data'=>null],400);
                    respond(['success'=>true,'message'=>'Scraping successful','data'=>$result]);
                    break;

                default:
                    respond(['error' => 'Unknown action'], 400);
            }
            break;

        case 'PUT':
            switch ($action) {
                case 'campaign':
                    $id = $_GET['id'] ?? null;
                    if (!$id) respond(['error'=>'Campaign ID required'],400);
                    $existing = $scheduler->getCampaign($id);
                    if (!$existing) respond(['error'=>'Campaign not found'],404);
                    $res = $scheduler->updateCampaign($id, $input);
                    respond(['success' => (bool)$res, 'message' => 'Campaign updated successfully']);
                    break;
                default:
                    respond(['error'=>'Unknown action'],400);
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'campaign':
                    $id = $_GET['id'] ?? null;
                    if (!$id) respond(['error'=>'Campaign ID required'],400);
                    $existing = $scheduler->getCampaign($id);
                    if (!$existing) respond(['error'=>'Campaign not found'],404);
                    $res = $scheduler->deleteCampaign($id);
                    respond(['success'=>(bool)$res,'message'=>'Campaign deleted successfully']);
                    break;
                case 'supplier':
                    $id = $_GET['id'] ?? null;
                    if (!$id) respond(['error'=>'Supplier ID required'],400);
                    $res = $scheduler->deleteSupplier($id);
                    respond(['success'=>(bool)$res,'message'=>'Supplier deleted successfully']);
                    break;
                default:
                    respond(['error'=>'Unknown action'],400);
            }
            break;

        default:
            respond(['error'=>'Method not allowed'],405);
    }
} catch (Exception $e) {
    respond(['error' => $e->getMessage()], 500);
}

// EOF
