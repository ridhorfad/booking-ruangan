<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';

requireRole(['employee','admin','superadmin']);

/* ===============================
   VALIDASI REQUEST
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();

$userId = (int) $_SESSION['user']['id'];

/* ===============================
   SANITIZE INPUT
================================ */
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($name === '' || $email === '') {
    header('Location: profile.php?error=invalid_input');
    exit;
}

$name  = mysqli_real_escape_string($conn, $name);
$email = mysqli_real_escape_string($conn, $email);

/* ===============================
   AMBIL AVATAR LAMA (UNTUK DELETE)
================================ */
$oldAvatar = null;
$qOld = mysqli_query($conn,"
    SELECT avatar 
    FROM users 
    WHERE id = $userId 
    LIMIT 1
");
if ($r = mysqli_fetch_assoc($qOld)) {
    $oldAvatar = $r['avatar'];
}

/* ===============================
   UPLOAD AVATAR (OPTIONAL)
================================ */
if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {

    $tmp  = $_FILES['avatar']['tmp_name'];
    $size = $_FILES['avatar']['size'];

    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];

    /* validasi file */
    if (in_array($ext, $allowed) && $size <= 2 * 1024 * 1024) {

        $avatarName = 'u_'.$userId.'_'.time().'.'.$ext;
        $uploadDir  = __DIR__.'/../../assets/img/avatars/';
        $uploadPath = $uploadDir.$avatarName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($tmp, $uploadPath)) {

            /* hapus avatar lama (jika ada & bukan null) */
            if ($oldAvatar && file_exists($uploadDir.$oldAvatar)) {
                unlink($uploadDir.$oldAvatar);
            }

            /* update DB */
            mysqli_query($conn,"
                UPDATE users 
                SET avatar = '$avatarName' 
                WHERE id = $userId
            ");

            /* sync session */
            $_SESSION['user']['avatar'] = $avatarName;
        }
    }
}

/* ===============================
   UPDATE NAME & EMAIL
================================ */
mysqli_query($conn,"
    UPDATE users 
    SET name = '$name',
        email = '$email'
    WHERE id = $userId
");

/* ===============================
   UPDATE SESSION (ANTI BALIK)
================================ */
$_SESSION['user']['name']  = $name;
$_SESSION['user']['email'] = $email;

mysqli_close($conn);

/* ===============================
   REDIRECT
================================ */
header('Location: profile.php?success=1');
exit;
