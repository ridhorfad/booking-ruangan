<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/audit.php';
require_once '../../helpers/settings.php';

requireRole(['superadmin']);

$role = $_SESSION['user']['role'] ?? 'superadmin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

$db   = new Database();
$conn = $db->connect();

/* ===== SYNC AVATAR SESSION (FINAL) ===== */
$userId = (int) ($_SESSION['user']['id'] ?? 0);

if ($userId && empty($_SESSION['user']['avatar'])) {
    $qAvatar = mysqli_query($conn,"
        SELECT avatar
        FROM users
        WHERE id = $userId
        LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($qAvatar)) {
        $_SESSION['user']['avatar'] = $row['avatar'] ?? null;
    }
}

$id = (int)($_GET['id'] ?? 0);

/* ===== FOOTER ROLE (SAMAKAN DENGAN DASHBOARD) ===== */
$role = $_SESSION['user']['role'] ?? 'superadmin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

/* ===== AMBIL DATA USER ===== */
$user = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT * FROM users WHERE id=$id")
);

// Tolak jika user tidak ada / superadmin
if (!$user || $user['role'] === 'superadmin') {

    audit_log(
        'UPDATE_USER_FAILED',
        'Attempt edit protected or invalid user (ID: '.$id.')',
        $_SESSION['user']['id']
    );

    header("Location: users.php");
    exit;
}

/* ===== UPDATE USER ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama  = trim($_POST['nama']);
    $email = strtolower(trim($_POST['email']));
    $roleU = $_POST['role'];

    $cek = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT id FROM users WHERE email='$email' AND id!=$id")
    );
if ($cek) {

    audit_log(
        'UPDATE_USER_FAILED',
        'Update user failed (duplicate email): '.$email,
        $_SESSION['user']['id']
    );

    header("Location: users_edit.php?id=$id&error=email");
    exit;
}

    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn,"
            UPDATE users 
            SET name='$nama', email='$email', role='$roleU', password='$hash'
            WHERE id=$id
        ");
    } else {
        mysqli_query($conn,"
            UPDATE users 
            SET name='$nama', email='$email', role='$roleU'
            WHERE id=$id
        ");
    }

audit_log(
    'UPDATE_USER',
    'Update user: '.$user['email'].' → '.$email.' (role: '.$roleU.')',
    $_SESSION['user']['id']
);

header("Location: users.php");
exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit User | RMBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* === STYLE IDENTIK DENGAN users.php === */
:root{
    --primary:#eb2525;
    --success:#16a34a;
    --danger:#dc2626;
    --info:#2563eb;
    --bg:#0b1220;
    --glass:rgba(255,255,255,.06);
    --border:rgba(255,255,255,.12);
}

*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif}

body{
    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 40%),
        linear-gradient(to bottom right,#0b1220,#020617);
    background-size:28px 28px,28px 28px,cover,cover;
    color:#e5e7eb;

    min-height:100vh;
    display:flex;
    flex-direction:column;
    padding-top:78px;
}

/* ===== NAVBAR ===== */
.navbar{
    position:fixed;
    top:0;left:0;right:0;
    z-index:1000;
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(12px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 15px 40px rgba(0,0,0,.6);
    transition:.35s;
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}
.navbar.shrink .nav-logo{height:24px}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:#fff;
    font-weight:700;
    font-size:14px;
}
.nav-logo{height:32px}

.nav-user{
    display:flex;
    align-items:center;
    gap:14px;
    font-size:13px;
}
.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
}
.btn-logout{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:7px 18px;
    border-radius:10px;
    color:#fff;
    text-decoration:none;
    font-weight:600;
}

/* ===== WRAPPER ===== */
.wrapper{
    padding:40px 26px;
    flex:1;
}

/* PAGE HEADER */
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:34px;
}
.page-header h1{
    font-size:26px;
    font-weight:700;
}
.page-header h1::after{
    content:'';
    display:block;
    width:42px;
    height:3px;
    margin-top:8px;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    border-radius:2px;
}
.page-header p{
    font-size:13px;
    opacity:.75;
    margin-top:4px;
}
.btn-back{
    text-decoration:none;
    color:#fff;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:10px 22px;
    border-radius:14px;
    font-size:13px;
}
.btn-back:hover{background:rgba(255,255,255,.16)}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:34px;
    backdrop-filter:blur(16px);
}

/* FORM */
.form-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:18px;
}
.form{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
}
.form{
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
}

