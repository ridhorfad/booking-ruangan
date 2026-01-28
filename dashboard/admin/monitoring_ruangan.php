<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

/* ===== ROLE & FOOTER ===== */
$role = $_SESSION['user']['role'] ?? 'admin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

/* ===== DATABASE ===== */
$db   = new Database();
$conn = $db->connect();

/* ===== SYNC AVATAR SESSION (FINAL & WAJIB) ===== */
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

/* ===== AVATAR NAVBAR ===== */
$avatarFile = $_SESSION['user']['avatar'] ?? null;
$avatarPath = $avatarFile
    && file_exists(__DIR__.'/../../assets/img/avatars/'.$avatarFile)
    ? '../../assets/img/avatars/'.$avatarFile
    : null;

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$now     = date('H:i:s');

/* ===== DATA RUANGAN (MONITORING HARIAN) ===== */
$rooms = mysqli_query($conn,"
    SELECT 
        r.id,
        r.nama,
        b.jam_mulai,
        b.jam_selesai,
        CASE
            WHEN b.jam_mulai IS NULL THEN 'available'
            WHEN b.jam_mulai <= '$now' AND b.jam_selesai >= '$now' THEN 'used'
            WHEN b.jam_mulai > '$now' THEN 'upcoming'
            ELSE 'available'
        END AS status_ruangan
    FROM ruangan r
    LEFT JOIN booking b 
        ON r.id = b.ruangan_id
        AND b.tanggal = '$tanggal'
        AND b.status = 'approved'
    ORDER BY r.nama
");

/* ===== SUMMARY ===== */
$totalRooms = mysqli_fetch_assoc(mysqli_query(
    $conn,"SELECT COUNT(*) total FROM ruangan"
))['total'] ?? 0;

$usedRooms = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(DISTINCT ruangan_id) total
    FROM booking
    WHERE tanggal = '$tanggal'
    AND status = 'approved'
    AND jam_mulai <= '$now'
    AND jam_selesai >= '$now'
"))['total'] ?? 0;

$availableRooms = $totalRooms - $usedRooms;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
<?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?> | Monitoring Ruangan
</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
    --success:#16a34a;
    --danger:#dc2626;
    --warning:#facc15;
    --ease:cubic-bezier(.4,0,.2,1);
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
    padding-top:78px;
    opacity:0;
    transition:.5s;
}
body.page-loaded{opacity:1}

/* NAVBAR */
.navbar{
    position:fixed;
    top:0;left:0;right:0;
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(12px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 15px 40px rgba(0,0,0,.6);
    transition:
        transform .35s ease,
        padding .35s ease,
        background .35s ease;
    z-index:1000;
}

.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}

.nav-brand{
    display:flex;align-items:center;gap:12px;
    color:#fff;text-decoration:none;font-weight:700;
    font-size:14px;
}

/* ===== GLOBAL ADMIN NAVBAR FINAL ===== */
.nav-brand span{
    font-size:14px;
    letter-spacing:.3px;
}

.nav-logo{
    height:34px;
    transition:.35s ease;
}

.navbar.shrink .nav-logo{
    height:26px;
}

.user-name{
    font-size:13px;
    font-weight:500;
}

.role-badge{
    font-size:11px;
}

.nav-logo{height:32px}

.nav-user{display:flex;gap:14px;font-size:13px}
.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;border-radius:999px;font-size:11px;
}
.btn-logout{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:7px 18px;border-radius:10px;
    color:#fff;text-decoration:none;font-weight:600;
}

/* ===== PROFILE NAVBAR (ADMIN CONSISTENT) ===== */
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
    overflow:hidden;
}

.user-name{
    font-size:13px;
    font-weight:500;
}

.caret{
    font-size:12px;
    opacity:.9;
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

/* CONTENT */
.wrapper{padding:40px 26px 120px}

.page-header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:26px;
}
.page-header h1{font-size:26px}
.page-header h1::after{
    content:'';display:block;width:44px;height:3px;margin-top:8px;
    background:linear-gradient(to right,var(--primary),#b91c1c);
}
.page-header p{font-size:13px;opacity:.75}

.btn-back{
    padding:10px 22px;border-radius:14px;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    color:#fff;text-decoration:none;font-size:13px;
}

/* FILTER */
/* ================= FILTER BAR ================= */
.filter-bar{
    display:flex;
    justify-content:flex-end;
    margin-bottom:26px;
}

.filter-form{
    display:flex;
    gap:8px;
    align-items:center;
}

.filter-form input{
    padding:9px 14px;
    border-radius:12px;

    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    height:36;

    font-size:13px;
    transition:
        border .25s ease,
        box-shadow .25s ease,
        background .25s ease;
}

.filter-form input:hover{
    background:rgba(255,255,255,.1);
}

.filter-form input:focus{
    outline:none;
    border-color:#eb2525;
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(255,255,255,.12);
}

.btn-filter{
    padding:9px 16px;
    border-radius:12px;

    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
    border:none;

    font-size:13px;
    font-weight:600;
    cursor:pointer;
    height:36px;

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        opacity .25s ease;
}

.btn-filter:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(235,37,37,.45);
    opacity:.95;
}

.btn-filter:active{
    transform:scale(.96);
}


/* SUMMARY */
.summary{
    display:grid;grid-template-columns:repeat(3,1fr);
    gap:18px;margin-bottom:30px;
}
.box{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:22px;
    padding:24px;
    text-align:center;
    transition:transform .35s var(--ease), box-shadow .35s var(--ease);
}

.box:hover{
    transform:translateY(-6px);
    box-shadow:0 25px 60px rgba(0,0,0,.45);
}
.box h2{font-size:30px}
.box small{opacity:.65}

