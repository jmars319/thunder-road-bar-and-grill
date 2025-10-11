<?php
// cron/send_scheduled_emails.php
// CLI cron script to check and send scheduled emails

require_once __DIR__ . '/../lib/email_scheduler.php';

class CronEmailSender {
    private $scheduler;
    private $logFile;

    public function __construct() {
        $this->scheduler = new EmailScheduler(__DIR__ . '/../data/email_scheduler.sqlite');
        $this->logFile = __DIR__ . '/cron.log';
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    public function checkAndSendEmails() {
        $this->log("Cron job started");
        $currentDay = strtolower(date('l'));
        $currentTime = date('H:i');
        $this->log("Current day: $currentDay, Current time: $currentTime");

        $campaigns = $this->scheduler->getCampaigns();
        foreach ($campaigns as $campaign) {
            if (!$campaign['active']) continue;
            if (!in_array($currentDay, $campaign['send_days'])) continue;
            $sendTime = $campaign['send_time'];
            if ($this->isTimeToSend($currentTime, $sendTime)) {
                $this->log("Sending campaign: {$campaign['name']} (ID: {$campaign['id']})");
                try {
                    $result = $this->scheduler->sendCampaignEmail($campaign['id']);
                    if ($result) $this->log("Successfully sent campaign: {$campaign['name']}");
                    else $this->log("Failed to send campaign: {$campaign['name']}");
                } catch (Exception $e) {
                    $this->log("Error sending campaign {$campaign['name']}: " . $e->getMessage());
                }
            }
        }
        $this->log("Cron job completed\n");
    }

    private function isTimeToSend($currentTime, $sendTime) {
        $current = strtotime($currentTime);
        $scheduled = strtotime($sendTime);
        $diff = abs($current - $scheduled);
        return $diff < 60;
    }
}

$sender = new CronEmailSender();
$sender->checkAndSendEmails();

?>