.form button{
    align-self:flex-end;
}
input,select{
    width:100%;
    padding:13px 15px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.14);
    font-size:13px;
    background:rgba(15,23,42,.65);
    color:#e5e7eb;
    transition:.25s ease;
}

input::placeholder{
    color:rgba(255,255,255,.45);
}

input:focus,select:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.9);
}

/* BUTTON */
.btn{
    padding:10px 18px;
    border-radius:12px;
    font-size:12px;
    font-weight:600;
    border:none;
    cursor:pointer;
    color:#fff;
}
.btn-save{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:12px;
    border-radius:14px;
    font-size:12px;
    font-weight:600;
    transition:.25s;
}

.btn-save:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(235,37,37,.45);
}

/* FOOTER (SAMA DENGAN DASHBOARD) */
.footer{
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    padding:22px;
    font-size:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    border-top:1px solid rgba(255,255,255,.12);
}
.footer strong{color:#fff}

/* RESPONSIVE */
@media(max-width:1100px){
    .form{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }
}
/* ===== PAGE ENTRY TRANSITION (KONSISTEN GLOBAL) ===== */
body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
}

/* Card masuk halus */
.card{
    opacity:0;
    transform:translateY(18px);
    animation:cardIn .6s cubic-bezier(.4,0,.2,1) forwards;
    animation-delay:.15s;
}

@keyframes cardIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* ================= LOGOUT MODAL ================= */
.logout-modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.55);
    backdrop-filter:blur(6px);
    display:flex;
    align-items:center;
    justify-content:center;
    opacity:0;
    pointer-events:none;
    transition:.35s ease;
    z-index:9999;
}

.logout-modal.show{
    opacity:1;
    pointer-events:auto;
}

.logout-box{
    width:100%;
    max-width:420px;
    background:
        linear-gradient(
            rgba(255,255,255,.18),
            rgba(255,255,255,.08)
        );
    backdrop-filter:blur(28px);
    border:1px solid rgba(255,255,255,.25);
    border-radius:26px;
    padding:34px 30px;
    text-align:center;
    color:#fff;
    box-shadow:0 40px 90px rgba(0,0,0,.6);
    transform:scale(.92);
    transition:.35s ease;
}

.logout-modal.show .logout-box{
    transform:scale(1);
}

.logout-icon{
    font-size:42px;
    margin-bottom:14px;
}

.logout-box h3{
    font-size:20px;
    margin-bottom:10px;
}

.logout-box p{
    font-size:14px;
    opacity:.9;
    line-height:1.6;
}

.logout-action{
    display:flex;
    gap:14px;
    margin-top:26px;
}

.logout-action button{
    flex:1;
    padding:12px;
    border-radius:14px;
    border:none;
    font-weight:600;
    cursor:pointer;
    transition:.25s;
}

.btn-cancel{
    background:rgba(255,255,255,.15);
    color:#fff;
}
.btn-cancel:hover{
    background:rgba(255,255,255,.28);
}

.btn-yes{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
}
.btn-yes:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(235,37,37,.55);
}

/* ===== PROFILE NAVBAR ===== */
.profile-trigger{
    position:relative;
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:14px;
}

