<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/maintenance.php'; // ✅ TAMBAHKAN DI SINI
require_once '../../middleware/role.php';
require_once '../../helpers/settings.php';
require_once '../../config/database.php';

requireRole(['admin','superadmin']);

$role = $_SESSION['user']['role'] ?? 'admin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

/* ================= DATABASE ================= */
$db   = new Database();
$conn = $db->connect();

$userId = (int) $_SESSION['user']['id'];

/* ===== SYNC AVATAR SESSION ===== */
if (empty($_SESSION['user']['avatar'])) {
    $qAvatar = mysqli_query($conn,"
        SELECT avatar
        FROM users
        WHERE id = $userId
        LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($qAvatar)) {
        if (!empty($row['avatar'])) {
            $_SESSION['user']['avatar'] = $row['avatar'];
        }
    }
}

$totalRooms = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM ruangan"))['total'] ?? 0;
$totalBooking = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM booking"))['total'] ?? 0;
$pendingBooking = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM booking WHERE status='pending'"))['total'] ?? 0;
$today = date('Y-m-d');
$todayBooking = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM booking WHERE tanggal='$today'"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>RMBS | Dashboard Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ==================================================
   ROOT & RESET
================================================== */
:root{
    --primary:#eb2525;
    --primary-dark:#b91c1c;
    --glass:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.18);
    --shadow:rgba(0,0,0,.55);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Montserrat',sans-serif;
}

/* ==================================================
   BASE
================================================== */
body{
    min-height:100vh;
    padding-top:76px;
    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 40%),
        linear-gradient(to bottom right,#0b1220,#020617);
    background-size:28px 28px,28px 28px,cover,cover;
    color:#e5e7eb;
    opacity:0;
    transition:opacity .5s ease;
}
body.loaded{opacity:1}

.fade-out{
    opacity:0;
}


/* ==================================================
   NAVBAR
================================================== */
.navbar{
    position:fixed;
    inset:0 0 auto 0;
    z-index:1000;
    background:rgba(15,23,42,.72);
    backdrop-filter:blur(10px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    transition:.35s;
    box-shadow:0 8px 25px rgba(0,0,0,.45);
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:#fff;
    font-weight:700;
    font-size:14px;
}

/* ===== PENYESUAIAN FONT NAVBAR (FINAL) ===== */
.nav-brand span{
    font-size:14px;
    letter-spacing:.3px;
}

.user-name{
    font-size:13px;
    font-weight:500;
}

.role-badge{
    font-size:11px;
}

.nav-logo{height:34px;transition:.35s}
.navbar.shrink .nav-logo{height:26px}

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

/* ===== PROFILE NAVBAR (KONSISTEN SEMUA ROLE) ===== */
.profile-trigger{
    position:relative;
    cursor:pointer;
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
    overflow:hidden;
}

.user-name{
    font-size:13px;
    font-weight:500;
}

.caret{
    font-size:12px;
    opacity:.9;
    margin-left:2px;
    transform:translateY(1px);
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

.profile-dropdown.show{
    display:flex;
}

.btn-logout{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:7px 18px;
    border-radius:10px;
    color:#fff;
    text-decoration:none;
    font-weight:600;
}

/* ==================================================
   HERO
================================================== */
.hero{
    position:relative;                 /* penting untuk aksen */
    padding:48px clamp(20px, 4vw, 72px) 34px;
    text-align:center;
    animation:heroIn .7s cubic-bezier(.4,0,.2,1);
}

.hero h1{
    font-size:28px;
    margin-bottom:6px;
}

/* HAPUS garis lama di h1 */
.hero h1::after{
    display:none;
}

.hero p{
    font-size:13px;
    opacity:.85;
}

/* ===== AKSEN GARIS MERAH (KONSISTEN DENGAN SUPERADMIN) ===== */
.hero::after{
    content:'';
    position:absolute;
    left:50%;
    bottom:0;
    transform:translateX(-50%);
    width:120px;
    height:3px;
    background:linear-gradient(
        to right,
        transparent,
        var(--primary),
        transparent
    );
    border-radius:2px;
    opacity:.85;
}

/* ==================================================
   CONTAINER
================================================== */
.container{
    width:100%;
    max-width:none;                 /* sama seperti user */
    margin:24px 0 120px;            /* jarak konsisten */
    padding:0 clamp(20px, 4vw, 72px);
}

/* ==================================================
   CARD
================================================== */
.card{
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid var(--border);
    padding:32px clamp(20px, 3vw, 40px);
    border-radius:26px;
    box-shadow:0 40px 90px var(--shadow);
    margin-bottom:40px;

    opacity:0;
    transform:translateY(24px);
    animation:cardUp .7s cubic-bezier(.4,0,.2,1) forwards;
}
.card:nth-child(1){animation-delay:.15s}
.card:nth-child(2){animation-delay:.3s}

.card h3{
    font-size:20px;
    margin-bottom:26px;
}
.card h3::after{
    content:'';
    width:42px;
    height:3px;
    background:linear-gradient(to right,var(--primary),var(--primary-dark));
    display:block;
    margin-top:10px;
    border-radius:2px;
}

/* ==================================================
   GRID
================================================== */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:22px;
    align-items:stretch;
}

/* ==================================================
   STAT
================================================== */
.stat{
    display:flex;
    align-items:center;
    gap:18px;
    padding:24px 26px;
    border-radius:20px;

    background:rgba(255,255,255,.07);
    border:1px solid var(--border);
    backdrop-filter:blur(14px);
    box-shadow:0 18px 45px rgba(0,0,0,.45);

    position:relative;
    overflow:hidden;

    opacity:0;
    transform:translateY(16px);
    animation:statIn .6s cubic-bezier(.4,0,.2,1) forwards;

    transition:
        transform .35s ease,
        box-shadow .35s ease,
        background .35s ease;
}

/* delay */
.stat:nth-child(1){animation-delay:.1s}
.stat:nth-child(2){animation-delay:.2s}
.stat:nth-child(3){animation-delay:.3s}
.stat:nth-child(4){animation-delay:.4s}

/* glow sweep */
.stat::after{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(
        to right,
        rgba(235,37,37,.18),
        transparent 65%
    );
    opacity:0;
    transition:.35s ease;
    pointer-events:none;
}

.stat:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 28px 65px rgba(0,0,0,.6);
}

