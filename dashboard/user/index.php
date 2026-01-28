<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/maintenance.php'; // ✅ TAMBAHKAN DI SINI
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

$db   = new Database();
$conn = $db->connect();

requireRole(['employee']);

$userName  = $_SESSION['user']['name'];
$userEmail = $_SESSION['user']['email'];
$userRole  = strtoupper($_SESSION['user']['role']);


$avatarPath = null;

if (!empty($_SESSION['user']['avatar'])) {
    $avatarFile = __DIR__.'/../../assets/img/avatars/'.$_SESSION['user']['avatar'];
    if (file_exists($avatarFile)) {
        $avatarPath = '../../assets/img/avatars/'.$_SESSION['user']['avatar'];
    }
}

$initial = strtoupper(substr($userName, 0, 1));

$user_id = (int) $_SESSION['user']['id'];

/* ==========================
   SYNC AVATAR SESSION (ANTI BALIK INISIAL)
========================== */
if (empty($_SESSION['user']['avatar'])) {
    $qAvatar = mysqli_query($conn, "
        SELECT avatar
        FROM users
        WHERE id = $user_id
        LIMIT 1
    ");
    if ($r = mysqli_fetch_assoc($qAvatar)) {
        $_SESSION['user']['avatar'] = $r['avatar'] ?? null;
    }
}

/* ==========================
   AVATAR PATH FINAL (GLOBAL)
========================== */
$avatarPath = null;

if (!empty($_SESSION['user']['avatar'])) {
    $avatarFile = __DIR__ . '/../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    if (file_exists($avatarFile)) {
        $avatarPath = '../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    }
}

$initial = strtoupper(substr($userName, 0, 1));

/* ==========================
   DATA RUANGAN AKTIF (SLIDER)
========================== */
$qRooms = mysqli_query($conn, "
    SELECT 
        nama,
        kapasitas,
        fasilitas,
        deskripsi,
        gambar
    FROM ruangan
    WHERE status = 'aktif'
    ORDER BY nama ASC
");

/* ==========================
   HITUNG STATUS BOOKING USER
========================== */
$qStatus = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')  AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'rejected') AS rejected
    FROM booking
    WHERE user_id = $user_id
"));

$total    = (int) $qStatus['total'];
$pending  = (int) $qStatus['pending'];
$approved = (int) $qStatus['approved'];
$rejected = (int) $qStatus['rejected'];

$footerRole = 'Employee Portal';
$footerDesc = 'Internal User Access';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>RMBS | Dashboard User</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
    --primary-dark:#b91c1c;
    --glass:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.18);
    --shadow:rgba(0,0,0,.55);
}

/* RESET */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif}

/* ================= BASE ================= */
body{
    min-height:100vh;
    padding-top:72px;
    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 40%),
        linear-gradient(to bottom right,#0b1220,#020617);
    background-size:28px 28px,28px 28px,cover,cover;
    color:#e5e7eb;

    /* smooth masuk */
    opacity:0;
    transition:opacity .6s ease;
}

body.loaded{
    opacity:1;
}

/* smooth keluar */
.fade-out{
    opacity:0;
}

/* ================= NAVBAR ================= */
.navbar{
    position:fixed;
    inset:0 0 auto 0;
    z-index:1000;
    background:rgba(15,23,42,.78);
    backdrop-filter:blur(12px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    transition:.35s ease;
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
.nav-logo{
    height:32px;
    transition:.35s;
}
.navbar.shrink .nav-logo{height:26px}

.nav-user{
    display:flex;
    align-items:center;
    gap:14px;
    font-size:13px;          /* booking.php */
}

.user-name{
    font-size:13px;
    font-weight:500;
}

.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;          /* booking.php */
    letter-spacing:.3px;
}

.caret{
    font-size:12px;          /* booking.php */
    opacity:.9;
    margin-left:2px;
    transform:translateY(1px); /* sejajar teks */
}

/* ================= HERO (DIPERKECIL) ================= */
.hero{
    position:relative;
    padding:48px clamp(20px, 4vw, 72px) 34px;
    text-align:center;
    animation:heroIn .6s cubic-bezier(.4,0,.2,1);
}

.hero h1{
    font-size:24px;
    margin-bottom:4px;
}

.hero p{
    font-size:13px;
    opacity:.8;
}

/* ===== AKSEN GARIS MERAH (KONSISTEN SEMUA ROLE) ===== */
.hero::after{
    content:'';
    position:absolute;
    left:50%;
    bottom:0;
    transform:translateX(-50%);
    width:90px;                         /* lebih kecil dari admin */
    height:3px;
    background:linear-gradient(
        to right,
        transparent,
        var(--primary),
        transparent
    );
    border-radius:2px;
    opacity:.8;
}

.hero .cta{
    margin-top:22px;
}

.hero .cta a{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
    padding:10px 26px;
    border-radius:24px;
    text-decoration:none;
    font-weight:600;
    font-size:12px;
    transition:.35s;
}

.hero .cta a:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 30px rgba(235,37,37,.45);
}