.avatar-mini{
    width:28px;
    height:28px;
    border-radius:50%;
    background:linear-gradient(135deg,#eb2525,#b91c1c);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:700;
}

.user-name{
    font-size:13px;
}

.caret{
    font-size:12px;
    opacity:.8;
}

.profile-dropdown{
    position:absolute;
    top:120%;
    right:0;
    width:220px;
    background:rgba(15,23,42,.92);
    border:1px solid rgba(255,255,255,.15);
    border-radius:14px;
    backdrop-filter:blur(16px);
    box-shadow:0 25px 60px rgba(0,0,0,.6);
    display:none;
    flex-direction:column;
    overflow:hidden;
    z-index:1000;
}

.profile-dropdown.show{
    display:flex;
}

.profile-dropdown a{
    padding:12px 16px;
    font-size:13px;
    color:#e5e7eb;
    text-decoration:none;
}

.profile-dropdown a:hover{
    background:rgba(255,255,255,.08);
}

.profile-dropdown .danger{
    color:#fca5a5;
}

.profile-email{
    font-size:11px;
    opacity:.6;
    margin-top:4px;
}

</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <img src="../../assets/img/<?= getSetting('system_logo', 'logobummnew.png') ?>" class="nav-logo">
<span>
    <?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?> | Dashboard
</span>
    </a>
    <div class="nav-user profile-trigger">

    <?php
$avatarFile = $_SESSION['user']['avatar'] ?? null;

$avatarPath = $avatarFile
    ? '../../assets/img/avatars/'.$avatarFile
    : null;
?>

<div class="avatar-mini">
    <?php if ($avatarPath && file_exists(__DIR__.'/../../assets/img/avatars/'.$avatarFile)): ?>
        <img src="<?= $avatarPath ?>" alt="Avatar"
             style="width:100%;height:100%;object-fit:cover;border-radius:50%">
    <?php else: ?>
        <?= strtoupper(substr($_SESSION['user']['name'], 0, 1)) ?>
    <?php endif; ?>
</div>


    <span class="user-name">
        <?= htmlspecialchars($_SESSION['user']['name']) ?>
    </span>

    <span class="role-badge">
        <?= strtoupper($_SESSION['user']['role']) ?>
    </span>

    <span class="caret">▾</span>

    <div class="profile-dropdown">
        <a href="profile.php">
            👤 <strong>Profil Saya</strong>
            <div class="profile-email">
                <?= htmlspecialchars($_SESSION['user']['email']) ?>
            </div>
        </a>

        <a href="profile.php#security">🔒 Keamanan Akun</a>

        <a href="#" class="danger" onclick="confirmLogout(event)">
            🚪 Logout
        </a>
    </div>

</div>

</nav>

<div class="wrapper">

    <div class="page-header">
        <div>
            <h1>Edit User</h1>
            <p>Perbarui data akun admin atau karyawan</p>
        </div>
        <a href="users.php" class="btn-back">← Kembali</a>
    </div>

    <div class="card">

    <?php if(isset($_GET['error']) && $_GET['error']=='email'): ?>
<div style="
    margin-bottom:18px;
    padding:12px 16px;
    border-radius:14px;
    background:rgba(220,38,38,.2);
    color:#fca5a5;
    font-size:13px;
">
    ❌ Email sudah digunakan user lain
</div>
<?php endif; ?>

        <div class="form-title">Form Edit User</div>

        <form class="form" method="POST">
            <input type="text" name="nama" value="<?= htmlspecialchars($user['name']) ?>" required>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <select name="role" required>
                <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
                <option value="employee" <?= $user['role']=='employee'?'selected':'' ?>>Employee</option>
            </select>

            <input type="password" name="password" placeholder="Password baru (opsional)">

            <button class="btn btn-save" type="submit">Simpan Perubahan</button>
        </form>

    </div>

</div>

<footer class="footer">
    <span>
        © <?= date('Y') ?>
        <strong><?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?></strong>
    </span>
    <span>•</span>
    <span><?= $footerRole ?> • <?= $footerDesc ?></span>
</footer>

<!-- LOGOUT MODAL -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">⚠️</div>
        <h3>Konfirmasi Logout</h3>
        <p>
            Anda login sebagai
<strong><?= strtoupper($_SESSION['user']['role']) ?></strong>.<br>
            Yakin ingin keluar dari sistem?
        </p>
        <div class="logout-action">
            <button class="btn-cancel" onclick="closeLogout()">Batal</button>
            <button class="btn-yes" onclick="doLogout()">Ya, Logout</button>
        </div>
    </div>
</div>

<script>
/* ================= PAGE ENTRY ================= */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-loaded');
});

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

    if (currentScroll > lastScrollTop && currentScroll > 100) {
        navbar.classList.add('hide');
    } else {
        navbar.classList.remove('hide');
    }

    if (currentScroll > 80) {
        navbar.classList.add('shrink');
    } else {
        navbar.classList.remove('shrink');
    }

    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
});

/* ================= LOGOUT MODAL ================= */
function confirmLogout(e){
    e.preventDefault();
    document.getElementById('logoutModal').classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal').classList.remove('show');
}

function doLogout(){
    document.body.classList.remove('page-loaded');
    document.body.classList.add('fade-out');

    setTimeout(() => {
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

/* ESC to close modal */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLogout();
});

/* Click outside modal */
document.getElementById('logoutModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'logoutModal') closeLogout();
});

/* ===== PROFILE DROPDOWN ===== */
const profileTrigger = document.querySelector('.profile-trigger');
const profileDropdown = document.querySelector('.profile-dropdown');

profileTrigger?.addEventListener('click', e => {
    e.stopPropagation();
    profileDropdown?.classList.toggle('show');
});

document.addEventListener('click', () => {
    profileDropdown?.classList.remove('show');
});

</script>

</body>
</html>
