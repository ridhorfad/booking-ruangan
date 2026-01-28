<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/audit.php';
require_once '../../helpers/settings.php';

requireRole(['superadmin']);

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

/* ===== FOOTER ROLE (SAMA DENGAN DASHBOARD) ===== */
$role = $_SESSION['user']['role'] ?? 'superadmin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

/* ===== AMBIL SETTINGS LAMA (UNTUK AUDIT) ===== */
$oldSettings = [];
$qOld = mysqli_query($conn,"SELECT * FROM system_settings");
while($r = mysqli_fetch_assoc($qOld)){
    $oldSettings[$r['setting_key']] = $r['setting_value'];
}

/* ===== VALIDASI JAM OPERASIONAL ===== */
if (isset($_POST['office_start'], $_POST['office_end'])) {
    $start = $_POST['office_start'];
    $end   = $_POST['office_end'];

    if ($start >= $end) {
        $_SESSION['error'] = 'Jam operasional tidak valid. Jam mulai harus lebih kecil dari jam selesai.';
        header("Location: system_settings.php");
        exit;
    }
}

/* ===== VALIDASI IDENTITAS SISTEM ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['system_name']) && trim($_POST['system_name']) === '') {
        $_SESSION['error'] = 'Nama sistem tidak boleh kosong';
        header("Location: system_settings.php");
        exit;
    }

    if (isset($_POST['company_email']) &&
        !filter_var($_POST['company_email'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Email perusahaan tidak valid';
        header("Location: system_settings.php");
        exit;
    }
}

/* ===== UPDATE SETTINGS ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil nilai jam operasional lama (untuk audit gabungan)
    $oldOfficeStart = $oldSettings['office_start'] ?? null;
    $oldOfficeEnd   = $oldSettings['office_end'] ?? null;

    $officeChanged = false;

    foreach ($_POST as $key => $value) {

        if (!isset($oldSettings[$key])) continue;

        $newValue = trim($value);
        $oldValue = $oldSettings[$key];

        // Skip jika tidak berubah
        if ($newValue === $oldValue) continue;

        // ===== VALIDASI JAM OPERASIONAL =====
        if ($key === 'office_start' || $key === 'office_end') {
            $officeChanged = true;
        }

        $safeValue = mysqli_real_escape_string($conn, $newValue);

        mysqli_query($conn,"
            UPDATE system_settings 
            SET setting_value='$safeValue'
            WHERE setting_key='$key'
        ");

        /* ===== AUDIT LOG ===== */
        if ($key === 'maintenance_mode') {

            audit_log(
                'SYSTEM_MAINTENANCE',
                $newValue === 'on'
                    ? 'System maintenance mode ENABLED'
                    : 'System maintenance mode DISABLED',
                $_SESSION['user']['id']
            );

        } elseif ($key !== 'office_start' && $key !== 'office_end') {

            // Audit setting lain (generic)
            audit_log(
                'UPDATE_SYSTEM_SETTING',
                "Setting '$key' changed from '$oldValue' to '$newValue'",
                $_SESSION['user']['id']
            );

        }
    }

    /* ===== AUDIT KHUSUS JAM OPERASIONAL (DIGABUNG) ===== */
    if ($officeChanged) {

        $newOfficeStart = $_POST['office_start'] ?? $oldOfficeStart;
        $newOfficeEnd   = $_POST['office_end'] ?? $oldOfficeEnd;

        audit_log(
            'UPDATE_OPERATIONAL_HOURS',
            "Operational hours changed from {$oldOfficeStart}–{$oldOfficeEnd} to {$newOfficeStart}–{$newOfficeEnd}",
            $_SESSION['user']['id']
        );
    }

    header("Location: system_settings.php?success=1");
    exit;
}

/* ===== AMBIL SETTINGS ===== */
$settings = [];
$q = mysqli_query($conn,"SELECT * FROM system_settings");
while($row = mysqli_fetch_assoc($q)){
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>System Settings | RMBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
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
    padding-top:72px;
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
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}
.navbar.shrink .nav-logo{height:24px}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    color:#fff;
    text-decoration:none;
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

/* ===== PROFILE DROPDOWN ===== */
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

.wrapper{
    flex:1;
    padding:24px clamp(20px, 4vw, 72px) 120px;
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
    transition:.3s;
}
.btn-back:hover{
    background:rgba(255,255,255,.16);
}

body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:32px clamp(20px, 3vw, 40px);
    max-width:760px;

    backdrop-filter:blur(16px);
    box-shadow:0 40px 90px rgba(0,0,0,.55);

    opacity:0;
    transform:translateY(18px);
    animation:cardUp .6s cubic-bezier(.4,0,.2,1) forwards;
}

@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}