.container{
    width:100%;
    max-width:none;              /* ⬅️ kunci utama */
    margin:24px 0 120px;
    padding:0 clamp(20px, 4vw, 72px);
}

/* ================= STAT CARDS ================= */
.stat-card:hover{
    transform:translateY(-4px);
    background:rgba(255,255,255,.1);
}

.stat-title{
    font-size:12px;
    opacity:.75;
    margin-bottom:8px;
}

.stat-value{
    font-size:26px;
    font-weight:700;
}

.stat-meta{
    margin-top:10px;
    font-size:11px;
    opacity:.6;
}

/* WARNA STATUS */
.stat-card.total h3{color:#60a5fa}
.stat-card.pending h3{color:#fbbf24}
.stat-card.approved h3{color:#22c55e}
.stat-card.rejected h3{color:#f87171}

/* ================= CARD ================= */
.card{
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid var(--border);
    padding:32px clamp(20px, 3vw, 40px);
    border-radius:26px;
    box-shadow:0 40px 90px rgba(0,0,0,.55);
    margin-bottom:40px;

    opacity:0;
    transform:translateY(18px);
    animation:cardUp .6s cubic-bezier(.4,0,.2,1) forwards;
}
.card:nth-child(1){animation-delay:.12s}
.card:nth-child(2){animation-delay:.25s}

.card h3{
    font-size:18px;
    margin-bottom:22px;
}
.card h3::after{
    content:'';
    width:38px;
    height:3px;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    display:block;
    margin-top:8px;
    border-radius:2px;
}

/* ===== STAT GRID EMPLOYEE ===== */
.stat-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:22px;
    margin:20px 0 40px;
}

.stat-card{
    display:flex;
    align-items:center;
    gap:18px;
    background:rgba(255,255,255,.07);
    border:1px solid var(--border);
    padding:22px 24px;
    border-radius:20px;
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

/* delay tetap */
.stat-card:nth-child(1){animation-delay:.1s}
.stat-card:nth-child(2){animation-delay:.2s}
.stat-card:nth-child(3){animation-delay:.3s}
.stat-card:nth-child(4){animation-delay:.4s}

/* glow halus */
.stat-card::after{
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
.stat-card:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 28px 65px rgba(0,0,0,.6);
}

.stat-card:hover::after{
    opacity:1;
}

/* isi */
.stat-icon{
    font-size:26px;
}

.stat-body span{
    font-size:12px;
    opacity:.7;
}

.stat-body h3{
    font-size:26px;
    margin:4px 0;
}

.stat-body small{
    font-size:11px;
    opacity:.6;
}

@keyframes statIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* ================= INFO GRID ================= */
.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}
.info-box{
    background:rgba(255,255,255,.06);
    border:1px solid var(--border);
    padding:24px;
    border-radius:18px;
    font-size:13px;

    position:relative;
    overflow:hidden;

    backdrop-filter:blur(12px);
    box-shadow:0 14px 32px rgba(0,0,0,.4);

    transition:
        transform .35s ease,
        box-shadow .35s ease,
        background .35s ease;
}

/* glow halus */
.info-box::after{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(
        to right,
        rgba(235,37,37,.18),
        transparent 70%
    );
    opacity:0;
    transition:.35s ease;
    pointer-events:none;
}

/* hover hidup */
.info-box:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.12);
    box-shadow:0 24px 55px rgba(0,0,0,.55);
}

.info-box:hover::after{
    opacity:1;
}

/* ================= ROOM SLIDER ================= */
.room-slider-wrapper{
    overflow:hidden;
}
.room-slider-wrapper:hover .room-slider{
    animation-play-state:paused;
}
.room-slider{
    display:flex;
    gap:32px;              /* ⬅️ beri napas */
    width:max-content;
    animation:slideAuto 45s linear infinite;
}

@keyframes slideAuto{
    from{ transform:translateX(0); }
    to{ transform:translateX(-50%); }
}

/* ================= ROOM CARD (IMPROVED) ================= */
.room-card{
    min-width:340px;                 /* ⬅️ lebih proporsional */
    max-width:340px;
    background:rgba(255,255,255,.07);
    border-radius:22px;
    overflow:hidden;
    border:1px solid var(--border);
    box-shadow:0 18px 45px rgba(0,0,0,.5);
    transition:.35s ease;
    flex-shrink:0;
}

.room-card:hover{
    transform:translateY(-8px) scale(1.02);
    box-shadow:0 28px 70px rgba(0,0,0,.65);
}

@media(max-width:768px){
    .room-card{
        min-width:260px;
        max-width:260px;
    }

    .room-card img{
        height:160px;
    }
}

/* IMAGE */
.room-card img{
    width:100%;
    height:200px;                    /* ⬅️ lebih tinggi */
    object-fit:cover;
    display:block;
}

.room-card img{
    width:100%;
    height:180px;
    object-fit:cover;
}
.room-body{
    padding:18px 20px 22px;
}

.room-body h4{
    margin-bottom:6px;
    font-size:15px;
    font-weight:600;
}

.room-body p{
    font-size:12px;
    opacity:.8;
    line-height:1.5;
}

.room-badge{
    position:absolute;
    top:12px;
    left:12px;
    background:rgba(0,0,0,.55);
    backdrop-filter:blur(6px);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
}

/* ================= FOOTER ================= */
.footer{
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    padding:20px;
    font-size:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    border-top:1px solid rgba(255,255,255,.12);
}
.footer strong{color:#fff}

/* ================= ANIMATION ================= */
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}
@keyframes heroIn{
    from{opacity:0;transform:translateY(-10px)}
    to{opacity:1;transform:translateY(0)}
}
@keyframes slideAuto{
    from{transform:translateX(0)}
    to{transform:translateX(-50%)}
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
    transition:.35s cubic-bezier(.4,0,.2,1);
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
}

.profile-dropdown{
    position:absolute;
    top:120%;
    right:0;
    width:200px;
    background:rgba(15,23,42,.9);
    border:1px solid rgba(255,255,255,.15);
    border-radius:14px;
    backdrop-filter:blur(16px);
    box-shadow:0 25px 60px rgba(0,0,0,.6);
    display:none;
    flex-direction:column;
    overflow:hidden;
}

.profile-dropdown a{
    padding:12px 16px;
    font-size:13px;
    color:#e5e7eb;
    text-decoration:none;
}

.profile-email{
    font-size:11px;      /* booking.php */
    opacity:.6;
    margin-top:4px;
}

.profile-dropdown a:hover{
    background:rgba(255,255,255,.08);
}

.profile-dropdown .danger{
    color:#fca5a5;
}

.profile-dropdown.show{display:flex}

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
<div class="avatar-mini">
    <?php if ($avatarPath): ?>
        <img src="<?= $avatarPath ?>"
             alt="Avatar"
             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
    <?php else: ?>
        <?= $initial ?>
    <?php endif; ?>
</div>

    <span class="user-name"><?= htmlspecialchars($userName) ?></span>

    <span class="role-badge"><?= $userRole ?></span>

    <span class="caret">▾</span>

    <div class="profile-dropdown">
        <a href="profile.php">
            👤 <strong>Profil Saya</strong>
            <div class="profile-email">
                <?= htmlspecialchars($userEmail) ?>
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
    <h1><?= htmlspecialchars(getSetting('system_name', 'Room Meeting Booking System')) ?></h1>
    <p>Selamat datang, <strong><?= htmlspecialchars($userName); ?></strong></p>
    <p>Booking ruang meeting cepat & profesional</p>
    <div class="cta">
        <a href="booking.php" onclick="smoothRedirect(event,this.href)">Booking Sekarang</a>
    </div>
</div>

<div class="container">

    <!-- ===== STAT EMPLOYEE ===== -->
   <div class="stat-grid">

    <div class="stat-card total">
        <div class="stat-icon">📊</div>
        <div class="stat-body">
            <span>Total Booking</span>
            <h3><?= $total; ?></h3>
            <small>Semua pemesanan</small>
        </div>
    </div>

    <div class="stat-card pending">
        <div class="stat-icon">⏳</div>
        <div class="stat-body">
            <span>Pending</span>
            <h3><?= $pending; ?></h3>
            <small>Menunggu persetujuan</small>
        </div>
    </div>

    <div class="stat-card approved">
        <div class="stat-icon">✅</div>
        <div class="stat-body">
            <span>Disetujui</span>
            <h3><?= $approved; ?></h3>
            <small>Booking aktif</small>
        </div>
    </div>

    <div class="stat-card rejected">
        <div class="stat-icon">❌</div>
        <div class="stat-body">
            <span>Ditolak</span>
            <h3><?= $rejected; ?></h3>
            <small>Tidak disetujui</small>
        </div>
    </div>

</div>

    <!-- ===== END STAT ===== -->

    <div class="card">
        <h3>Kenapa menggunakan <?= htmlspecialchars(getSetting('system_name','RMBS')) ?>?</h3>
        <div class="info-grid">
            <div class="info-box">📅 Booking cepat & efisien</div>
            <div class="info-box">⏰ Jadwal real-time</div>
            <div class="info-box">✅ Transparan & tercatat</div>
        </div>
    </div>

    <div class="card">
    <h3>Preview Ruang Meeting</h3>

    <div class="room-slider-wrapper">
    <div class="room-slider">

        <?php
        // simpan data ke array
        $rooms = [];
        while($r = mysqli_fetch_assoc($qRooms)){
            $rooms[] = $r;
        }
        ?>

        <?php if(count($rooms) > 0): ?>

            <!-- LOOP PERTAMA -->
            <?php foreach($rooms as $room): ?>
            <div class="room-card">
                <img 
                    src="../../assets/img/rooms/<?= htmlspecialchars($room['gambar']) ?>" 
                    alt="<?= htmlspecialchars($room['nama']) ?>"
                >
                <div class="room-body">
                    <h4><?= htmlspecialchars($room['nama']) ?></h4>
                    <p>Kapasitas <?= (int)$room['kapasitas'] ?> orang</p>

                    <?php if(!empty($room['fasilitas'])): ?>
                    <p style="opacity:.7;font-size:11px;margin-top:6px">
                        <?= htmlspecialchars($room['fasilitas']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- LOOP DUPLIKAT (WAJIB UNTUK INFINITE) -->
            <?php foreach($rooms as $room): ?>
            <div class="room-card">
                <img 
                    src="../../assets/img/rooms/<?= htmlspecialchars($room['gambar']) ?>" 
                    alt="<?= htmlspecialchars($room['nama']) ?>"
                >
                <div class="room-body">
                    <h4><?= htmlspecialchars($room['nama']) ?></h4>
                    <p>Kapasitas <?= (int)$room['kapasitas'] ?> orang</p>

                    <?php if(!empty($room['fasilitas'])): ?>
                    <p style="opacity:.7;font-size:11px;margin-top:6px">
                        <?= htmlspecialchars($room['fasilitas']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <p style="opacity:.7;padding:20px">
                Belum ada ruangan aktif
            </p>
        <?php endif; ?>

    </div>
</div>

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
            Anda login sebagai <strong><?= strtoupper($userRole) ?></strong>.<br>
            Yakin ingin keluar dari sistem?
        </p>
        <div class="logout-action">
            <button class="btn-cancel" onclick="closeLogout()">Batal</button>
            <button class="btn-yes" onclick="doLogout()">Ya, Logout</button>
        </div>
    </div>
</div>

<script>
/* PAGE LOAD */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('loaded');
});

/* SMOOTH REDIRECT */
function smoothRedirect(e, url){
    e.preventDefault();
    document.body.classList.add('fade-out');
    setTimeout(() => {
        window.location.href = url;
    }, 400);
}

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

/* NAVBAR AUTO HIDE + SHRINK */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    let cur = window.pageYOffset;
    navbar.classList.toggle('hide', cur > lastScroll && cur > 100);
    navbar.classList.toggle('shrink', cur > 80);
    lastScroll = cur <= 0 ? 0 : cur;
});

document.querySelectorAll('.stat-body h3').forEach(el=>{
    const target = +el.innerText;
    let count = 0;
    const step = Math.ceil(target / 20);

    const interval = setInterval(()=>{
        count += step;
        if(count >= target){
            el.innerText = target;
            clearInterval(interval);
        }else{
            el.innerText = count;
        }
    },30);
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