.stat:hover::after{
    opacity:1;
}

/* ==================================================
   MENU
================================================== */
.menu{
    position:relative;
    padding:26px;
    border-radius:20px;

    background:rgba(255,255,255,.07);
    border:1px solid var(--border);
    backdrop-filter:blur(14px);

    text-decoration:none;
    color:#fff;
    cursor:pointer;

    overflow:hidden;
    will-change: transform, opacity;

    box-shadow:0 18px 45px rgba(0,0,0,.45);

    opacity:0;
    transform:translateY(16px);
    animation:menuIn .6s cubic-bezier(.4,0,.2,1) forwards;

    transition:
        transform .35s ease,
        background .35s ease,
        box-shadow .35s ease;
}

/* delay animasi (opsional, bikin berasa satu-satu masuk) */
.menu:nth-child(1){animation-delay:.1s}
.menu:nth-child(2){animation-delay:.2s}
.menu:nth-child(3){animation-delay:.3s}
.menu:nth-child(4){animation-delay:.4s}

/* efek sweep merah */
.menu::before{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(
        120deg,
        transparent,
        rgba(235,37,37,.25),
        transparent
    );
    transform:translateX(-100%);
    transition:.6s;
    pointer-events:none;
}

.menu:hover::before{
    transform:translateX(100%);
}

/* hover naik + glow */
.menu:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 28px 65px rgba(0,0,0,.6);
}

/* teks */
.menu strong{
    font-size:15px;
    font-weight:600;
}

.menu small{
    display:block;
    margin-top:6px;
    font-size:12px;
    opacity:.75;
}

/* ==================================================
   FOOTER
================================================== */
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

