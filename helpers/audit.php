<?php
/**
 * =========================================================
 * AUDIT LOG HELPER
 * Project : RMBS (Room Meeting Booking System)
 * Author  : System Core
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Menyimpan log aktivitas sistem
 *
 * @param string      $action      Kode aksi (LOGIN_SUCCESS, UPDATE_USER, dll)
 * @param string      $description Deskripsi aktivitas
 * @param int|null    $userId      ID user (optional)
 */
function audit_log(string $action, string $description, ?int $userId = null): void
{
    try {
        $db   = new Database();
        $conn = $db->connect();

        // Ambil user ID dari session jika tidak dikirim
        if ($userId === null && isset($_SESSION['user']['id'])) {
            $userId = (int) $_SESSION['user']['id'];
        }

        // Data request
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Sanitasi
        $action      = mysqli_real_escape_string($conn, $action);
        $description = mysqli_real_escape_string($conn, $description);
        $ipAddress   = $ipAddress ? mysqli_real_escape_string($conn, $ipAddress) : null;
        $userAgent   = $userAgent ? mysqli_real_escape_string($conn, $userAgent) : null;

        // Query insert
        $sql = "
            INSERT INTO audit_logs (
                user_id,
                action,
                description,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                " . ($userId !== null ? (int)$userId : "NULL") . ",
                '$action',
                '$description',
                " . ($ipAddress ? "'$ipAddress'" : "NULL") . ",
                " . ($userAgent ? "'$userAgent'" : "NULL") . ",
                NOW()
            )
        ";

        mysqli_query($conn, $sql);

    } catch (Throwable $e) {
        /**
         * Audit log TIDAK BOLEH menghentikan sistem
         * Jika error, cukup diabaikan (silent fail)
         */
    }
}
