<?php
// Function to safely get file contents or return empty array
function getJsonData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

// Location logs
$locationLogs = glob('logs/*.json');s
usort($locationLogs, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Access logs
$accessLogs = glob('access_logs/*.log');
usort($accessLogs, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html>
<head>
    <title>Location Logs</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        tr:hover { background-color: #f5f5f5; }
        h1, h2 { margin: 20px 0 10px; }
    </style>
</head>
<body>
    <h1>Location Data Logs</h1>
    <?php if (empty($locationLogs)): ?>
        <p>No location logs found.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Timestamp</th>
                <th>Coordinates</th>
                <th>Accuracy</th>
                <th>IP Address</th>
                <th>Map Link</th>
            </tr>
            <?php foreach ($locationLogs as $log): 
                $data = getJsonData($log);
                if (empty($data['coordinates'])) continue;
                $coords = $data['coordinates'];
            ?>
            <tr>
                <td><?= htmlspecialchars($data['timestamp'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($coords['lat'] ?? '') ?>, <?= htmlspecialchars($coords['lng'] ?? '') ?></td>
                <td><?= htmlspecialchars($coords['accuracy'] ?? '') ?> meters</td>
                <td><?= htmlspecialchars($data['ip'] ?? 'Unknown') ?></td>
                <td>
                    <?php if (!empty($coords['lat']) && !empty($coords['lng'])): ?>
                        <a href="https://maps.google.com/?q=<?= $coords['lat'] ?>,<?= $coords['lng'] ?>" target="_blank">View on Map</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h1>Access Logs</h1>
    <?php if (empty($accessLogs)): ?>
        <p>No access logs found.</p>
    <?php else: ?>
        <?php foreach ($accessLogs as $log): ?>
            <h2><?= htmlspecialchars(basename($log)) ?></h2>
            <?php
            $entries = file_exists($log) ? file($log, FILE_IGNORE_NEW_LINES) : [];
            if (empty($entries)) {
                echo "<p>No entries in this log file.</p>";
                continue;
            }
            ?>
            <table>
                <tr>
                    <th>Timestamp</th>
                    <th>IP</th>
                    <th>Device</th>
                    <th>Browser</th>
                    <th>Platform</th>
                </tr>
                <?php foreach ($entries as $entry): 
                    $data = json_decode($entry, true) ?: [];
                    if (empty($data)) continue;
                ?>
                <tr>
                    <td><?= htmlspecialchars($data['timestamp'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($data['ip'] ?? 'Unknown') ?></td>
                    <td><?= !empty($data['device']['is_mobile']) ? 'Mobile' : 'Desktop' ?></td>
                    <td><?= htmlspecialchars($data['device']['browser'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($data['device']['platform'] ?? 'Unknown') ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>