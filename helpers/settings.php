<?php
/**
 * =========================================================
 * SYSTEM SETTINGS HELPER
 * Project : RMBS (Room Meeting Booking System)
 * =========================================================
 */

require_once __DIR__ . '/../config/database.php';

/**
 * ---------------------------------------------------------
 * Ambil 1 nilai setting berdasarkan key
 * Cocok untuk UI / view / validasi ringan
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 * ---------------------------------------------------------
 */
function getSetting(string $key, $default = null)
{
    static $cache = [];

    // gunakan cache agar tidak query berulang
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $db   = new Database();
        $conn = $db->connect();

        $safeKey = mysqli_real_escape_string($conn, $key);

        $q = mysqli_query(
            $conn,
            "SELECT setting_value 
             FROM system_settings 
             WHERE setting_key='$safeKey' 
             LIMIT 1"
        );

        if ($row = mysqli_fetch_assoc($q)) {
            $cache[$key] = $row['setting_value'];
            return $row['setting_value'];
        }

    } catch (Throwable $e) {
        // silent fail → sistem tetap jalan
    }

    return $default;
}


/**
 * ---------------------------------------------------------
 * Ambil SEMUA system settings sekaligus
 * Digunakan untuk middleware & policy global
 *
 * Contoh penggunaan:
 * $settings = getSystemSettings();
 * $settings['office_start']
 *
 * @return array
 * ---------------------------------------------------------
 */
function getSystemSettings(): array
{
    static $settings = null;

    // cache per request
    if ($settings !== null) {
        return $settings;
    }

    $settings = [];

    try {
        $db   = new Database();
        $conn = $db->connect();

        $q = mysqli_query(
            $conn,
            "SELECT setting_key, setting_value 
             FROM system_settings"
        );

        while ($row = mysqli_fetch_assoc($q)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

    } catch (Throwable $e) {
        // silent fail
    }

    return $settings;
}
