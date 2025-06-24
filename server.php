<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Security and configuration settings
define('LOG_DIR', 'logs');
define('MAX_LOG_FILES', 1000); // Prevent unlimited file growth
define('MAX_LOG_SIZE', 5242880); // 5MB max log file size

// Create secure logs directory
if (!file_exists(LOG_DIR)) {
    if (!mkdir(LOG_DIR, 0750, true)) {
        logError('Failed to create log directory');
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Server configuration error']));
    }
}

// Validate JSON input
$json = file_get_contents("php://input");
if (empty($json)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'No input data']));
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid JSON format']));
}

// Validate required location data
if (!isset($data['latitude']) || !isset($data['longitude'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing location data']));
}

// Sanitize and validate coordinates
$latitude = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
$longitude = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);

if ($latitude === false || $longitude === false || 
    $latitude < -90 || $latitude > 90 || 
    $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid coordinates']));
}

// Prepare log data with sanitized values
$locationData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'latitude' => $latitude,
    'longitude' => $longitude,
    'accuracy' => isset($data['accuracy']) ? filter_var($data['accuracy'], FILTER_VALIDATE_FLOAT) : null,
    'address' => isset($data['address']) ? htmlspecialchars($data['address']) : null,
    'additional_data' => isset($data['additional_data']) ? filter_var_array($data['additional_data'], FILTER_SANITIZE_STRING) : null
];

// Rotate logs if they get too large
manageLogRotation();

try {
    // Save individual JSON log
    $filename = LOG_DIR . '/location_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($filename, json_encode($locationData, JSON_PRETTY_PRINT));
    
    // Append to master log with locking
    $masterLog = LOG_DIR . '/all_locations.log';
    $logEntry = sprintf(
        "[%s] IP: %s | Coordinates: %.6f,%.6f | Accuracy: %sm | Address: %s\n",
        $locationData['timestamp'],
        $locationData['ip'],
        $locationData['latitude'],
        $locationData['longitude'],
        $locationData['accuracy'] ?? 'N/A',
        $locationData['address'] ?? 'N/A'
    );
    
    file_put_contents($masterLog, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Location saved',
        'map_link' => generateMapLink($latitude, $longitude)
    ]);
    
} catch (Exception $e) {
    logError('Failed to save location: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
}

// Helper functions
function manageLogRotation() {
    // Rotate master log if too large
    $masterLog = LOG_DIR . '/all_locations.log';
    if (file_exists($masterLog) && filesize($masterLog) > MAX_LOG_SIZE) {
        rename($masterLog, LOG_DIR . '/all_locations_' . date('Y-m-d_His') . '.log');
    }
    
    // Limit number of individual log files
    $files = glob(LOG_DIR . '/location_*.json');
    if (count($files) > MAX_LOG_FILES) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        for ($i = 0; $i < count($files) - MAX_LOG_FILES; $i++) {
            @unlink($files[$i]);
        }
    }
}

function generateMapLink($lat, $lng) {
    return sprintf(
        'https://www.google.com/maps/search/?api=1&query=%s,%s',
        urlencode($lat),
        urlencode($lng)
    );
}

function logError($message) {
    $errorLog = LOG_DIR . '/errors.log';
    $errorEntry = sprintf("[%s] ERROR: %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents($errorLog, $errorEntry, FILE_APPEND | LOCK_EX);
}
?>