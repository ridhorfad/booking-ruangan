<?php
/* ===================================================
   LOGOUT PROCESS (WITH AUDIT LOG)
   Room Meeting Booking System
=================================================== */

/* ===================================================
   SESSION
=================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || empty($_SESSION['user'])) {
    header("Location: /booking-ruangan/auth/login.php");
    exit;
}

require_once '../helpers/audit.php';

/* ===================================================
   STORE USER INFO BEFORE LOGOUT
=================================================== */
$userId    = $_SESSION['user']['id']    ?? null;
$userEmail = $_SESSION['user']['email'] ?? 'UNKNOWN';

/* ===================================================
   AUDIT LOG
=================================================== */
audit_log(
    'LOGOUT',
    'User logout: ' . $userEmail,
    $userId
);

/* ===================================================
   CLEAR SESSION DATA
=================================================== */
$_SESSION = [];

/* ===================================================
   DELETE SESSION COOKIE
=================================================== */
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/* ===================================================
   DESTROY SESSION
=================================================== */
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Logout...</title>

<meta http-equiv="refresh" content="1.3;url=/booking-ruangan/auth/login.php">

<style>
body{
    margin:0;
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#0b1220;
    font-family:'Montserrat',sans-serif;
    color:#e5e7eb;
}

.logout-box{
    text-align:center;
    animation:fadeIn .4s ease;
}

.spinner{
    width:44px;
    height:44px;
    border:4px solid rgba(255,255,255,.2);
    border-top:4px solid #eb2525;
    border-radius:50%;
    animation:spin 1s linear infinite;
    margin:0 auto 18px;
}

.logout-box p{
    font-size:14px;
    opacity:.9;
}

@keyframes spin{
    to{transform:rotate(360deg)}
}

@keyframes fadeIn{
    from{opacity:0;transform:scale(.95)}
    to{opacity:1;transform:scale(1)}
}
</style>
</head>

<body>

<div class="logout-box">
    <div class="spinner"></div>
    <p>Logout berhasil, mengalihkan ke halaman login…</p>
</div>

</body>
</html>
