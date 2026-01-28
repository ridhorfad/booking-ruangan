<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

/* ===== AKSES KHUSUS SUPERADMIN ===== */
requireRole(['superadmin']);

/* ===== DATABASE ===== */
$db   = new Database();
$conn = $db->connect();

/* ===== SINKRON AVATAR SESSION ===== */
if (!isset($_SESSION['user']['avatar'])) {

    $uid = (int) $_SESSION['user']['id'];

    $qAvatar = mysqli_query($conn,"
        SELECT avatar
        FROM users
        WHERE id = $uid
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($qAvatar)) {
        $_SESSION['user']['avatar'] = $row['avatar']; // bisa null
    }
}

/* ===== USER STAT ===== */
$totalUser = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM users"))['total'] ?? 0;
$totalAdmin = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM users WHERE role='admin'"))['total'] ?? 0;
$totalEmployee = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM users WHERE role='employee'"))['total'] ?? 0;

/* ===== BOOKING STAT ===== */
$totalBooking = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM booking"))['total'] ?? 0;
$pendingBooking = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM booking WHERE status='pending'"))['total'] ?? 0;

$month = date('Y-m');
$monthlyBooking = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) total FROM booking 
    WHERE DATE_FORMAT(tanggal,'%Y-%m')='$month'
"))['total'] ?? 0;

$topRoom = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT r.nama, COUNT(*) total
    FROM booking b
    JOIN ruangan r ON b.ruangan_id = r.id
    WHERE b.status='approved'
    GROUP BY r.id
    ORDER BY total DESC
    LIMIT 1
"));

$busyHour = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT HOUR(jam_mulai) jam, COUNT(*) total
    FROM booking
    WHERE status='approved'
    GROUP BY jam
    ORDER BY total DESC
    LIMIT 1
"));

$approvalRate = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT 
        ROUND(
            SUM(status='approved') / COUNT(*) * 100, 1
        ) rate
    FROM booking
"));

$totalIncome = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(biaya) total
    FROM booking
    WHERE status='approved'
"))['total'] ?? 0;

$role = $_SESSION['user']['role'] ?? 'superadmin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>RMBS | Superadmin Dashboard</title>
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

body{
    min-height:100vh;
    padding-top:72px; /* ⬅️ dari 76px */
    color:#e5e7eb;
    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 45%),
        linear-gradient(to bottom right,#0b1220,#020617);
    background-size:28px 28px,28px 28px,cover,cover;
}

body.loaded{opacity:1}

.fade-out{
    opacity:0;
    transition:opacity .4s ease;
}

/* NOISE */
body::after{
    content:'';
    position:fixed;
    inset:0;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='.035'/%3E%3C/svg%3E");
    pointer-events:none;
    z-index:-1;
}

/* BLUR SHAPE */
.bg-shape{
    position:fixed;
    width:420px;
    height:420px;
    background:rgba(235,37,37,.28);
    filter:blur(120px);
    border-radius:50%;
    z-index:-1;
}
.bg-1{top:-140px;left:-140px}
.bg-2{bottom:-160px;right:-160px}

/* ==================================================
   NAVBAR
================================================== */
.navbar{
    position:fixed;
    inset:0 0 auto 0;
    z-index:1000;
    background:rgba(15,23,42,.75);
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

.hero{
    position:relative;
    padding:48px clamp(20px, 4vw, 72px) 34px;
    text-align:center;
    animation:heroIn .8s cubic-bezier(.4,0,.2,1);
}

.hero h1{
    font-size:24px;
    margin-bottom:6px;
}

.hero p{
    font-size:13px;
    opacity:.8;
}

.badge{
    display:inline-block;
    margin-top:14px;
    background:rgba(255,255,255,.15);
    padding:6px 14px;
    border-radius:999px;
    font-size:12px;
}

/* ===== AKSEN GARIS MERAH ===== */
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

.container{
    width:100%;
    max-width:none;
    margin:24px 0 120px;
    padding:0 clamp(20px, 4vw, 72px);
}

.card{
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid var(--border);
    border-radius:28px;
    padding:32px clamp(20px, 3vw, 40px);
    box-shadow:0 40px 90px var(--shadow);
    margin-bottom:40px;
}

.card:nth-child(1){animation-delay:.15s}
.card:nth-child(2){animation-delay:.3s}
.card:nth-child(3){animation-delay:.45s}

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

.card-desc{
    display:block;
    margin:6px 0 22px;
    font-size:12px;
    opacity:.6;
}

/* ==================================================
   GRID
================================================== */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:22px;
}

/* ==================================================
   STAT
================================================== */
.stat{
    display:flex;
    align-items:center;
    gap:18px;
    padding:26px;
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

/* delay animasi masuk */
.stat:nth-child(1){animation-delay:.1s}
.stat:nth-child(2){animation-delay:.2s}
.stat:nth-child(3){animation-delay:.3s}
.stat:nth-child(4){animation-delay:.4s}

/* glow halus */
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

.stat::before{
    content:'';
    position:absolute;
    left:0;
    top:0;
    width:4px;
    height:100%;
    background:linear-gradient(to bottom,var(--primary),transparent);
    opacity:.6;
}

/* hover hidup */
.stat:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 28px 65px rgba(0,0,0,.6);
}
.stat:hover::after{
    opacity:1;
}

/* icon */
.stat-icon{
    font-size:22px;
    background:rgba(235,37,37,.18);
    padding:12px;
    border-radius:12px;
    line-height:1;
}

/* text */
.stat h2{
    font-size:28px;
    margin-top:2px;
}
.stat small{
    font-size:12px;
    opacity:.75;
}

/* animasi masuk */
@keyframes statIn{
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
    animation:cardUp .5s ease forwards;

    transition:
        transform .35s ease,
        background .35s ease,
        box-shadow .35s ease;
}

