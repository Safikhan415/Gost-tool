<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

define('ACCESS_LOG_DIR', 'access_logs');
define('MAX_LOG_FILES', 500);

// Create access logs directory if not exists
if (!file_exists(ACCESS_LOG_DIR)) {
    if (!mkdir(ACCESS_LOG_DIR, 0750, true)) {
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Failed to create access log directory']));
    }
}

// Get client information
$clientData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
    'method' => $_SERVER['REQUEST_METHOD'],
    'requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
];

// Additional device fingerprinting
$clientData['device'] = [
    'platform' => parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '')['platform'],
    'browser' => parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '')['browser'],
    'is_mobile' => is_mobile($_SERVER['HTTP_USER_AGENT'] ?? '')
];

// Save the access log
$logFilename = ACCESS_LOG_DIR . '/access_' . date('Y-m-d') . '.log';
file_put_contents($logFilename, json_encode($clientData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND | LOCK_EX);

// Rotate logs if needed
if (filesize($logFilename) > 5 * 1024 * 1024) { // 5MB
    $rotatedFilename = ACCESS_LOG_DIR . '/access_' . date('Y-m-d_His') . '.log';
    rename($logFilename, $rotatedFilename);
}

// Helper functions
function parse_user_agent($ua) {
    $platform = 'Unknown';
    $browser = 'Unknown';
    
    if (preg_match('/linux/i', $ua)) $platform = 'Linux';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac';
    elseif (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';
    
    if (preg_match('/MSIE/i', $ua) && !preg_match('/Opera/i', $ua)) $browser = 'IE';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Opera/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Netscape/i', $ua)) $browser = 'Netscape';
    
    return ['platform' => $platform, 'browser' => $browser];
}

function is_mobile($ua) {
    return preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone/i', $ua);
}

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Access logged',
    'data' => [
        'logged_at' => $clientData['timestamp'],
        'request_id' => bin2hex(random_bytes(4))
    ]
]);
?>