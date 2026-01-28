<?php
/* ===================================================
   AUTH MIDDLEWARE
   Room Meeting Booking System
=================================================== */

/* ===================================================
   TIMEZONE (GLOBAL APLIKASI)
   HARUS di atas sebelum date()/time() dipakai
=================================================== */
date_default_timezone_set('Asia/Jakarta');

/* ===================================================
   SESSION
=================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===================================================
   AUTH CHECK
=================================================== */
/*
 | Sistem ini menggunakan indikator login:
 | - $_SESSION['user']  → data user
 | - $_SESSION['login'] → flag login (jika tersedia)
 |
 | Check tetap kompatibel dengan sistem lama
*/
if (
    !isset($_SESSION['user']) ||
    (isset($_SESSION['login']) && $_SESSION['login'] !== true)
) {
    /* Redirect ke halaman login */
    header('Location: /booking-ruangan/auth/login.php');
    exit;
}
require_once __DIR__ . '/office_hours.php';
