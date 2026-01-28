<?php
/* ===================================================
   ROLE MIDDLEWARE
   Room Meeting Booking System
=================================================== */

/**
 * Restrict page access based on user role
 *
 * Usage:
 * requireRole(['admin', 'superadmin']);
 */
function requireRole(array $allowedRoles): void
{
    /* ===================================================
       SESSION
    =================================================== */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    /* ===================================================
       AUTH CHECK
    =================================================== */
    if (!isset($_SESSION['user'])) {
        header('Location: /booking-ruangan/auth/login.php');
        exit;
    }

    /* ===================================================
       ROLE CHECK
    =================================================== */
    if (
        !isset($_SESSION['user']['role']) ||
        !is_string($_SESSION['user']['role'])
    ) {
        session_destroy();
        header('Location: /booking-ruangan/auth/login.php');
        exit;
    }

    /* ===================================================
       ACCESS VALIDATION
    =================================================== */
    if (!in_array($_SESSION['user']['role'], $allowedRoles, true)) {

        http_response_code(403);

        echo '<h2>403 - Akses Ditolak</h2>';
        echo '<p>Kamu tidak memiliki hak akses ke halaman ini.</p>';

        exit;
    }
}
