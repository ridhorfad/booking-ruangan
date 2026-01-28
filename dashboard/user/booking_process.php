<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

requireRole(['employee']);

if (
    !isset($_POST['csrf']) ||
    !isset($_SESSION['csrf']) ||
    !hash_equals($_SESSION['csrf'], $_POST['csrf'])
) {
    flashError('Permintaan tidak valid (CSRF). Silakan ulangi booking.');
}

function flashError($msg){
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $msg
    ];
    header("Location: booking.php");
    exit;
}

function flashSuccess($msg){
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => $msg
    ];
    header("Location: booking_saya.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: booking.php");
    exit;
}

if (!isset($_SESSION['user']['id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id     = (int) $_SESSION['user']['id'];
$ruangan_id  = (int) ($_POST['ruangan_id'] ?? 0);

$tanggal     = trim($_POST['tanggal'] ?? '');
$jam_mulai   = trim($_POST['jam_mulai'] ?? '');
$jam_selesai = trim($_POST['jam_selesai'] ?? '');

$keperluan        = trim($_POST['keterangan'] ?? '');
$jumlah_tamu      = (int) ($_POST['jumlah_tamu'] ?? 0);
$request_konsumsi = trim($_POST['request_konsumsi'] ?? '');

if (
    !$ruangan_id ||
    $tanggal === '' ||
    $jam_mulai === '' ||
    $jam_selesai === '' ||
    $keperluan === '' ||
    $jumlah_tamu <= 0
) {
    flashError("Mohon lengkapi seluruh data booking.");
}

if ($jumlah_tamu > 50) {
    flashError("Jumlah tamu terlalu besar (maksimal 50orang).");
}

if ($jam_mulai >= $jam_selesai) {
    flashError("Jam selesai harus lebih besar dari jam mulai.");
}

$today = date('Y-m-d');
$now   = date('H:i');

if ($tanggal < $today) {
    flashError("Tanggal booking tidak boleh di masa lalu.");
}

if ($tanggal === $today && $jam_mulai <= $now) {
    flashError("Jam mulai harus lebih besar dari waktu sekarang.");
}

$db   = new Database();
$conn = $db->connect();

$sqlRoomBentrok = "
    SELECT id FROM booking
    WHERE ruangan_id = ?
    AND tanggal = ?
    AND status IN ('pending','approved')
    AND NOT (
        jam_selesai <= ?
        OR jam_mulai >= ?
    )
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sqlRoomBentrok);
mysqli_stmt_bind_param(
    $stmt,
    "isss",
    $ruangan_id,
    $tanggal,
    $jam_mulai,
    $jam_selesai
);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    flashError("Ruangan sudah dibooking pada jam tersebut.");
}
mysqli_stmt_close($stmt);

$sqlUserBentrok = "
    SELECT id FROM booking
    WHERE user_id = ?
    AND tanggal = ?
    AND status IN ('pending','approved')
    AND NOT (
        jam_selesai <= ?
        OR jam_mulai >= ?
    )
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sqlUserBentrok);
mysqli_stmt_bind_param(
    $stmt,
    "isss",
    $user_id,
    $tanggal,
    $jam_mulai,
    $jam_selesai
);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    flashError("Anda sudah memiliki booking lain di jam tersebut.");
}
mysqli_stmt_close($stmt);

$status = 'pending';

$sqlInsert = "
    INSERT INTO booking
    (
        user_id,
        ruangan_id,
        tanggal,
        jam_mulai,
        jam_selesai,
        keperluan,
        jumlah_tamu,
        request_konsumsi,
        status
    )
    VALUES (?,?,?,?,?,?,?,?,?)
";

$stmt = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param(
    $stmt,
    "iissssiss",
    $user_id,
    $ruangan_id,
    $tanggal,
    $jam_mulai,
    $jam_selesai,
    $keperluan,
    $jumlah_tamu,
    $request_konsumsi,
    $status
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    flashError("Gagal menyimpan booking. Silakan coba lagi.");
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

flashSuccess("Booking berhasil dikirim dan menunggu persetujuan admin.");