/* ==================================================
   ANIMATION
================================================== */
@keyframes statIn{
    to{opacity:1;transform:translateY(0)}
}
@keyframes menuIn{
    to{opacity:1;transform:translateY(0)}
}
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}
@keyframes heroIn{
    from{opacity:0;transform:translateY(-14px)}
    to{opacity:1;transform:translateY(0)}
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

@media (max-width: 768px){

    /* NAVBAR */
    .nav-brand span{
        display:none;          /* sembunyikan teks, logo saja */
    }

    .user-name{
        display:none;          /* nama disembunyikan */
    }

    .role-badge{
        padding:4px 10px;
        font-size:10px;
    }

    /* HERO */
    .hero h1{
        font-size:22px;
    }

    .hero p{
        font-size:12px;
    }

    /* CONTAINER */
    .container{
        padding:0 16px;
        margin:20px 0 100px;
    }

    /* CARD */
    .card{
        padding:24px 20px;
    }

    /* GRID */
    .grid{
        grid-template-columns:1fr;
    }

    /* MENU */
    .menu{
        padding:22px;
    }

    /* DROPDOWN */
    .profile-dropdown{
        width:190px;
    }
}

</style>
</head>

<body>

<nav class="navbar">
    <a href="#" class="nav-brand">
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

$avatarFullPath = $avatarFile
    ? __DIR__.'/../../assets/img/avatars/'.$avatarFile
    : null;

$initial = strtoupper(substr($_SESSION['user']['name'],0,1));
?>

<div class="avatar-mini">
<?php if ($avatarPath && file_exists($avatarFullPath)): ?>
    <img src="<?= $avatarPath ?>"
         alt="Avatar"
         style="width:100%;height:100%;object-fit:cover;border-radius:50%">
<?php else: ?>
    <?= $initial ?>
<?php endif; ?>
</div>

    <span class="user-name">
        <?= htmlspecialchars($_SESSION['user']['name']); ?>
    </span>

    <span class="role-badge"><?= strtoupper($role) ?></span>
    <span class="caret">▾</span>

<div class="profile-dropdown">

<?php if($role === 'admin'): ?>
    <a href="profile.php">
        👤 <strong>Profil Saya</strong>
        <div class="profile-email">
            <?= htmlspecialchars($_SESSION['user']['email']); ?>
        </div>
    </a>

    <a href="profile.php#security">🔒 Keamanan Akun</a>
<?php endif; ?>

<?php if($role === 'superadmin'): ?>
    <a href="../superadmin/system_settings.php">
        ⚙️ <strong>System Settings</strong>
        <div class="profile-email">
            <?= htmlspecialchars($_SESSION['user']['email']); ?>
        </div>
    </a>
<?php endif; ?>

    <a href="#" class="danger" onclick="confirmLogout(event)">
        🚪 Logout
    </a>
</div>

</div>

</nav>

<div class="hero">
    <h1>Dashboard <?= htmlspecialchars(getSetting('system_name','RMBS')) ?></h1>
    <p>Monitoring & pengelolaan Room Meeting Booking System</p>
</div>

<div class="container">

    <div class="card">
        <h3>Ringkasan Sistem</h3>
        <div class="grid">
            <div class="stat">
                <div class="stat-icon">🏢</div>
                <div><small>Total Ruangan</small><h2><?= $totalRooms ?></h2></div>
            </div>
            <div class="stat">
                <div class="stat-icon">📅</div>
                <div><small>Booking Hari Ini</small><h2><?= $todayBooking ?></h2></div>
            </div>
            <div class="stat">
                <div class="stat-icon">⏳</div>
                <div><small>Booking Pending</small><h2><?= $pendingBooking ?></h2></div>
            </div>
            <div class="stat">
                <div class="stat-icon">📊</div>
                <div><small>Total Booking</small><h2><?= $totalBooking ?></h2></div>
            </div>
        </div>
    </div>

    <div class="card">
    <h3>Menu Administrasi</h3>
    <div class="grid">

        <a href="kelola_booking.php" class="menu">
            <strong>Kelola Booking</strong>
            <small>Approve & kelola jadwal</small>
        </a>

        <a href="monitoring_ruangan.php" class="menu">
            <strong>Monitoring Ruangan</strong>
            <small>Status ruangan real-time</small>
        </a>

        <a href="manajemen_ruangan.php" class="menu">
            <strong>Manajemen Ruangan</strong>
            <small>Tambah & edit ruangan</small>
        </a>

        <a href="reports.php" class="menu">
            <strong>Reports Booking</strong>
            <small>Laporan & rekap booking</small>
        </a>

        <?php if($role === 'superadmin'): ?>
        <a href="users.php" class="menu">
            <strong>Manajemen User</strong>
            <small>Admin & karyawan</small>
        </a>
        <?php endif; ?>

    </div>
</div>

    </div>

</div>

<footer class="footer">
    <span>© <?= date('Y') ?> <strong><?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?></strong></span>
    <span> • </span>
    <span><?= $footerRole ?> • <?= $footerDesc ?></span>
</footer>

<!-- LOGOUT MODAL -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">⚠️</div>
        <h3>Konfirmasi Logout</h3>
        <p>
            Anda login sebagai <strong><?= strtoupper($role) ?></strong>.<br>
            Yakin ingin keluar dari sistem?
        </p>
        <div class="logout-action">
            <button class="btn-cancel" onclick="closeLogout()">Batal</button>
            <button class="btn-yes" onclick="doLogout()">Ya, Logout</button>
        </div>
    </div>
</div>

<script>
/* ================= PAGE LOAD ================= */
document.addEventListener('DOMContentLoaded',()=>{
    document.body.classList.add('loaded');
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

/* ESC untuk tutup modal */
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeLogout();
});

/* Klik backdrop untuk tutup */
document.getElementById('logoutModal')?.addEventListener('click', e => {
    if(e.target.id === 'logoutModal') closeLogout();
});

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll',()=>{
    const cur = window.pageYOffset;

    navbar.classList.toggle('hide', cur > lastScroll && cur > 120);
    navbar.classList.toggle('shrink', cur > 90);

    lastScroll = cur <= 0 ? 0 : cur;
});

const profileTrigger = document.querySelector('.profile-trigger');
const profileDropdown = document.querySelector('.profile-dropdown');

profileTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown?.classList.toggle('show');
});

document.addEventListener('click', () => {
    profileDropdown?.classList.remove('show');
});

</script>

</body>
</html>