/* SECTION */
.section{
    margin-bottom:30px;
}
.section-title{
    font-size:15px;
    font-weight:600;
    margin-bottom:6px;
}
.section-desc{
    font-size:12px;
    opacity:.7;
    margin-bottom:14px;
}
.section{
    margin-bottom:34px;
    padding-bottom:26px;
    border-bottom:1px dashed rgba(255,255,255,.12);
}
.section:last-child{
    border-bottom:none;
    padding-bottom:0;
}

/* FORM */
.form{
    display:grid;
    gap:16px;
}
label{
    font-size:13px;
    opacity:.85;
}
input,select{
    padding:12px 14px;
    border-radius:12px;
    border:1px solid #ccc;
    font-size:13px;
}
input,select{
    background:rgba(15,23,42,.65);
    color:#e5e7eb;
    border:1px solid rgba(255,255,255,.14);
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
.btn-save{
    margin-top:10px;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:12px;
    border-radius:14px;
    border:none;
    color:#fff;
    font-weight:600;
    cursor:pointer;
    transition:.25s;
}

.btn-save:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(235,37,37,.45);
}

/* FOOTER */
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
@media(max-width:600px){
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
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
    background:linear-gradient(
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
}

.btn-cancel{
    background:rgba(255,255,255,.15);
    color:#fff;
}
.btn-cancel:hover{background:rgba(255,255,255,.28)}

.btn-yes{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
}

</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <img src="../../assets/img/logobummnew.png" class="nav-logo">
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
            <h1>System Settings</h1>
            <p>
Konfigurasi inti dan perilaku sistem
<strong><?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?></strong>
</p>
        </div>
        <a href="index.php" class="btn-back">← Dashboard</a>
    </div>

    <div class="card">

    <?php if(isset($_GET['success'])): ?>
<div style="
    margin-bottom:20px;
    padding:12px 16px;
    border-radius:14px;
    background:rgba(22,163,74,.2);
    color:#4ade80;
    font-size:13px;
">
    ✅ Pengaturan berhasil disimpan
</div>
<?php endif; ?>

        <form class="form" method="POST">

    <div class="section">
        <div class="section-title">Identitas Sistem</div>
        <div class="section-desc">
            Informasi dasar yang digunakan di seluruh sistem
        </div>

        <label>Nama Sistem</label>
        <input type="text"
               name="system_name"
               placeholder="Nama Sistem"
               value="<?= $settings['system_name'] ?>">

        <label>Email Perusahaan</label>
        <input type="email"
               name="company_email"
               placeholder="Email Perusahaan"
               value="<?= $settings['company_email'] ?>">
    </div>

<div class="section">
    <div class="section-title">Jam Operasional</div>
    <div class="section-desc">
        Digunakan untuk validasi pemesanan ruangan.
        <br>
        <small style="opacity:.6">
            Booking di luar jam ini akan otomatis ditolak oleh sistem.
        </small>
    </div>

    <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap">

        <div style="flex:1;min-width:180px">
            <label>Jam Mulai Operasional</label>
            <input
                type="time"
                name="office_start"
                value="<?= htmlspecialchars($settings['office_start']) ?>"
                required
            >
        </div>

        <div style="flex:1;min-width:180px">
            <label>Jam Selesai Operasional</label>
            <input
                type="time"
                name="office_end"
                value="<?= htmlspecialchars($settings['office_end']) ?>"
                required
            >
        </div>

    </div>
</div>

    <div class="section">
        <div class="section-title">Mode Sistem</div>
        <div class="section-desc">
            Nonaktifkan sistem sementara untuk maintenance
        </div>

        <label>Maintenance Mode</label>
        <select name="maintenance_mode">
            <option value="">Pilih Mode</option>
            <option value="off" <?= $settings['maintenance_mode']=='off'?'selected':'' ?>>OFF</option>
            <option value="on"  <?= $settings['maintenance_mode']=='on'?'selected':'' ?>>ON</option>
        </select>
    </div>

    <button class="btn-save">Simpan Perubahan</button>

</form>

    </div>

</div>

<footer class="footer">
    <span>© <?= date('Y') ?> <strong><?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?></strong>
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
/* PAGE ENTRY */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-loaded');
});

/* NAVBAR AUTO HIDE + SHRINK */
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const cur = window.pageYOffset || document.documentElement.scrollTop;

    navbar.classList.toggle('hide', cur > lastScrollTop && cur > 100);
    navbar.classList.toggle('shrink', cur > 80);

    lastScrollTop = cur <= 0 ? 0 : cur;
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
    document.body.classList.add('fade-out');
    setTimeout(()=>{
        window.location.href='../../auth/logout_process.php';
    },400);
}

/* ESC untuk tutup */
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeLogout();
});

/* Klik area gelap */
document.getElementById('logoutModal')?.addEventListener('click', e => {
    if(e.target.id === 'logoutModal') closeLogout();
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
