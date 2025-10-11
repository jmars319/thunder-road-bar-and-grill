<?php
// EmailScheduler core class adapted from package
// Default DB path set to data/email_scheduler.sqlite (outside web-accessible admin)

// Basic environment checks
if (!extension_loaded('pdo')) {
    throw new Exception('PDO extension is required for EmailScheduler.');
}
if (!in_array('sqlite', PDO::getAvailableDrivers())) {
    throw new Exception('PDO SQLite driver is required for EmailScheduler.');
}
if (!extension_loaded('curl')) {
    throw new Exception('cURL extension is required for EmailScheduler.');
}
if (!class_exists('DOMDocument')) {
    throw new Exception('DOMDocument (libxml) extension is required for EmailScheduler.');
}

class EmailScheduler {
    private $db;

    /**
     * Constructor - Initializes database connection
     * @param string $dbPath Path to SQLite database file
     */
    public function __construct($dbPath = null) {
        if ($dbPath === null) {
            // default: project data directory
            $dbPath = __DIR__ . '/../data/email_scheduler.sqlite';
        }

        // Ensure data directory exists
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }

    private function initDatabase() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                recipients TEXT NOT NULL,
                send_days TEXT NOT NULL,
                send_time TEXT NOT NULL,
                active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                selectors TEXT NOT NULL,
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_config (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                smtp_server TEXT NOT NULL,
                smtp_port INTEGER NOT NULL,
                email_address TEXT NOT NULL,
                email_password TEXT NOT NULL
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status TEXT,
                message TEXT,
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
            )
        ");
    }

    /* ==================== CAMPAIGN MANAGEMENT ==================== */
    public function createCampaign($data) {
        $stmt = $this->db->prepare("
            INSERT INTO campaigns (name, subject, body, recipients, send_days, send_time, active)
            VALUES (:name, :subject, :body, :recipients, :send_days, :send_time, :active)
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':subject' => $data['subject'],
            ':body' => $data['body'],
            ':recipients' => json_encode($data['recipients']),
            ':send_days' => json_encode($data['send_days']),
            ':send_time' => $data['send_time'],
            ':active' => $data['active'] ?? 1
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getCampaigns() {
        $stmt = $this->db->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        $campaigns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['recipients'] = json_decode($row['recipients'], true);
            $row['send_days'] = json_decode($row['send_days'], true);
            $campaigns[] = $row;
        }
        return $campaigns;
    }

    public function getCampaign($id) {
        $stmt = $this->db->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($campaign) {
            $campaign['recipients'] = json_decode($campaign['recipients'], true);
            $campaign['send_days'] = json_decode($campaign['send_days'], true);
            $campaign['suppliers'] = $this->getSuppliersByCampaign($id);
        }
        return $campaign;
    }

    public function updateCampaign($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE campaigns 
            SET name = :name, subject = :subject, body = :body, 
                recipients = :recipients, send_days = :send_days, 
                send_time = :send_time, active = :active
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':subject' => $data['subject'],
            ':body' => $data['body'],
            ':recipients' => json_encode($data['recipients']),
            ':send_days' => json_encode($data['send_days']),
            ':send_time' => $data['send_time'],
            ':active' => $data['active'] ?? 1
        ]);
    }

    public function deleteCampaign($id) {
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ==================== SUPPLIER MANAGEMENT ==================== */
    public function addSupplier($campaignId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO suppliers (campaign_id, name, url, selectors)
            VALUES (:campaign_id, :name, :url, :selectors)
        ");
        return $stmt->execute([
            ':campaign_id' => $campaignId,
            ':name' => $data['name'],
            ':url' => $data['url'],
            ':selectors' => json_encode($data['selectors'])
        ]);
    }

    public function getSuppliersByCampaign($campaignId) {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $suppliers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['selectors'] = json_decode($row['selectors'], true);
            $suppliers[] = $row;
        }
        return $suppliers;
    }

    public function deleteSupplier($id) {
        $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ==================== EMAIL CONFIGURATION ==================== */
    public function saveEmailConfig($config) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO email_config (id, smtp_server, smtp_port, email_address, email_password)
            VALUES (1, :smtp_server, :smtp_port, :email_address, :email_password)
        ");
        return $stmt->execute([
            ':smtp_server' => $config['smtp_server'],
            ':smtp_port' => $config['smtp_port'],
            ':email_address' => $config['email_address'],
            ':email_password' => $config['email_password']
        ]);
    }

    public function getEmailConfig() {
        $stmt = $this->db->query("SELECT * FROM email_config WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================== WEB SCRAPING ==================== */
    public function scrapeSupplierData($url, $selectors) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; EmailScheduler/1.0)',
            CURLOPT_TIMEOUT => 10
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || !$html) {
            return null;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $data = [];
        foreach ($selectors as $key => $selector) {
            $xpathQuery = $this->cssToXpath($selector);
            $nodes = $xpath->query($xpathQuery);
            if ($nodes->length > 0) {
                $data[$key] = trim($nodes->item(0)->textContent);
            } else {
                $data[$key] = 'N/A';
            }
        }
        return $data;
    }

    private function cssToXpath($css) {
        $css = trim($css);
        if (strpos($css, '#') === 0) {
            return "//*[@id='" . substr($css, 1) . "']";
        } elseif (strpos($css, '.') === 0) {
            return "//*[contains(@class, '" . substr($css, 1) . "')]";
        } else {
            return "//" . $css;
        }
    }

    /* ==================== EMAIL SENDING ==================== */
    public function sendCampaignEmail($campaignId) {
        $campaign = $this->getCampaign($campaignId);
        $config = $this->getEmailConfig();

        if (!$campaign || !$config) {
            $this->logEmail($campaignId, 'error', 'Campaign or email config not found');
            return false;
        }

        try {
            $supplierData = [];
            foreach ($campaign['suppliers'] as $supplier) {
                $data = $this->scrapeSupplierData($supplier['url'], $supplier['selectors']);
                if ($data) {
                    $supplierData[$supplier['name']] = $data;
                }
            }

            $body = $campaign['body'];
            if (!empty($supplierData)) {
                $body .= "\n\n--- Supplier Information ---\n";
                foreach ($supplierData as $name => $data) {
                    $body .= "\n$name:\n";
                    foreach ($data as $key => $value) {
                        $body .= "  $key: $value\n";
                    }
                }
            }

            $this->sendEmail($config, $campaign['recipients'], $campaign['subject'], $body);
            $this->logEmail($campaignId, 'success', 'Email sent successfully');
            return true;
        } catch (Exception $e) {
            $this->logEmail($campaignId, 'error', $e->getMessage());
            return false;
        }
    }

    private function sendEmail($config, $recipients, $subject, $body) {
        // Prefer PHPMailer (SMTP) if available and configured. Fallback to PHP mail().
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            // Use PHPMailer with SMTP using config or project constants/overrides
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $smtpHost = $config['smtp_server'] ?? (defined('SMTP_HOST') ? SMTP_HOST : null);
                $smtpPort = $config['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : null);
                $smtpUser = $config['email_address'] ?? ($GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : null));
                $smtpPass = $config['email_password'] ?? ($GLOBALS['SMTP_PASSWORD_OVERRIDE'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : null));
                $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

                $mail->isSMTP();
                if ($smtpHost) $mail->Host = $smtpHost;
                if ($smtpPort) $mail->Port = (int)$smtpPort;
                $mail->SMTPAuth = true;
                if ($smtpUser) $mail->Username = $smtpUser;
                if ($smtpPass) $mail->Password = $smtpPass;
                if (!empty($smtpSecure)) {
                    // PHPMailer expects 'tls' or 'ssl' or empty
                    $mail->SMTPSecure = $smtpSecure;
                }

                $fromAddress = $smtpUser ?? ($config['email_address'] ?? ($GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_FROM_ADDRESS') ? SMTP_FROM_ADDRESS : null)));
                $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : null;
                if ($fromAddress) {
                    $mail->setFrom($fromAddress, $fromName ?: '');
                }

                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->isHTML(false);

                foreach ($recipients as $recipient) {
                    $mail->clearAddresses();
                    $mail->addAddress($recipient);
                    if (!$mail->send()) {
                        throw new Exception('PHPMailer failed to send: ' . $mail->ErrorInfo);
                    }
                }
                return;
            } catch (Exception $e) {
                // If PHPMailer fails, fall through to PHP mail() fallback below
                error_log('EmailScheduler: PHPMailer error - ' . $e->getMessage());
            }
        }

        // Fallback: PHP mail()
        $headers = "From: " . ($config['email_address'] ?? (defined('SMTP_FROM_ADDRESS') ? SMTP_FROM_ADDRESS : '')) . "\r\n";
        $headers .= "Reply-To: " . ($config['email_address'] ?? '') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        foreach ($recipients as $recipient) {
            $result = mail($recipient, $subject, $body, $headers);
            if (!$result) {
                throw new Exception("Failed to send email to $recipient");
            }
        }
    }

    /* ==================== LOGGING ==================== */
    private function logEmail($campaignId, $status, $message) {
        $stmt = $this->db->prepare("\n            INSERT INTO email_logs (campaign_id, status, message)\n            VALUES (:campaign_id, :status, :message)\n        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':status' => $status,
            ':message' => $message
        ]);
    }

    public function getEmailLogs($campaignId = null) {
        if ($campaignId) {
            $stmt = $this->db->prepare("SELECT * FROM email_logs WHERE campaign_id = ? ORDER BY sent_at DESC LIMIT 50");
            $stmt->execute([$campaignId]);
        } else {
            $stmt = $this->db->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 50");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
