<?php
/**
 * Email Diagnostic Tool — Run directly: https://khatibangla.com/email-diag.php
 * DELETE THIS FILE after testing!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Email Diagnostic</title><style>
body{font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;font-size:13px;line-height:1.8}
.ok{color:#00ff88}.fail{color:#ff4444}.warn{color:#ffaa00}.info{color:#66bbff}
h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px}
</style></head><body>";

echo "<h1>📧 Email Diagnostic — khatibangla.com</h1>";
echo "<p class='info'>Time: " . date('Y-m-d H:i:s') . " | PHP " . phpversion() . "</p><hr>";

// ─── 1. PHP mail() function ───
echo "<h2>1. PHP mail() Function</h2>";
echo "mail() exists: " . (function_exists('mail') ? "<span class='ok'>✅ YES</span>" : "<span class='fail'>❌ NO</span>") . "<br>";
echo "sendmail_path: <span class='info'>" . (ini_get('sendmail_path') ?: '(not set)') . "</span><br>";
echo "SMTP (php.ini): <span class='info'>" . ini_get('SMTP') . ":" . ini_get('smtp_port') . "</span><br>";

// Check if sendmail binary exists
$sendmailPaths = ['/usr/sbin/sendmail', '/usr/lib/sendmail', '/usr/bin/sendmail'];
foreach ($sendmailPaths as $sp) {
    echo "  {$sp}: " . (file_exists($sp) ? "<span class='ok'>✅ exists</span>" : "<span class='fail'>❌ not found</span>") . "<br>";
}

// ─── 2. Postfix status ───
echo "<h2>2. Mail Server (Postfix)</h2>";
$postfixStatus = @shell_exec('systemctl is-active postfix 2>&1');
echo "Postfix status: " . (trim($postfixStatus) === 'active' ? "<span class='ok'>✅ active</span>" : "<span class='warn'>⚠️ " . trim($postfixStatus ?: 'unknown') . "</span>") . "<br>";

$postfixCheck = @shell_exec('which postfix 2>&1');
echo "Postfix binary: <span class='info'>" . trim($postfixCheck ?: 'not found') . "</span><br>";

// ─── 3. Port connectivity ───
echo "<h2>3. SMTP Port Connectivity</h2>";
$targets = [
    ['localhost', 25, false, 'Postfix local (no SSL)'],
    ['localhost', 587, false, 'Submission local'],
    ['localhost', 465, true, 'SMTPS local'],
    ['127.0.0.1', 25, false, 'Postfix 127.0.0.1'],
    ['mail.menzio.store', 25, false, 'Mail MX:25'],
    ['mail.menzio.store', 587, false, 'Mail MX:587'],
    ['mail.menzio.store', 465, true, 'Mail MX:465 (SSL)'],
];

$workingHost = null;
$workingPort = null;
$workingEnc = null;

foreach ($targets as [$host, $port, $ssl, $label]) {
    $prefix = $ssl ? 'ssl://' : '';
    $s = @fsockopen($prefix . $host, $port, $errno, $errstr, 3);
    if ($s) {
        $banner = @fgets($s, 512);
        echo "<span class='ok'>✅ {$label} ({$host}:{$port})</span> — " . trim($banner) . "<br>";
        if (!$workingHost) {
            $workingHost = $host;
            $workingPort = $port;
            $workingEnc = $ssl ? 'ssl' : ($port == 587 ? 'tls' : 'none');
        }
        @fclose($s);
    } else {
        echo "<span class='fail'>❌ {$label} ({$host}:{$port})</span> — {$errstr}<br>";
    }
}

// ─── 4. SMTP AUTH test ───
echo "<h2>4. SMTP Authentication Test</h2>";
if ($workingHost) {
    echo "<span class='ok'>Best working endpoint: {$workingHost}:{$workingPort}</span><br>";
    
    // Try AUTH with noreply@menzio.store
    $prefix = ($workingEnc === 'ssl') ? 'ssl://' : '';
    $s = @fsockopen($prefix . $workingHost, $workingPort, $errno, $errstr, 5);
    if ($s) {
        stream_set_timeout($s, 10);
        $banner = @fgets($s, 512);
        echo "Banner: " . trim($banner) . "<br>";
        
        fwrite($s, "EHLO localhost\r\n");
        $caps = '';
        while ($line = @fgets($s, 512)) {
            $caps .= $line;
            echo "  EHLO: " . trim($line) . "<br>";
            if (substr($line, 3, 1) === ' ') break;
        }
        
        $hasStarttls = stripos($caps, 'STARTTLS') !== false;
        $hasAuth = stripos($caps, 'AUTH') !== false;
        echo "<br>STARTTLS: " . ($hasStarttls ? "<span class='ok'>✅ supported</span>" : "<span class='warn'>not advertised</span>") . "<br>";
        echo "AUTH: " . ($hasAuth ? "<span class='ok'>✅ supported</span>" : "<span class='warn'>not advertised (may relay without auth)</span>") . "<br>";
        
        // Try STARTTLS if available and we're not already SSL
        if ($hasStarttls && $workingEnc !== 'ssl') {
            fwrite($s, "STARTTLS\r\n");
            $tlsResp = @fgets($s, 512);
            echo "STARTTLS response: " . trim($tlsResp) . "<br>";
            
            if (substr($tlsResp, 0, 3) === '220') {
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                stream_context_set_option($s, 'ssl', 'verify_peer', false);
                stream_context_set_option($s, 'ssl', 'verify_peer_name', false);
                stream_context_set_option($s, 'ssl', 'allow_self_signed', true);
                
                $tlsOk = @stream_socket_enable_crypto($s, true, $crypto);
                echo "TLS handshake: " . ($tlsOk ? "<span class='ok'>✅ success</span>" : "<span class='fail'>❌ failed</span>") . "<br>";
                
                if ($tlsOk) {
                    fwrite($s, "EHLO localhost\r\n");
                    while ($line = @fgets($s, 512)) {
                        if (substr($line, 3, 1) === ' ') break;
                    }
                }
            }
        }
        
        @fclose($s);
    }
} else {
    echo "<span class='fail'>❌ No working SMTP endpoint found!</span><br>";
}

// ─── 5. PHP mail() test ───
echo "<h2>5. PHP mail() Direct Test</h2>";
$testTo = 'test@test.com';
$headers = "From: noreply@menzio.store\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$errorMsg = '';
set_error_handler(function($errno, $errstr) use (&$errorMsg) {
    $errorMsg .= $errstr;
    return true;
});

$mailResult = @mail($testTo, 'Diagnostic Test', 'Test from email-diag.php at ' . date('r'), $headers);
restore_error_handler();

echo "mail() returned: " . ($mailResult ? "<span class='ok'>✅ true</span>" : "<span class='fail'>❌ false</span>") . "<br>";
if ($errorMsg) echo "Error: <span class='fail'>{$errorMsg}</span><br>";

// ─── 6. Send real test email ───
if (isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $realTo = $_GET['to'];
    echo "<h2>6. 📨 Sending REAL test to: {$realTo}</h2>";
    
    // Method A: PHP mail()
    echo "<br><strong>Method A: PHP mail()</strong><br>";
    $headersReal = "From: noreply@menzio.store\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    $bodyReal = "<h2>✅ PHP mail() test successful!</h2><p>Sent at: " . date('r') . "</p>";
    
    $errorA = '';
    set_error_handler(function($errno, $errstr) use (&$errorA) { $errorA .= $errstr; return true; });
    $resultA = @mail($realTo, 'Test A: PHP mail() — menzio.store', $bodyReal, $headersReal);
    restore_error_handler();
    echo "Result: " . ($resultA ? "<span class='ok'>✅ Queued</span>" : "<span class='fail'>❌ Failed</span>");
    if ($errorA) echo " — <span class='fail'>{$errorA}</span>";
    echo "<br>";
    
    // Method B: Direct SMTP to working endpoint
    if ($workingHost) {
        echo "<br><strong>Method B: Direct SMTP ({$workingHost}:{$workingPort})</strong><br>";
        $prefix = ($workingEnc === 'ssl') ? 'ssl://' : '';
        $s = @fsockopen($prefix . $workingHost, $workingPort, $errno, $errstr, 10);
        if ($s) {
            stream_set_timeout($s, 15);
            $r = @fgets($s, 512); echo "< " . trim($r) . "<br>";
            
            fwrite($s, "EHLO localhost\r\n");
            $caps = '';
            while ($line = @fgets($s, 512)) {
                $caps .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            echo "< EHLO OK<br>";
            
            // STARTTLS if available
            if (stripos($caps, 'STARTTLS') !== false && $workingEnc !== 'ssl') {
                fwrite($s, "STARTTLS\r\n");
                $r = @fgets($s, 512); echo "< STARTTLS: " . trim($r) . "<br>";
                stream_context_set_option($s, 'ssl', 'verify_peer', false);
                stream_context_set_option($s, 'ssl', 'verify_peer_name', false);
                stream_context_set_option($s, 'ssl', 'allow_self_signed', true);
                @stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                fwrite($s, "EHLO localhost\r\n");
                while ($line = @fgets($s, 512)) { if (substr($line, 3, 1) === ' ') break; }
            }
            
            // Try AUTH if the server supports it
            if (stripos($caps, 'AUTH') !== false) {
                fwrite($s, "AUTH LOGIN\r\n");
                $r = @fgets($s, 512); echo "< AUTH: " . trim($r) . "<br>";
                if (substr($r, 0, 3) === '334') {
                    fwrite($s, base64_encode('noreply@menzio.store') . "\r\n");
                    $r = @fgets($s, 512); echo "< User: " . trim($r) . "<br>";
                    // We don't have password here — skip AUTH
                    echo "<span class='warn'>⚠️ Need password for AUTH — skipping, trying without auth</span><br>";
                    @fclose($s);
                    
                    // Reconnect without auth
                    $s = @fsockopen($prefix . $workingHost, $workingPort, $errno, $errstr, 10);
                    if ($s) {
                        stream_set_timeout($s, 15);
                        @fgets($s, 512);
                        fwrite($s, "EHLO localhost\r\n");
                        while ($line = @fgets($s, 512)) { if (substr($line, 3, 1) === ' ') break; }
                    }
                }
            }
            
            if ($s) {
                fwrite($s, "MAIL FROM:<noreply@menzio.store>\r\n");
                $r = @fgets($s, 512); echo "< MAIL FROM: " . trim($r) . "<br>";
                
                fwrite($s, "RCPT TO:<{$realTo}>\r\n");
                $r = @fgets($s, 512); echo "< RCPT TO: " . trim($r) . "<br>";
                
                if (substr($r, 0, 3) === '250') {
                    fwrite($s, "DATA\r\n");
                    $r = @fgets($s, 512); echo "< DATA: " . trim($r) . "<br>";
                    
                    $msg = "From: noreply@menzio.store\r\n";
                    $msg .= "To: {$realTo}\r\n";
                    $msg .= "Subject: Test B: Direct SMTP — menzio.store\r\n";
                    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $msg .= "\r\n";
                    $msg .= "<h2>✅ Direct SMTP test successful!</h2><p>Sent via {$workingHost}:{$workingPort} at " . date('r') . "</p>\r\n";
                    $msg .= ".\r\n";
                    
                    fwrite($s, $msg);
                    $r = @fgets($s, 512); echo "< SEND: " . trim($r) . "<br>";
                    echo (substr($r, 0, 3) === '250') ? "<span class='ok'>✅ EMAIL SENT!</span>" : "<span class='fail'>❌ Send failed</span>";
                } else {
                    echo "<span class='fail'>❌ Relay denied — server requires AUTH</span>";
                }
                
                fwrite($s, "QUIT\r\n");
                @fclose($s);
            }
        } else {
            echo "<span class='fail'>❌ Connect failed: {$errstr}</span>";
        }
    }
    echo "<br>";
} else {
    echo "<h2>6. Send Real Test Email</h2>";
    echo "<p>Add <span class='info'>?to=your@email.com</span> to URL to send a real test email.</p>";
    echo "<p>Example: <span class='info'>https://khatibangla.com/email-diag.php?to=your@gmail.com</span></p>";
}

// ─── 7. Recommendations ───
echo "<h2>7. Recommendations</h2>";
if ($workingHost) {
    echo "<p class='ok'>✅ Working SMTP endpoint found: {$workingHost}:{$workingPort}</p>";
    echo "<p>In Admin → Settings → Email/SMTP, use:</p>";
    echo "<pre style='background:#222;padding:10px;border-radius:5px;color:#0f0'>";
    echo "SMTP Host:       {$workingHost}\n";
    echo "SMTP Port:       {$workingPort}\n";
    echo "Encryption:      {$workingEnc}\n";
    echo "Username:        noreply@menzio.store\n";
    echo "Password:        (your CyberPanel email password)\n";
    echo "</pre>";
} else {
    echo "<p class='fail'>❌ No SMTP port is open. Check CyberPanel → Server Status → Postfix is running.</p>";
}

echo "<hr><p class='warn'>⚠️ DELETE this file after testing: /public_html/email-diag.php</p>";
echo "</body></html>";
