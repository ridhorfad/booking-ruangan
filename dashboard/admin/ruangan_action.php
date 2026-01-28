<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

requireRole(['admin','superadmin']);

$db   = new Database();
$conn = $db->connect();

$aksi = $_REQUEST['aksi'] ?? '';
$targetDir = '../../assets/img/rooms/';

/* ================= HELPER ================= */
function redirect(){
    header("Location: manajemen_ruangan.php");
    exit;
}

/* ================= TAMBAH RUANGAN ================= */
if ($aksi === 'tambah') {

    $kode      = strtoupper(trim($_POST['kode'] ?? ''));
    $nama      = trim($_POST['nama'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);
    $fasilitas = trim($_POST['fasilitas'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($kode === '' || $nama === '' || $kapasitas <= 0) {
        redirect();
    }

    /* CEK KODE DUPLIKAT */
    $cek = mysqli_query($conn,"SELECT id FROM ruangan WHERE kode='$kode' LIMIT 1");
    if (mysqli_fetch_assoc($cek)) {
        redirect();
    }

    /* UPLOAD GAMBAR */
    $gambar = null;

    if (!empty($_FILES['gambar']['name'])) {

        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            redirect();
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $gambar = 'rooms_' . time() . '_' . rand(100,999) . '.' . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $targetDir . $gambar);
    }

    mysqli_query($conn,"
        INSERT INTO ruangan (kode,nama,kapasitas,fasilitas,deskripsi,gambar,status)
        VALUES (
            '$kode',
            '$nama',
            '$kapasitas',
            '$fasilitas',
            '$deskripsi',
            '$gambar',
            'aktif'
        )
    ");

    redirect();
}

/* ================= UPDATE RUANGAN ================= */
elseif ($aksi === 'update') {

    $id        = (int)($_POST['id'] ?? 0);
    $nama      = trim($_POST['nama'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);
    $fasilitas = trim($_POST['fasilitas'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($id <= 0 || $nama === '' || $kapasitas <= 0) {
        redirect();
    }

    /* AMBIL GAMBAR LAMA */
    $old = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT gambar FROM ruangan WHERE id='$id' LIMIT 1")
    );

    if (!$old) {
        redirect();
    }

    $gambarBaru = $old['gambar'];

    /* UPLOAD GAMBAR BARU */
    if (!empty($_FILES['gambar']['name'])) {

        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            redirect();
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $gambarBaru = 'rooms_' . time() . '_' . rand(100,999) . '.' . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $targetDir . $gambarBaru);

        /* HAPUS GAMBAR LAMA */
        if (!empty($old['gambar']) && file_exists($targetDir.$old['gambar'])) {
            unlink($targetDir.$old['gambar']);
        }
    }

    mysqli_query($conn,"
        UPDATE ruangan SET
            nama='$nama',
            kapasitas='$kapasitas',
            fasilitas='$fasilitas',
            deskripsi='$deskripsi',
            gambar='$gambarBaru'
        WHERE id='$id'
    ");

    redirect();
}

/* ================= HAPUS RUANGAN ================= */
elseif ($aksi === 'hapus') {

    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        redirect();
    }

    $r = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT gambar FROM ruangan WHERE id='$id' LIMIT 1")
    );

    if ($r && !empty($r['gambar']) && file_exists($targetDir.$r['gambar'])) {
        unlink($targetDir.$r['gambar']);
    }

    mysqli_query($conn,"DELETE FROM ruangan WHERE id='$id'");

    redirect();
}

/* DEFAULT */
redirect();
