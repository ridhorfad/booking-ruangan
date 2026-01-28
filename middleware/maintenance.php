<?php
/**
 * =========================================================
 * MAINTENANCE MODE MIDDLEWARE
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../helpers/settings.php';
require_once __DIR__ . '/../helpers/audit.php';

// status maintenance
$maintenance = getSetting('maintenance_mode', 'off');

// role user
$role = $_SESSION['user']['role'] ?? null;

// ================== SAAT MAINTENANCE ON ==================
if ($maintenance === 'on' && $role !== 'superadmin') {

    // simpan halaman tujuan (sekali saja)
    if (!isset($_SESSION['intended_url'])) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    }

    // audit
    audit_log(
        'MAINTENANCE_BLOCK',
        'Akses diblokir karena maintenance mode aktif'
    );

    header('Location: /booking-ruangan/maintenance.php');
    exit;
}

// ================== SAAT MAINTENANCE OFF ==================
if ($maintenance === 'off' && isset($_SESSION['intended_url'])) {

    $redirect = $_SESSION['intended_url'];
    unset($_SESSION['intended_url']);

    header("Location: $redirect");
    exit;
}
