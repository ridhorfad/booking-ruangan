<?php
/**
 * Middleware: Office Hours (Jam Operasional)
 * RMBS – Room Meeting Booking System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/settings.php';

/* ===== LEWATI JIKA BELUM LOGIN ===== */
if (!isset($_SESSION['user'])) {
    return;
}

/* ===== SUPERADMIN BEBAS JAM OPERASIONAL ===== */
if ($_SESSION['user']['role'] === 'superadmin') {
    return;
}

/* ===== AMBIL SETTING SISTEM ===== */
$settings = getSystemSettings();

/* Pastikan setting tersedia */
if (
    empty($settings['office_start']) ||
    empty($settings['office_end'])
) {
    return; // tidak diblokir jika setting belum lengkap
}

/* ===== JAM SEKARANG ===== */
date_default_timezone_set('Asia/Jakarta');

$nowTime   = date('H:i');
$startTime = $settings['office_start'];
$endTime   = $settings['office_end'];

/* ===== VALIDASI JAM OPERASIONAL ===== */
$isOutsideOfficeHours = false;

/*
 * Case normal: 08:00 – 17:00
 */
if ($startTime < $endTime) {
    if ($nowTime < $startTime || $nowTime > $endTime) {
        $isOutsideOfficeHours = true;
    }
}
/*
 * Case lintas hari: 22:00 – 06:00
 */
else {
    if ($nowTime < $startTime && $nowTime > $endTime) {
        $isOutsideOfficeHours = true;
    }
}

/* ===== BLOK AKSES ===== */
if ($isOutsideOfficeHours) {

    // simpan info untuk ditampilkan
    $_SESSION['office_hours_block'] = [
        'start' => $startTime,
        'end'   => $endTime
    ];

    // arahkan ke halaman khusus
    header('Location: /booking-ruangan/outside_office_hours.php');
    exit;
}
