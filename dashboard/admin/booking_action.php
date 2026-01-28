<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

/* ===============================
   CSRF PROTECTION
================================ */
if (
    empty($_POST['csrf']) ||
    empty($_SESSION['csrf']) ||
    $_POST['csrf'] !== $_SESSION['csrf']
) {
    die('Invalid CSRF Token');
}

requireRole(['admin','superadmin']);

/* ===============================
   VALIDASI METHOD
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: kelola_booking.php");
    exit;
}

/* ===============================
   AMBIL DATA
================================ */
$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$biaya  = isset($_POST['biaya']) ? (int)$_POST['biaya'] : null;

/* 🔴 tambahan khusus cancel */
$cancelReason = trim($_POST['cancel_reason'] ?? '');

/* ===============================
   VALIDASI DASAR
================================ */
if ($id <= 0 || !in_array($action, ['approve','reject','cancel'])) {
    header("Location: kelola_booking.php");
    exit;
}

/* ===============================
   DATABASE
================================ */
$db   = new Database();
$conn = $db->connect();

/* ===============================
   CEK STATUS BOOKING SAAT INI
================================ */
$check = mysqli_prepare(
    $conn,
    "SELECT status FROM booking WHERE id = ? LIMIT 1"
);
mysqli_stmt_bind_param($check, "i", $id);
mysqli_stmt_execute($check);
$res  = mysqli_stmt_get_result($check);
$data = mysqli_fetch_assoc($res);
mysqli_stmt_close($check);

if (!$data) {
    mysqli_close($conn);
    header("Location: kelola_booking.php");
    exit;
}

/* ===============================
   VALIDASI STATUS (FINAL STATE)
================================ */
// status final → tidak boleh diubah lagi
if (in_array($data['status'], ['rejected','cancelled'])) {
    mysqli_close($conn);
    header("Location: detail_booking.php?id=".$id);
    exit;
}

// booking approved hanya boleh di-cancel
if ($data['status'] === 'approved' && $action !== 'cancel') {
    mysqli_close($conn);
    header("Location: detail_booking.php?id=".$id);
    exit;
}

/* ===============================
   VALIDASI KHUSUS APPROVE
================================ */
if ($action === 'approve') {
    if ($biaya === null || $biaya < 0) {
        mysqli_close($conn);
        header("Location: detail_booking.php?id=".$id);
        exit;
    }
}

/* ===============================
   VALIDASI KHUSUS CANCEL
================================ */
if ($action === 'cancel') {

    // hanya pending & approved yang boleh dicancel
    if (!in_array($data['status'], ['pending','approved'])) {
        mysqli_close($conn);
        header("Location: detail_booking.php?id=".$id);
        exit;
    }

    // alasan WAJIB
    if ($cancelReason === '') {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Alasan pembatalan wajib diisi'
        ];
        mysqli_close($conn);
        header("Location: detail_booking.php?id=".$id);
        exit;
    }
}

/* ===============================
   PROSES ACTION
================================ */
if ($action === 'approve') {

    $sql = "
        UPDATE booking
        SET status = 'approved',
            biaya  = ?
        WHERE id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $biaya, $id);

} elseif ($action === 'reject') {

    $sql = "
        UPDATE booking
        SET status = 'rejected'
        WHERE id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

} elseif ($action === 'cancel') {

    $sql = "
        UPDATE booking
        SET status = 'cancelled',
            cancel_reason = ?,
            cancelled_at = NOW()
        WHERE id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $cancelReason, $id);

}

/* ===============================
   EKSEKUSI
================================ */
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    die('ERROR SQL');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

/* ===============================
   FLASH MESSAGE
================================ */
$_SESSION['flash'] = [
    'type' => 'success',
    'message' => match($action) {
        'approve' => 'Booking berhasil disetujui',
        'reject'  => 'Booking berhasil ditolak',
        'cancel'  => 'Booking berhasil dibatalkan oleh admin',
        default   => 'Aksi berhasil'
    }
];

/* ===============================
   REDIRECT
================================ */
header("Location: detail_booking.php?id=".$id);
exit;
