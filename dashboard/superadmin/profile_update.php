<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

requireRole(['superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();

$userId = (int) $_SESSION['user']['id'];

/* ======================
   UPDATE NAMA & EMAIL
====================== */
$name  = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
$email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));

if ($name === '' || $email === '') {
    $_SESSION['error'] = 'Nama dan email wajib diisi';
    header('Location: profile.php');
    exit;
}

/* ======================
   UPLOAD AVATAR (OPTIONAL)
====================== */
if (!empty($_FILES['avatar']['name'])) {

    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];

    if (in_array($ext, $allowed)) {

        /* penamaan konsisten superadmin */
        $avatarName = 'superadmin_'.$userId.'_'.time().'.'.$ext;
        $uploadDir  = '../../assets/img/avatars/';
        $uploadPath = $uploadDir . $avatarName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {

            mysqli_query($conn,"
                UPDATE users
                SET avatar = '$avatarName'
                WHERE id = $userId
            ");
        }
    }
}

/* ======================
   UPDATE DATA USER
====================== */
mysqli_query($conn,"
    UPDATE users
    SET name  = '$name',
        email = '$email'
    WHERE id = $userId
");

/* ======================
   🔁 FINAL SYNC SESSION
====================== */
$qRefresh = mysqli_query($conn,"
    SELECT name, email, avatar
    FROM users
    WHERE id = $userId
    LIMIT 1
");

if ($fresh = mysqli_fetch_assoc($qRefresh)) {
    $_SESSION['user']['name']   = $fresh['name'];
    $_SESSION['user']['email']  = $fresh['email'];
    $_SESSION['user']['avatar'] = $fresh['avatar'];
}

mysqli_close($conn);

$_SESSION['success'] = 'Profil berhasil diperbarui';
header('Location: profile.php?success=1');
exit;
