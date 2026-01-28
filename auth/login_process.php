<?php
/* ===================================================
   SESSION
=================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===================================================
   ONLY POST REQUEST
=================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

require_once '../config/database.php';
require_once '../helpers/audit.php';

/* ===================================================
   CSRF VALIDATION
=================================================== */
if (
    empty($_POST['csrf']) ||
    empty($_SESSION['csrf']) ||
    !hash_equals($_SESSION['csrf'], $_POST['csrf'])
) {

    audit_log(
        'SECURITY_CSRF_BLOCK',
        'CSRF token invalid saat login'
    );

    $_SESSION['error'] = "Permintaan tidak valid";
    header("Location: login.php");
    exit;
}

/* ===================================================
   INPUT SANITIZATION
=================================================== */
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

/* ===================================================
   BASIC VALIDATION
=================================================== */
if ($email === '' || $password === '') {

    $_SESSION['error'] = "Email dan password wajib diisi";

    audit_log(
        'LOGIN_FAILED',
        'Login gagal: field kosong (' . htmlspecialchars($email) . ')'
    );

    header("Location: login.php");
    exit;
}

/* ===================================================
   EMAIL FORMAT VALIDATION
=================================================== */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $_SESSION['error'] = "Email atau password salah";

    audit_log(
        'LOGIN_FAILED',
        'Login gagal: format email tidak valid (' . htmlspecialchars($email) . ')'
    );

    header("Location: login.php");
    exit;
}

/* ===================================================
   DOMAIN CHECK (NON BLOCKING)
=================================================== */
$emailParts = explode('@', $email);
if (count($emailParts) === 2 && $emailParts[1] !== 'gmail.com') {

    audit_log(
        'LOGIN_WARNING',
        'Login dengan domain non-gmail: ' . htmlspecialchars($email)
    );
}

/* ===================================================
   DATABASE CONNECTION
=================================================== */
$db   = new Database();
$conn = $db->connect();

/* ===================================================
   FETCH USER (DITAMBAH phone & department)
=================================================== */
$stmt = mysqli_prepare(
    $conn,
    "SELECT 
        id,
        name,
        email,
        password,
        role,
        phone,
        department
     FROM users
     WHERE email = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

/* ===================================================
   LOGIN SUCCESS
=================================================== */
if ($user && password_verify($password, $user['password'])) {

    /* Session fixation protection */
    session_regenerate_id(true);

    $_SESSION['login'] = true;
    $_SESSION['user']  = [
        'id'         => $user['id'],
        'name'       => $user['name'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'phone'      => $user['phone'],       // ✅ BARU
        'department' => $user['department']   // ✅ BARU
    ];

    unset($_SESSION['csrf']);

    audit_log(
        'LOGIN_SUCCESS',
        'User login: ' . $user['email'],
        $user['id']
    );

    /* Cleanup */
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    /* Redirect by role */
    switch ($user['role']) {

        case 'superadmin':
            header("Location: ../dashboard/superadmin/index.php");
            break;

        case 'admin':
            header("Location: ../dashboard/admin/index.php");
            break;

        case 'employee':
            header("Location: ../dashboard/user/index.php");
            break;

        default:
            header("Location: login.php");
            break;
    }

    exit;
}

/* ===================================================
   ANTI BRUTE FORCE DELAY
=================================================== */
sleep(1);

/* ===================================================
   LOGIN FAILED
=================================================== */
audit_log(
    'LOGIN_FAILED',
    'Login gagal: ' . htmlspecialchars($email)
);

$_SESSION['error'] = "Email atau password salah";

/* Cleanup */
mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: login.php");
exit;
