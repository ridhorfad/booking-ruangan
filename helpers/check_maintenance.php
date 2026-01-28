<?php
require_once __DIR__ . '/settings.php';

header('Content-Type: application/json');

$maintenance = getSetting('maintenance_mode', 'off');

echo json_encode([
    'maintenance' => $maintenance
]);