/* TABLE */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:30px;
    backdrop-filter:blur(16px);

    opacity:0;
    transform:translateY(20px);
    animation:cardIn .6s var(--ease) forwards;
}

@keyframes cardIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:14px;text-align:center}
th{
    text-transform:uppercase;font-size:11px;opacity:.7;
    border-bottom:1px solid rgba(255,255,255,.18);
}

tbody tr{
    transition:
        background .25s ease,
        transform .25s ease;
}

tbody tr:hover{
    background:rgba(255,255,255,.05);
    transform:scale(1.01);
}

.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
    position:relative;
}

.used::after{
    content:'';
    position:absolute;
    inset:-4px;
    border-radius:999px;
    background:rgba(220,38,38,.25);
    opacity:.35;
    animation:pulse 2s infinite;
}

@keyframes pulse{
    0%{transform:scale(.95);opacity:.4}
    70%{transform:scale(1.2);opacity:0}
    100%{opacity:0}
}

.available{background:rgba(22,163,74,.2);color:#16a34a}
.used{background:rgba(220,38,38,.2);color:#dc2626}
.upcoming{background:rgba(234,179,8,.2);color:#facc15}

/* FOOTER */
.footer{
    margin-top:80px;
    background:rgba(15,23,42,.75);
    border-top:1px solid rgba(255,255,255,.12);
    padding:18px;text-align:center;font-size:12px;
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
.btn-cancel{
    flex:1;
    padding:12px;
    border-radius:14px;
    border:none;
    background:rgba(255,255,255,.15);
    color:#fff;
    font-weight:600;
}
.btn-yes{
    flex:1;
    padding:12px;
    border-radius:14px;
    border:none;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
    font-weight:600;
}

</style>
</head>

<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand" onclick="smoothRedirect(event,this.href)">
    <img src="../../assets/img/logobummnew.png" class="nav-logo">
    <span>
<?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?> | Dashboard
</span>
</a>

    <div class="nav-user profile-trigger">

    <div class="avatar-mini">
        <?php if ($avatarPath): ?>
            <img src="<?= $avatarPath ?>"
                 alt="Avatar"
                 style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
        <?php else: ?>
            <?= strtoupper(substr($_SESSION['user']['name'],0,1)) ?>
        <?php endif; ?>
    </div>

    <span class="user-name">
        <?= htmlspecialchars($_SESSION['user']['name']) ?>
    </span>

    <span class="role-badge">
        <?= strtoupper($role) ?>
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

        <a href="#" class="danger" onclick="openLogout(event)">
            🚪 Logout
        </a>
    </div>

</div>

</nav>

<div class="wrapper">

    <div class="page-header">
        <div>
            <h1>Monitoring Ruangan</h1>
<p> Status penggunaan ruangan meeting</p>
        </div>
        <a href="index.php" class="btn-back" onclick="smoothRedirect(event,this.href)">
    ← Dashboard
</a>
    </div>

    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="date" name="tanggal" value="<?= $tanggal ?>">
            <button class="btn-filter">Cari</button>
        </form>
    </div>

    <div class="summary">
        <div class="box">
            <h2><?= $totalRooms ?></h2>
            <small>Total Ruangan</small>
        </div>
        <div class="box">
            <h2><?= $usedRooms ?></h2>
            <small>Dipakai</small>
        </div>
        <div class="box">
            <h2><?= $availableRooms ?></h2>
            <small>Tersedia</small>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Ruangan</th>
                    <th>Status</th>
                    <th>Jam</th>
                </tr>
            </thead>
            <tbody>
            <?php while($r = mysqli_fetch_assoc($rooms)): ?>
                <?php
                if ($r['status_ruangan'] === 'used') {
                    $label = 'DIPAKAI'; $cls = 'used';
                } elseif ($r['status_ruangan'] === 'upcoming') {
                    $label = 'AKAN DIPAKAI'; $cls = 'upcoming';
                } else {
                    $label = 'TERSEDIA'; $cls = 'available';
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['nama']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
                    <td>
                        <?= $r['jam_mulai']
                            ? substr($r['jam_mulai'],0,5).' - '.substr($r['jam_selesai'],0,5)
                            : '-' ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
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
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-loaded');
});

function smoothRedirect(e, url){
    e.preventDefault();
    document.body.classList.add('fade-out');
    setTimeout(()=>location.href = url, 400);
}

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const y = window.pageYOffset || document.documentElement.scrollTop;

    // hide saat scroll turun
    navbar?.classList.toggle('hide', y > lastScroll && y > 100);

    // shrink saat scroll sedikit
    navbar?.classList.toggle('shrink', y > 80);

    lastScroll = y <= 0 ? 0 : y;
});

/* ===== PROFILE DROPDOWN ===== */
const profileTrigger = document.querySelector('.profile-trigger');
const profileDropdown = document.querySelector('.profile-dropdown');

profileTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown?.classList.toggle('show');
});

document.addEventListener('click', () => {
    profileDropdown?.classList.remove('show');
});

/* ===== LOGOUT MODAL ===== */
function openLogout(e){
    e.preventDefault();
    document.getElementById('logoutModal')?.classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal')?.classList.remove('show');
}

function doLogout(){
    document.body.classList.remove('page-loaded');
    document.body.classList.add('fade-out');

    setTimeout(() => {
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

/* ESC untuk tutup modal */
document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeLogout();
});

/* Klik backdrop untuk tutup */
document.getElementById('logoutModal')?.addEventListener('click', (e) => {
    if(e.target.id === 'logoutModal') closeLogout();
});

</script>

</body>
</html>
