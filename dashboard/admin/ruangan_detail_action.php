<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

requireRole(['admin','superadmin']);

$db   = new Database();
$conn = $db->connect();

/* ===============================
   DELETE DETAIL GAMBAR (GET)
=============================== */
if (isset($_GET['hapus'])) {

    $id = (int) $_GET['hapus'];

    // ambil data gambar
    $q = mysqli_query($conn,"
        SELECT gambar, ruangan_id
        FROM ruangan_detail
        WHERE id = $id
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($q)) {

        $filePath = __DIR__ . '/../../assets/img/rooms/' . $row['gambar'];

        // hapus file fisik
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // hapus dari database
        mysqli_query($conn,"
            DELETE FROM ruangan_detail
            WHERE id = $id
        ");

        // balik ke halaman edit ruangan
        header("Location: edit_ruangan.php?id=" . $row['ruangan_id']);
        exit;
    }

    // fallback
    header("Location: manajemen_ruangan.php");
    exit;
}

/* ===============================
   TAMBAH DETAIL GAMBAR (POST)
=============================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manajemen_ruangan.php");
    exit;
}

$ruangan_id = (int)($_POST['ruangan_id'] ?? 0);
$posisi     = $_POST['posisi'] ?? 'lainnya';

if ($ruangan_id <= 0 || empty($_FILES['gambar']['name'])) {
    die('Data tidak valid');
}

/* VALIDASI FILE */
$allowed = ['jpg','jpeg','png','webp'];
$ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    die('Format gambar tidak valid');
}

/* SIMPAN FILE */
$filename = 'detail_' . time() . '_' . rand(100,999) . '.' . $ext;
$target   = __DIR__ . '/../../assets/img/rooms/' . $filename;

if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
    die('Upload gagal');
}

/* INSERT DATABASE */
$stmt = mysqli_prepare($conn, "
    INSERT INTO ruangan_detail (ruangan_id, gambar, posisi)
    VALUES (?, ?, ?)
");

mysqli_stmt_bind_param($stmt, "iss", $ruangan_id, $filename, $posisi);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

/* KEMBALI KE EDIT */
header("Location: edit_ruangan.php?id=".$ruangan_id);
exit;