/* delay animasi masuk (opsional, biar satu-satu) */
.menu:nth-child(1){animation-delay:.1s}
.menu:nth-child(2){animation-delay:.2s}
.menu:nth-child(3){animation-delay:.3s}
.menu:nth-child(4){animation-delay:.4s}

/* sweep merah */
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

/* glow halus */
.menu::after{
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

/* hover hidup */
.menu:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 28px 65px rgba(0,0,0,.6);
}
.menu:hover::before{
    transform:translateX(100%);
}
.menu:hover::after{
    opacity:1;
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

/* animasi masuk */
@keyframes menuIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
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

/* ==================================================
   ANIMATION
================================================== */
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}
@keyframes heroIn{
    from{opacity:0;transform:translateY(-12px)}
    to{opacity:1;transform:translateY(0)}
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
    overflow:hidden;
    background:linear-gradient(135deg,#eb2525,#b91c1c);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:700;
}

.avatar-mini img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.user-name{
    font-size:13px;
}

.caret{
    font-size:12px;
    opacity:.85;
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

<div class="bg-shape bg-1"></div>
<div class="bg-shape bg-2"></div>

<nav class="navbar">
    <a href="#" class="nav-brand">
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
        <img src="<?= $avatarPath ?>" alt="Avatar">
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

<div class="hero">
    <h1>System Administration</h1>
<p>Ringkasan operasional & kendali sistem RMBS</p>

    <?php if (getSetting('maintenance_mode') === 'on'): ?>
        <span class="badge" style="background:rgba(251,191,36,.18);color:#facc15">
            ⚠️ Maintenance Mode Aktif
        </span>
    <?php endif; ?>
</div>

<div class="container">

    <div class="card">
        <h3>System Overview</h3>
        <div class="grid">
            <div class="stat"><div class="stat-icon">👥</div><div><small>Total User</small><h2><?= $totalUser ?></h2></div></div>
            <div class="stat"><div class="stat-icon">🛡️</div><div><small>Admin</small><h2><?= $totalAdmin ?></h2></div></div>
            <div class="stat"><div class="stat-icon">👤</div><div><small>Karyawan</small><h2><?= $totalEmployee ?></h2></div></div>
        </div>
    </div>

    <div class="card">
        <h3>Booking Analytics</h3>

<small class="card-desc">
        Data operasional sistem booking
    </small>

        <div class="grid">
            <div class="stat"><div class="stat-icon">📊</div><div><small>Total Booking</small><h2><?= $totalBooking ?></h2></div></div>
            <div class="stat"><div class="stat-icon">⏳</div><div><small>Pending</small><h2><?= $pendingBooking ?></h2></div></div>
            <div class="stat"><div class="stat-icon">📅</div><div><small>Bulan Ini</small><h2><?= $monthlyBooking ?></h2></div></div>
        </div>
    </div>

    <div class="card">
    <h3>Laporan & Analitik</h3>
    <div class="grid">

        <div class="stat">
            <div class="stat-icon">🏢</div>
            <div>
                <small>Ruangan Terpopuler</small>
                <h2><?= $topRoom['nama'] ?? '-' ?></h2>
            </div>
        </div>

        <div class="stat">
            <div class="stat-icon">⏰</div>
            <div>
                <small>Jam Tersibuk</small>
                <h2><?= isset($busyHour['jam']) ? $busyHour['jam'].':00' : '-' ?></h2>
            </div>
        </div>

        <div class="stat">
            <div class="stat-icon">✅</div>
            <div>
                <small>Tingkat Persetujuan</small>
                <h2><?= $approvalRate['rate'] ?? 0 ?>%</h2>
            </div>
        </div>

        <div class="stat">
            <div class="stat-icon">💰</div>
            <div>
                <small>Total Pendapatan</small>
                <h2>Rp <?= number_format((int)$totalIncome,0,',','.') ?></h2>
            </div>
        </div>

    </div>
</div>

<div class="card">
    <h3>Insight Sistem</h3>
    <p style="font-size:13px;opacity:.85;line-height:1.7">
        Ruangan <strong><?= $topRoom['nama'] ?? '-' ?></strong> merupakan ruangan
        yang paling sering digunakan.  
        Aktivitas booking paling tinggi terjadi pada jam
        <strong><?= isset($busyHour['jam']) ? $busyHour['jam'].':00' : '-' ?></strong>.
        Tingkat persetujuan booking saat ini berada di
        <strong><?= $approvalRate['rate'] ?? 0 ?>%</strong>.
    </p>
</div>

    <div class="card">
        <h3>System Control Panel</h3>
<p style="font-size:13px;opacity:.7;margin-bottom:18px">
Akses penuh konfigurasi dan monitoring sistem
</p>
        <div class="grid">
            <a href="users.php" class="menu"><strong>Manajemen User</strong><small>Admin & Karyawan</small></a>
            <a href="audit_logs.php" class="menu"><strong>Audit Log</strong><small>Keamanan sistem</small></a>
            <a href="system_settings.php" class="menu"><strong>System Settings</strong><small>Konfigurasi RMBS</small></a>
            <a href="reports.php" class="menu"><strong>Laporan & Analitik</strong><small>Monitoring & Insight System</small></a>
        </div>
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

/* ================= NAVBAR AUTO HIDE ================= */
let lastScroll=0;
const navbar=document.querySelector('.navbar');
window.addEventListener('scroll',()=>{
    const cur=window.pageYOffset;
    navbar.classList.toggle('hide',cur>lastScroll&&cur>120);
    navbar.classList.toggle('shrink',cur>90);
    lastScroll=cur<=0?0:cur;
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

<?php
mysqli_close($conn);
?>

</body>
</html>
