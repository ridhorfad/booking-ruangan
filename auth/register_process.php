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

/* ===================================================
   CSRF VALIDATION
=================================================== */
if (
    empty($_POST['csrf']) ||
    empty($_SESSION['csrf']) ||
    !hash_equals($_SESSION['csrf'], $_POST['csrf'])
) {
    $_SESSION['error'] = "Permintaan tidak valid";
    header("Location: login.php?register=1");
    exit;
}

require_once '../config/database.php';
require_once '../helpers/audit.php';

/* ===================================================
   INPUT SANITIZATION
=================================================== */
$name     = trim($_POST['name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

/* ===================================================
   BASIC VALIDATION
=================================================== */
if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "Semua field wajib diisi";
    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   EMAIL FORMAT VALIDATION
=================================================== */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Format email tidak valid";
    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   EMAIL DOMAIN RESTRICTION
=================================================== */
$emailParts = explode('@', $email);

if (
    count($emailParts) !== 2 ||
    $emailParts[1] !== 'gmail.com'
) {
    $_SESSION['error'] = "Pendaftaran hanya menggunakan email @gmail.com";
    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   EMAIL USERNAME VALIDATION
   (Must contain letter & number)
=================================================== */
$username = $emailParts[0];

if (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]+$/', $username)) {
    $_SESSION['error'] = "Email harus mengandung huruf dan angka (contoh: user27@gmail.com)";
    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   PASSWORD VALIDATION
=================================================== */
if (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/\d/', $password)
) {
    $_SESSION['error'] = "Password minimal 8 karakter, mengandung huruf besar dan angka";
    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   NAME SANITIZATION (SAFE OUTPUT)
=================================================== */
$safeName = trim($name);

/* ===================================================
   DATABASE CONNECTION
=================================================== */
$db   = new Database();
$conn = $db->connect();

/* ===================================================
   CHECK DUPLICATE EMAIL
=================================================== */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {

    audit_log(
        'REGISTER_FAILED',
        'Registrasi gagal: email sudah terdaftar (' . $email . ')'
    );

    $_SESSION['error'] = "Email sudah terdaftar";
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    header("Location: login.php?register=1");
    exit;
}

/* ===================================================
   PASSWORD HASH
=================================================== */
$hash = password_hash($password, PASSWORD_BCRYPT);

/* ===================================================
   INSERT USER
=================================================== */
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (name, email, password, role)
     VALUES (?, ?, ?, 'employee')"
);

mysqli_stmt_bind_param($stmt, "sss", $safeName, $email, $hash);
mysqli_stmt_execute($stmt);

audit_log(
    'REGISTER_SUCCESS',
    'Registrasi user baru: ' . $email
);

/* ===================================================
   CLEANUP
=================================================== */
mysqli_stmt_close($stmt);
mysqli_close($conn);

/* ===================================================
   SUCCESS
=================================================== */

unset($_SESSION['csrf']);

$_SESSION['success'] = "Registrasi berhasil, silakan login";
header("Location: login.php");
exit;
