<?php
/**
 * Security Middleware — Loaded on every request
 * Provides: Firewall, Rate Limiting, SQLi/XSS protection, Security Headers, Session Protection
 */

class SecurityGuard {
    private $db;
    private $ip;
    private $uri;
    private $method;
    private $settings = [];
    
    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->ip = $this->getClientIp();
            $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
            $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $this->loadSettings();
        } catch (\Throwable $e) {
            // Fail open — don't break the site if security tables don't exist yet
            return;
        }
    }
    
    private function loadSettings() {
        static $cache = null;
        if ($cache !== null) { $this->settings = $cache; return; }
        try {
            $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'sec_%'");
            foreach ($rows as $r) $this->settings[$r['setting_key']] = $r['setting_value'];
            $cache = $this->settings;
        } catch (\Throwable $e) {}
    }
    
    private function s($key, $default = '') {
        return $this->settings["sec_{$key}"] ?? $default;
    }
    
    public function run() {
        try {
            if ($this->s('firewall_enabled', '1') !== '1') return;
            $this->setSecurityHeaders();
            $this->checkIpRules();
            $this->checkRateLimit();
            $this->scanRequest();
            $this->sessionProtection();
        } catch (\Throwable $e) {
            // Fail open
        }
    }
    
    // ── Security Headers ──
    private function setSecurityHeaders() {
        if ($this->s('security_headers', '1') !== '1') return;
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        
        if ($this->s('force_https', '1') === '1' && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        $csp = $this->s('content_security_policy', '');
        if ($csp) header('Content-Security-Policy: ' . $csp);
    }
    
    // ── IP Firewall ──
    private function checkIpRules() {
        try {
            $rule = $this->db->fetch(
                "SELECT * FROM security_ip_rules WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())",
                [$this->ip]
            );
            if ($rule) {
                $this->db->query("UPDATE security_ip_rules SET hit_count = hit_count + 1 WHERE id = ?", [$rule['id']]);
                if ($rule['rule_type'] === 'block') {
                    $this->logEvent('ip_blocked', 'high', "Blocked IP: {$this->ip}", true);
                    http_response_code(403);
                    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px"><h1>403 Forbidden</h1><p>Your IP address has been blocked.</p></body></html>';
                    exit;
                }
                // 'watch' type — log but allow
                if ($rule['rule_type'] === 'watch') {
                    $this->logEvent('ip_watched', 'low', "Watched IP accessed: {$this->ip}");
                }
            }
        } catch (\Throwable $e) {}
    }
    
    // ── Rate Limiting ──
    private function checkRateLimit() {
        if ($this->s('rate_limit_enabled', '1') !== '1') return;
        
        $maxReq = intval($this->s('rate_limit_requests', '60'));
        $window = intval($this->s('rate_limit_window', '60'));
        $identifier = $this->ip;
        
        try {
            // Clean old entries
            $this->db->query("DELETE FROM security_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)", [$window * 2]);
            
            $current = $this->db->fetch(
                "SELECT id, request_count, window_start FROM security_rate_limits WHERE identifier = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$identifier, $window]
            );
            
            if ($current) {
                $newCount = $current['request_count'] + 1;
                $this->db->query("UPDATE security_rate_limits SET request_count = ? WHERE id = ?", [$newCount, $current['id']]);
                
                if ($newCount > $maxReq) {
                    $this->logEvent('rate_limit', 'high', "Rate limit exceeded: {$newCount}/{$maxReq} in {$window}s", true);
                    
                    // Auto-block if threshold hit
                    $threshold = intval($this->s('auto_block_threshold', '10'));
                    if ($newCount > $maxReq * $threshold) {
                        $this->autoBlockIp("Rate limit exceeded {$threshold}x");
                    }
                    
                    http_response_code(429);
                    header('Retry-After: ' . $window);
                    echo json_encode(['error' => 'Too many requests. Please slow down.']);
                    exit;
                }
            } else {
                $this->db->insert('security_rate_limits', [
                    'identifier' => $identifier,
                    'endpoint' => parse_url($this->uri, PHP_URL_PATH),
                    'request_count' => 1,
                ]);
            }
        } catch (\Throwable $e) {}
    }
    
    // ── Request Scanning (SQLi, XSS, Path Traversal) ──
    private function scanRequest() {
        $allInput = array_merge($_GET, $_POST);
        $inputStr = implode(' ', array_values(array_map('strval', $this->flattenArray($allInput))));
        
        if ($this->s('sqli_protection', '1') === '1') {
            $sqliPatterns = [
                '/(\bunion\b.*\bselect\b)/i',
                '/(\bselect\b.*\bfrom\b.*\bwhere\b)/i',
                '/(\'|\");\s*(drop|alter|truncate|delete|insert|update)\s/i',
                '/(\bor\b|\band\b)\s+[\d\'\"]+\s*=\s*[\d\'\"]+/i',
                '/(sleep|benchmark|load_file|into\s+outfile|into\s+dumpfile)\s*\(/i',
                '/(\bexec\b|\bexecute\b)\s*(sp_|xp_)/i',
                '/0x[0-9a-f]{8,}/i',
                '/(information_schema|mysql\.user|sys\.)/i',
            ];
            foreach ($sqliPatterns as $pattern) {
                if (preg_match($pattern, $inputStr)) {
                    $this->logEvent('sql_injection', 'critical', 'SQLi detected: ' . mb_substr($inputStr, 0, 300), true);
                    $this->createBreachAlert('SQL Injection Attempt', "Suspicious SQL pattern detected from IP: {$this->ip}");
                    $this->autoBlockIp('SQL injection attempt');
                    http_response_code(403);
                    exit('Forbidden');
                }
            }
        }
        
        if ($this->s('xss_protection', '1') === '1') {
            $xssPatterns = [
                '/<script[\s>]/i',
                '/javascript\s*:/i',
                '/on(error|load|click|mouseover|focus|blur|submit|change)\s*=/i',
                '/<(iframe|object|embed|applet|form|base|link|meta|svg|math)/i',
                '/eval\s*\(/i',
                '/document\.(cookie|write|location)/i',
                '/window\.(location|open)/i',
            ];
            // Skip XSS check for admin settings/CMS saves (they legitimately contain HTML)
            $isAdminSave = strpos($this->uri, '/admin/') !== false && $this->method === 'POST';
            if (!$isAdminSave) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $inputStr)) {
                        $this->logEvent('xss_attempt', 'high', 'XSS attempt: ' . mb_substr($inputStr, 0, 300), true);
                        $this->createBreachAlert('XSS Attack Attempt', "Cross-site scripting attempt from IP: {$this->ip}");
                        http_response_code(403);
                        exit('Forbidden');
                    }
                }
            }
        }
        
        // Path traversal + File Inclusion + Null bytes + SSRF
        $pathTraversal = ['../', '..\\', '%2e%2e', '%252e%252e', '/etc/passwd', '/proc/self', 'wp-admin', 'wp-login', 'wp-content'];
        foreach ($pathTraversal as $pt) {
            if (stripos($this->uri, $pt) !== false || stripos($inputStr, $pt) !== false) {
                $this->logEvent('path_traversal', 'high', "Path traversal: {$this->uri}", true);
                http_response_code(403);
                exit('Forbidden');
            }
        }
        
        // Null byte injection
        if (strpos($this->uri, '%00') !== false || strpos($inputStr, "\0") !== false || strpos($inputStr, '%00') !== false) {
            $this->logEvent('null_byte', 'critical', "Null byte injection: {$this->uri}", true);
            http_response_code(403);
            exit('Forbidden');
        }
        
        // Remote File Inclusion (RFI) / SSRF via user input
        if ($this->s('sqli_protection', '1') === '1') {
            $rfiPatterns = [
                '/https?:\/\/[^\s]+\.(php|phtml|txt|inc)/i',
                '/php:\/\/input/i',
                '/php:\/\/filter/i',
                '/data:\/\/text/i',
                '/expect:\/\//i',
            ];
            foreach ($rfiPatterns as $rfi) {
                if (preg_match($rfi, $inputStr)) {
                    $this->logEvent('rfi_attempt', 'critical', 'RFI/SSRF attempt: ' . mb_substr($inputStr, 0, 300), true);
                    $this->createBreachAlert('Remote File Inclusion', "RFI/SSRF attempt from IP: {$this->ip}");
                    http_response_code(403);
                    exit('Forbidden');
                }
            }
        }
        
        // Command injection patterns
        $cmdPatterns = ['/;\s*(cat|ls|wget|curl|bash|sh|nc|netcat|python|perl|ruby|php)\s/i', '/\|\s*(cat|ls|id|whoami|uname|pwd)/i', '/`[^`]+`/'];
        foreach ($cmdPatterns as $cp) {
            if (preg_match($cp, $inputStr)) {
                $this->logEvent('cmd_injection', 'critical', 'Command injection: ' . mb_substr($inputStr, 0, 200), true);
                $this->createBreachAlert('Command Injection', "CMD injection attempt from IP: {$this->ip}");
                http_response_code(403);
                exit('Forbidden');
            }
        }
        
        // Bad bot blocking
        if ($this->s('block_bad_bots', '1') === '1') {
            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            $badBots = ['sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'nuclei', 'dirbuster', 'gobuster', 'wfuzz', 'hydra', 'burpsuite', 'nessus', 'acunetix', 'havij', 'w3af', 'skipfish'];
            foreach ($badBots as $bot) {
                if (strpos($ua, $bot) !== false) {
                    $this->logEvent('bad_bot', 'high', "Blocked scanner: {$bot}", true);
                    $this->autoBlockIp("Scanner detected: {$bot}");
                    http_response_code(403);
                    exit;
                }
            }
        }
    }
    
    // ── Session Protection ──
    private function sessionProtection() {
        if ($this->s('session_protection', '1') !== '1') return;
        
        // Set secure cookie params before session starts (if not already active)
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        
        // Session fixation prevention
        if (!isset($_SESSION['_sec_created'])) {
            $_SESSION['_sec_created'] = time();
            $_SESSION['_sec_ip'] = $this->ip;
            $_SESSION['_sec_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // IP change detection (soft — log but don't kill session)
        if (isset($_SESSION['_sec_ip']) && $_SESSION['_sec_ip'] !== $this->ip) {
            $this->logEvent('session_ip_change', 'medium', "Session IP changed: {$_SESSION['_sec_ip']} → {$this->ip}");
            $_SESSION['_sec_ip'] = $this->ip;
            session_regenerate_id(true);
            $_SESSION['_sec_created'] = time();
        }
        
        // User-Agent change detection (likely session hijacking)
        $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (isset($_SESSION['_sec_ua']) && $_SESSION['_sec_ua'] !== '' && $_SESSION['_sec_ua'] !== $currentUa) {
            $this->logEvent('session_ua_change', 'high', "Session UA changed — possible hijack attempt", true);
            $_SESSION['_sec_ua'] = $currentUa;
            session_regenerate_id(true);
            $_SESSION['_sec_created'] = time();
        }
        
        // Regenerate session ID every 30 minutes
        if (time() - ($_SESSION['_sec_created'] ?? 0) > 1800) {
            session_regenerate_id(true);
            $_SESSION['_sec_created'] = time();
        }
        
        // Session timeout
        $timeout = intval($this->s('session_timeout_minutes', '120')) * 60;
        if (isset($_SESSION['_sec_last_active']) && (time() - $_SESSION['_sec_last_active'] > $timeout)) {
            session_destroy();
            return;
        }
        $_SESSION['_sec_last_active'] = time();
    }
    
    // ── Brute Force Check (called from login handler) ──
    public function checkBruteForce($username = '') {
        if ($this->s('brute_force_enabled', '1') !== '1') return true;
        
        $maxAttempts = intval($this->s('brute_force_max_attempts', '5'));
        $lockoutMin = intval($this->s('brute_force_lockout_minutes', '30'));
        
        try {
            $recentFails = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM security_login_attempts WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$this->ip, $lockoutMin]
            );
            
            if (intval($recentFails['cnt'] ?? 0) >= $maxAttempts) {
                $this->logEvent('brute_force', 'critical', "Brute force lockout: {$recentFails['cnt']} failed attempts for '{$username}'", true);
                $this->createBreachAlert('Brute Force Attack', "IP {$this->ip} locked out after {$recentFails['cnt']} failed login attempts");
                return false;
            }
        } catch (\Throwable $e) {}
        return true;
    }
    
    public function logLoginAttempt($username, $success) {
        try {
            $this->db->insert('security_login_attempts', [
                'ip_address' => $this->ip,
                'username' => mb_substr($username, 0, 100),
                'success' => $success ? 1 : 0,
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
            if (!$success) {
                $this->logEvent('login_failed', 'medium', "Failed login for '{$username}'");
            } else {
                $this->logEvent('admin_login', 'low', "Successful login for '{$username}'");
                // Clear failed attempts on successful login
                $this->db->query("DELETE FROM security_login_attempts WHERE ip_address = ? AND success = 0", [$this->ip]);
            }
        } catch (\Throwable $e) {}
    }
    
    // ── File Upload Validation ──
    public function validateUpload($file, $customAllowed = null) {
        if ($this->s('file_upload_scan', '1') !== '1') return true;
        
        $allowed = $customAllowed ?: explode(',', $this->s('allowed_upload_types', 'jpg,jpeg,png,gif,webp,pdf'));
        $maxSize = intval($this->s('max_upload_size_mb', '10')) * 1024 * 1024;
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($ext, $allowed)) {
            $this->logEvent('file_upload_blocked', 'high', "Blocked upload: .{$ext} not allowed - {$file['name']}", true);
            return 'File type not allowed: .' . $ext;
        }
        
        // Check size
        if ($file['size'] > $maxSize) {
            return 'File too large. Max: ' . $this->s('max_upload_size_mb', '10') . 'MB';
        }
        
        // Check for PHP in file content (malware scan)
        $content = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        $dangerousPatterns = ['<?php', '<?=', '<script', 'eval(', 'exec(', 'system(', 'passthru(', 'shell_exec(', 'base64_decode('];
        foreach ($dangerousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $this->logEvent('malware_detected', 'critical', "Malicious file blocked: {$file['name']} contains '{$pattern}'", true);
                $this->createBreachAlert('Malware Upload Blocked', "Malicious file '{$file['name']}' from IP: {$this->ip} contained: {$pattern}");
                return 'File contains potentially malicious content';
            }
        }
        
        // MIME type verification for images
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $validMimes = ['image/jpeg','image/png','image/gif','image/webp'];
            if (!in_array($mime, $validMimes)) {
                $this->logEvent('file_upload_blocked', 'high', "MIME mismatch: {$ext} but detected {$mime}", true);
                return 'File type does not match content';
            }
        }
        
        return true;
    }
    
    // ── Helpers ──
    private function logEvent($type, $severity, $payload, $blocked = false) {
        try {
            $this->db->insert('security_logs', [
                'event_type' => $type,
                'severity' => $severity,
                'ip_address' => $this->ip,
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'request_uri' => mb_substr($this->uri, 0, 500),
                'request_method' => $this->method,
                'payload' => mb_substr($payload, 0, 1000),
                'blocked' => $blocked ? 1 : 0,
                'admin_user_id' => $_SESSION['admin_id'] ?? null,
                'session_id' => session_id() ?: null,
            ]);
        } catch (\Throwable $e) {}
    }
    
    private function createBreachAlert($title, $message) {
        if ($this->s('breach_alerts', '1') !== '1') return;
        try {
            // Avoid duplicate alerts within 5 minutes
            $existing = $this->db->fetch(
                "SELECT id FROM notifications WHERE type = 'security_breach' AND title = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                [$title]
            );
            if ($existing) return;
            
            $this->db->insert('notifications', [
                'type' => 'security_breach',
                'title' => '🛡️ ' . $title,
                'message' => $message . ' at ' . date('H:i:s'),
                'link' => 'pages/security.php',
                'is_read' => 0,
                'user_id' => null,
            ]);
        } catch (\Throwable $e) {}
    }
    
    private function autoBlockIp($reason) {
        try {
            $this->db->query(
                "INSERT INTO security_ip_rules (ip_address, rule_type, reason, expires_at) VALUES (?, 'block', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE reason = VALUES(reason), hit_count = hit_count + 1, expires_at = GREATEST(expires_at, DATE_ADD(NOW(), INTERVAL 24 HOUR))",
                [$this->ip, mb_substr($reason, 0, 255)]
            );
        } catch (\Throwable $e) {}
    }
    
    public function getClientIp() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = explode(',', $_SERVER[$h])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function flattenArray($arr, $prefix = '') {
        $result = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) $result = array_merge($result, $this->flattenArray($v, $prefix . $k . '.'));
            else $result[$prefix . $k] = (string)$v;
        }
        return $result;
    }
}

// Auto-run security guard
try {
    $GLOBALS['_securityGuard'] = new SecurityGuard();
    $GLOBALS['_securityGuard']->run();
} catch (\Throwable $e) {
    // Fail open
}

function getSecurityGuard() {
    return $GLOBALS['_securityGuard'] ?? null;
}
