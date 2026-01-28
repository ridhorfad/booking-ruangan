<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/maintenance.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

$db   = new Database();
$conn = $db->connect();

requireRole(['employee']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ===== USER SESSION ===== */
$userName  = $_SESSION['user']['name'];
$userEmail = $_SESSION['user']['email'];
$userRole  = strtoupper($_SESSION['user']['role']);

$initial = strtoupper(substr($userName, 0, 1));

$user_id = (int) $_SESSION['user']['id'];

/* ==========================
   🔁 SYNC AVATAR SESSION (ANTI BALIK INISIAL)
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
   AVATAR PATH FINAL (GLOBAL RMBS)
========================== */
$avatarPath = null;

if (!empty($_SESSION['user']['avatar'])) {
    $avatarFile = __DIR__ . '/../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    if (file_exists($avatarFile)) {
        $avatarPath = '../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    }
}

/* ===== FOOTER ROLE ===== */
$footerRole = 'Employee Portal';
$footerDesc = 'Internal User Access';

/* ================= FILTER INPUT USER ================= */
$tanggal_filter     = $_GET['tanggal'] ?? '';
$jam_mulai_filter   = $_GET['jam_mulai'] ?? '';
$jam_selesai_filter = $_GET['jam_selesai'] ?? '';

$siapCek = $tanggal_filter && $jam_mulai_filter && $jam_selesai_filter;

/* ======================================================
   QUERY CEK BENTROK BOOKING
   (AKTIF + PENDING SAJA)
====================================================== */
$bookingBentrok = [];

if ($siapCek) {
    $sqlBentrok = "
        SELECT 
            ruangan_id,
            jam_mulai,
            jam_selesai
        FROM booking
        WHERE tanggal = ?
        AND status IN ('pending','approved')
        AND NOT (
            jam_selesai <= ?
            OR jam_mulai >= ?
        )
    ";

    $stmt = mysqli_prepare($conn, $sqlBentrok);
    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $tanggal_filter,
        $jam_mulai_filter,
        $jam_selesai_filter
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $bookingBentrok[$row['ruangan_id']] = [
            'mulai'   => $row['jam_mulai'],
            'selesai' => $row['jam_selesai']
        ];
    }

    mysqli_stmt_close($stmt);
}

/* ======================================================
   DATA RUANGAN
====================================================== */
$rooms = mysqli_query($conn, "
    SELECT 
        id,
        nama,
        gambar,
        kapasitas,
        fasilitas,
        deskripsi
    FROM ruangan
    ORDER BY nama
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Booking Ruang Meeting | <?= htmlspecialchars(getSetting('system_name','RMBS')) ?>
</title>
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
    transition:opacity .5s ease;
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
    transition:.35s ease;
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
    animation:heroIn .7s cubic-bezier(.4,0,.2,1);
}

.hero h1{
    font-size:24px;
    margin-bottom:6px;
}

.hero p{
    font-size:13px;
    opacity:.8;
}

/* ===== AKSEN GARIS MERAH (PALING HALUS – HALAMAN AKSI) ===== */
.hero::after{
    content:'';
    position:absolute;
    left:50%;
    bottom:0;
    transform:translateX(-50%);
    width:80px;                         /* paling kecil */
    height:3px;
    background:linear-gradient(
        to right,
        transparent,
        var(--primary),
        transparent
    );
    border-radius:2px;
    opacity:.7;
}

.container{
    width:100%;
    max-width:none;                 
    margin:24px 0 120px;
    padding:0 clamp(20px, 4vw, 72px);
}

/* ================= BACK ================= */
.back-wrap{margin-bottom:32px}
.btn-back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:11px 24px;
    border-radius:14px;
    font-size:14px;
    font-weight:600;
    color:#fff;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    transition:.3s;
    text-decoration:none;
}
.btn-back:hover{
    background:rgba(255,255,255,.15);
    transform:translateX(-4px);
}

.card{
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid var(--border);
    border-radius:28px;
    padding:32px clamp(20px, 3vw, 40px);
    box-shadow:0 40px 90px var(--shadow);

    opacity:0;
    transform:translateY(28px);
    animation:cardUp .7s cubic-bezier(.4,0,.2,1) forwards;
}

@media(max-width:768px){
    .form-grid{
        grid-template-columns:1fr;
        gap:16px;
    }
}
/* ================= FORM ================= */
.form-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:24px;
    margin-bottom:44px;
}
label{
    font-size:13px;
    font-weight:600;
    margin-bottom:8px;
    display:block;
}

/* ================= ROOM LIST ================= */
.room-list{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:30px
}

.room-image-wrap{
    position:relative;
    width:100%;
    height:170px;          /* SAMA dengan tinggi image */
    overflow:hidden;       /* potong yang keluar */
    border-radius:18px;
}

/* DETAIL BUTTON */
.btn-detail{
    position:absolute;
    bottom:12px;
    right:12px;

    display:flex;
    align-items:center;
    gap:6px;

    font-size:11px;
    padding:7px 14px;
    border-radius:999px;

    background:rgba(15,23,42,.85);
    border:1px solid rgba(255,255,255,.25);
    color:#e5e7eb;

    cursor:pointer;
    backdrop-filter:blur(10px);

    box-shadow:0 6px 18px rgba(0,0,0,.45);

    transition:
        transform .25s ease,
        opacity .25s ease,
        background .25s ease;

    opacity:.9;
}

.room-item:hover .btn-detail{
    opacity:1;
    transform:translateY(0);
}

.btn-detail:hover{
    background:rgba(255,255,255,.15);
    transform:translateY(-1px);
}

.room-modal{
    position:fixed;
    inset:0;
    background:rgba(2,6,23,.0);
    backdrop-filter:blur(0px);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;

    opacity:0;
    pointer-events:none;
    transition:
        opacity .45s ease,
        background .45s ease,
        backdrop-filter .45s ease;
}

.room-modal.show{
    opacity:1;
    pointer-events:auto;
    background:rgba(2,6,23,.65);   
    backdrop-filter:blur(8px);     
}

.room-modal-box{
    position:relative;
    width:88%;
    max-width:760px;              
    background:linear-gradient(
        to bottom,
        rgba(2,6,23,.95),
        rgba(11,18,32,.92)
    );
    border-radius:22px;
    padding:14px;                 
    box-shadow:0 30px 80px rgba(0,0,0,.65);

    transform:translateY(28px) scale(.94);
    opacity:0;

    transition:
        transform .45s cubic-bezier(.16,1,.3,1),
        opacity .35s ease;
}

.room-modal.show .room-modal-box{
    transform:
        translateY(0)
        scale(1);
    opacity:1;
}

.room-modal.show .room-modal-box img{
    transform:scale(1);
    opacity:1;
}

.room-modal-box img{
    width:100%;
    height:340px;                 
    object-fit:cover;
    border-radius:16px;

    transform:scale(1.02);
    opacity:.9;

    transition:
        transform .5s ease,
        opacity .4s ease;
}

.room-caption{
    margin-top:10px;
    font-size:12px;
    opacity:.7;
    text-align:center;
}

.room-close{
    position:absolute;
    top:12px;
    right:12px;

    width:32px;
    height:32px;
    font-size:16px;

    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;

    border-radius:50%;
    cursor:pointer;

    transition:.25s ease;
}

.room-close:hover{
    background:rgba(255,255,255,.25);
    transform:scale(1.05);
}

.room-modal .nav{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    font-size:26px;               

    width:40px;
    height:40px;

    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;

    border-radius:50%;
    cursor:pointer;

    transition:.25s ease;
}

.room-modal .nav:hover{
    background:rgba(255,255,255,.25);
}

.room-modal .prev{left:14px}
.room-modal .next{right:14px}

.room-indicator{
    margin-top:4px;
    font-size:11px;
    opacity:.5;
    text-align:center;
}

@media(max-width:640px){
    .room-modal-box{
        width:94%;
        padding:12px;
    }

    .room-modal-box img{
        height:240px;
    }
}

.room-item{
    display:grid;
    grid-template-columns:260px 1fr 170px;
    gap:30px;
    background:rgba(255,255,255,.06);
    border:1px solid var(--border);
    border-radius:26px;
    padding:32px;

    position:relative;
    overflow:hidden;

    opacity:0;
    transform:translateY(20px);
    animation:cardUp .6s ease forwards;

    transition:
        transform .35s ease,
        box-shadow .35s ease,
        background .35s ease;
}

/* GRADIENT OVERLAY (HIDUP SAAT HOVER) */
.room-item::after{
    content:'';
    position:absolute;
    inset:0;
    border-radius:26px;
    background:linear-gradient(
        to right,
        rgba(235,37,37,.12),
        transparent 60%
    );
    opacity:0;
    transition:.35s ease;
    pointer-events:none;
}

.room-item:hover{
    transform:translateY(-6px);
    background:rgba(255,255,255,.1);
    box-shadow:0 30px 70px rgba(0,0,0,.55);
}

.room-item:hover::after{
    opacity:1;
}

/* IMAGE */
.room-item img{
    width:100%;
    height:100%;           
    object-fit:cover;
    border-radius:0;       
    transition:transform .35s ease;
}

.room-item:hover img{
    transform:scale(1.05);
}

/* INFO */
.room-info h3{font-size:18px;margin-bottom:8px}
.room-info p{font-size:14px;opacity:.85;margin-bottom:10px}
.room-meta{font-size:13px;opacity:.75}

/* ACTION BUTTON */
.room-action button{
    width:100%;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
    padding:14px 0;
    border-radius:14px;
    border:none;
    font-weight:600;
    cursor:pointer;
    transition:.3s ease;
}

.room-item:hover .room-action button:not([disabled]){
    transform:translateY(-3px);
    box-shadow:0 15px 35px rgba(235,37,37,.45);
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
.footer-glass strong{color:#fff}

/* ================= ANIMATION ================= */
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}
@keyframes heroIn{
    from{opacity:0;transform:translateY(-14px)}
    to{opacity:1;transform:translateY(0)}
}

.toast{
    position:fixed;
    top:96px;
    right:26px;
    min-width:280px;
    max-width:360px;
    padding:16px 20px;
    border-radius:14px;
    color:#fff;
    font-size:14px;
    font-weight:600;
    box-shadow:0 20px 45px rgba(0,0,0,.45);
    z-index:9999;
    animation:
        toastIn .45s cubic-bezier(.4,0,.2,1),
        toastOut .45s ease 3.8s forwards;
}

@keyframes toastIn{
    from{
        opacity:0;
        transform:translateY(-12px) scale(.95);
    }
    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

@keyframes toastOut{
    to{
        opacity:0;
        transform:translateY(-12px) scale(.95);
    }
}

.toast.error{
    background:linear-gradient(135deg,#dc2626,#991b1b);
}

.toast.success{
    background:linear-gradient(135deg,#16a34a,#065f46);
}

@keyframes slideIn{
    from{transform:translateX(100%);opacity:0}
    to{transform:translateX(0);opacity:1}
}

@keyframes fadeOut{
    to{opacity:0;transform:translateX(100%)}
}

/* ================= RESPONSIVE ================= */
@media(max-width:1100px){
    .form-grid{grid-template-columns:1fr 1fr}
    .room-item{grid-template-columns:1fr}
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
#toastContainer{
    position:fixed;
    top:96px;
    right:26px;
    z-index:99999;
}

.toast{
    min-width:280px;
    max-width:360px;
    padding:16px 20px;
    margin-bottom:12px;
    border-radius:14px;
    color:#fff;
    font-size:14px;
    font-weight:600;
    box-shadow:0 20px 45px rgba(0,0,0,.45);
    animation:
        toastIn .45s cubic-bezier(.4,0,.2,1),
        toastOut .45s ease 3.8s forwards;
}

.toast.error{
    background:linear-gradient(135deg,#dc2626,#991b1b);
}

@keyframes toastIn{
    from{opacity:0;transform:translateY(-12px) scale(.95)}
    to{opacity:1;transform:translateY(0) scale(1)}
}

@keyframes toastOut{
    to{opacity:0;transform:translateY(-12px) scale(.95)}
}

/* SAMAKAN DENGAN users.php */
.form{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:20px;
}

input{
    width:100%;
    padding:13px 15px;
    border-radius:12px;
    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.14);
    color:#e5e7eb;
    font-size:13px;
    transition:.25s ease;
}

input::placeholder{
    color:rgba(255,255,255,.45);
}

input:focus{
    outline:none;
    border-color:#eb2525;
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.9);
}

/* ===== DATE & TIME INPUT – FINAL NORMALIZED ===== */
input[type="date"],
input[type="time"]{
    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.14);
    color:#e5e7eb;
    padding:13px 15px;
    border-radius:12px;
    font-size:13px;

    /* penting untuk konsistensi */
    color-scheme: dark;
}

/* Fokus (samakan dengan halaman lain) */
input[type="date"]:focus,
input[type="time"]:focus{
    outline:none;
    border-color:#eb2525;
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.9);
}

/* Icon kalender & jam (Chrome / Edge) */
input[type="date"]::-webkit-calendar-picker-indicator,
input[type="time"]::-webkit-calendar-picker-indicator{
    filter: invert(1);
    opacity: .7;
    cursor: pointer;
}

/* Hilangkan spin button time (biar rapi) */
input[type="time"]::-webkit-inner-spin-button,
input[type="time"]::-webkit-clear-button{
    display: none;
}

/* BUTTON FULL WIDTH SEPERTI users.php */
.btn-add-full{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:none;
    font-size:13px;
    font-weight:700;
    color:#fff;
    cursor:pointer;
    background:linear-gradient(to right,#16a34a,#15803d);
    box-shadow:0 14px 35px rgba(22,163,74,.45);
    transition:.25s ease;
}

.btn-add-full:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 45px rgba(22,163,74,.6);
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

.profile-dropdown a:hover{
    background:rgba(255,255,255,.08);
}

.profile-dropdown .danger{
    color:#fca5a5;
}

.profile-dropdown.show{
    display:flex;
}

.caret{
    font-size:12px;
    opacity:.9;
}

/* ================= RIWAYAT BOOKING BUTTON ================= */
.card{
    position:relative; /* penting untuk absolute button */
}

/* ===== CARD HEADER ===== */
.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:26px;
}

.card-title{
    font-size:18px;
    font-weight:700;
    letter-spacing:.3px;
    color:#fff;
}

/* ===== BUTTON RIWAYAT ===== */
.btn-riwayat{
    font-size:12px;
    padding:6px 14px;
    border-radius:999px;

    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.18);
    color:#e5e7eb;
    text-decoration:none;

    transition:.25s ease;
}

.btn-riwayat:hover{
    background:rgba(255,255,255,.15);
    transform:translateY(-1px);
}

</style>
</head>

<body>

<div id="toastContainer"></div>

<?php if(isset($_SESSION['flash'])): ?>
<div class="toast <?= $_SESSION['flash']['type']; ?>">
    <?= htmlspecialchars($_SESSION['flash']['message']); ?>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<nav class="navbar">
    <a href="index.php" class="nav-brand" onclick="smoothRedirect(event,this.href)">
        <img src="../../assets/img/logobummnew.png" class="nav-logo">
        <span>
<?= htmlspecialchars(getSetting('system_name','RMBS')) ?> | Dashboard
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
        <a href="#" class="danger" onclick="openLogout(event)">🚪 Logout</a>
    </div>
</div>

</nav>

<div class="hero">
    <h1>Booking Ruang Meeting</h1>
<p>
<?= htmlspecialchars(getSetting('system_name','Room Meeting Booking System')) ?>
</p>
    <p>Pilih ruangan dan jadwal meeting Anda</p>
</div>

<div class="container">

<div class="back-wrap">
    <a href="index.php" class="btn-back" onclick="smoothRedirect(event,this.href)">
        ← Kembali ke Dashboard
    </a>
</div>

<div class="card">

<!-- HEADER CARD -->
    <div class="card-header">
        <h3 class="card-title">Form Booking</h3>

        <a href="booking_saya.php"
           class="btn-riwayat"
           onclick="smoothRedirect(event,this.href)">
            📋 Riwayat Booking
        </a>
    </div>

<form method="GET">

    <div class="form">

        <input type="date"
               name="tanggal"
               title="Tanggal Meeting"
               value="<?= htmlspecialchars($tanggal_filter); ?>"
               required>

        <input type="time"
               name="jam_mulai"
               title="Jam Mulai Meeting"
               value="<?= htmlspecialchars($jam_mulai_filter); ?>"
               required>

        <input type="time"
               name="jam_selesai"
               title="Jam Selesai Meeting"
               value="<?= htmlspecialchars($jam_selesai_filter); ?>"
               required>

        <!-- BUTTON DI SEBELAH KANAN -->
        <button class="btn-add-full" type="submit">
            Cek Ketersediaan
        </button>

    </div>

</form>

<hr style="margin:40px 0;border:1px solid rgba(255,255,255,.12)">

<?php if (!$siapCek): ?>
<div style="
    padding:16px 22px;
    border-radius:16px;
    background:rgba(37,99,235,.15);
    border:1px solid rgba(37,99,235,.35);
    color:#bfdbfe;
    margin-bottom:30px;
    font-size:14px;
">
    ℹ️ Silakan pilih <b>tanggal</b> dan <b>jam meeting</b>,
    lalu klik <b>Cek Ketersediaan</b> untuk melihat status ruangan.
</div>
<?php endif; ?>

<!-- ================= FORM SUBMIT BOOKING ================= -->
<form id="bookingForm" action="booking_process.php" method="POST" novalidate>
    
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">

    <!-- ===== DATA OTOMATIS ===== -->
    <input type="hidden" name="ruangan_id" id="ruangan_id">
    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_filter); ?>">
    <input type="hidden" name="jam_mulai" value="<?= htmlspecialchars($jam_mulai_filter); ?>">
    <input type="hidden" name="jam_selesai" value="<?= htmlspecialchars($jam_selesai_filter); ?>">

    <!-- ===== INPUT USER ===== -->
    <div class="form-grid">

        <div style="grid-column:1 / -1">
            <label for="keterangan">Keperluan</label>
            <input
                type="text"
                id="keterangan"
                name="keterangan"
                placeholder="Contoh: Rapat koordinasi IT"
                required
            >
        </div>

        <div>
            <label for="jumlah_tamu">Jumlah Tamu</label>
            <input
                type="number"
                id="jumlah_tamu"
                name="jumlah_tamu"
                min="1"
                placeholder="Contoh: 10"
                required
            >
        </div>

        <div style="grid-column: span 3">
            <label for="request_konsumsi">Request Konsumsi (Opsional)</label>
            <input
                type="text"
                id="request_konsumsi"
                name="request_konsumsi"
                placeholder="Snack, kopi, teh"
            >
        </div>

    </div>

</form>

<div class="room-list">
<?php while($room = mysqli_fetch_assoc($rooms)): 
    $bentrok = $siapCek && isset($bookingBentrok[$room['id']]);
?>
<div class="room-item">

    <!-- IMAGE WRAP -->
    <div class="room-image-wrap">
        <img src="../../assets/img/rooms/<?= htmlspecialchars($room['gambar']); ?>">

        <!-- BUTTON DETAIL -->
        <button
            type="button"
            class="btn-detail"
            onclick="openRoomDetail(<?= $room['id']; ?>)">
            👁 Detail
        </button>
    </div>

    <div class="room-info">
        <h3><?= htmlspecialchars($room['nama']); ?></h3>
        <p><?= htmlspecialchars($room['deskripsi']); ?></p>

        <div class="room-meta">
            Kapasitas: <?= $room['kapasitas']; ?><br>
            Fasilitas: <?= htmlspecialchars($room['fasilitas']); ?>
        </div>

        <!-- ===== STATUS RUANGAN ===== -->
        <?php if ($siapCek): ?>
            <?php if ($bentrok): ?>
                <small style="color:#fca5a5;">
                    Digunakan
                    <?= substr($bookingBentrok[$room['id']]['mulai'],0,5); ?>
                    –
                    <?= substr($bookingBentrok[$room['id']]['selesai'],0,5); ?>
                </small>
            <?php else: ?>
                <small style="color:#4ade80;">Tersedia</small>
            <?php endif; ?>
        <?php else: ?>
            <small style="opacity:.6;font-style:italic;">
                Pilih tanggal & jam lalu klik <b>Cek Ketersediaan</b>
            </small>
        <?php endif; ?>
    </div>

    <!-- ===== ACTION BUTTON ===== -->
    <div class="room-action">
        <?php if (!$siapCek): ?>
            <button type="button" disabled
                style="background:#374151;cursor:not-allowed;">
                Cek Jadwal Dulu
            </button>

        <?php elseif ($bentrok): ?>
            <button type="button" disabled
                style="background:#6b7280;cursor:not-allowed;">
                Tidak Tersedia
            </button>

        <?php else: ?>
            <button type="button"
                onclick="setRoom('<?= $room['id']; ?>')">
                Booking
            </button>
        <?php endif; ?>
    </div>
</div>

<?php endwhile; ?>
</div>
</div>
</div>

<footer class="footer">
    <span>© <?= date('Y'); ?> <strong><?= htmlspecialchars(getSetting('system_name','RMBS')) ?></strong>
    <span>•</span>
    <span><?= $footerRole ?></span>
    <span>•</span>
    <span><?= $footerDesc ?></span>
</footer>

<!-- LOGOUT MODAL -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">⚠️</div>
        <h3>Konfirmasi Logout</h3>
        <p>
            Anda login sebagai <strong>EMPLOYEE</strong>.<br>
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
    document.body.classList.add('loaded');
});

/* ================= SMOOTH REDIRECT ================= */
function smoothRedirect(e, url){
    e.preventDefault();
    document.body.classList.add('fade-out');
    setTimeout(() => location.href = url, 400);
}

/* ================= SET ROOM & BOOKING ================= */
function setRoom(id){
    const keperluanInput = document.getElementById('keterangan');
    const tamuInput      = document.getElementById('jumlah_tamu');

    const keperluan  = keperluanInput ? keperluanInput.value.trim() : '';
    const jumlahTamu = tamuInput ? tamuInput.value.trim() : '';

    /* ================= VALIDASI ================= */
    if(!keperluan){
        showToast('Keperluan wajib diisi');
        keperluanInput?.focus();
        return;
    }

    if(!jumlahTamu || isNaN(jumlahTamu) || parseInt(jumlahTamu) <= 0){
        showToast('Jumlah tamu harus diisi dengan benar');
        tamuInput?.focus();
        return;
    }

    /* ================= VALIDASI FORM ================= */
    const form = document.getElementById('bookingForm');
    const ruanganInput = document.getElementById('ruangan_id');

    if(!form || !ruanganInput){
        showToast('Form booking tidak valid');
        return;
    }

    /* ================= SET DATA ================= */
    ruanganInput.value = id;

    /* ================= SUBMIT ================= */
    form.submit();

    /* ================= UI LOCK ================= */
    document.querySelectorAll('.room-action button').forEach(btn => {
        btn.disabled = true;
        btn.innerText = 'Memproses...';
    });
}

/* ================= TOAST ================= */
function showToast(message, type = 'error'){
    const container = document.getElementById('toastContainer');
    if(!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerText = message;

    container.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
}

/* ================= LOGOUT MODAL ================= */
function openLogout(e){
    e.preventDefault();
    document.getElementById('logoutModal')?.classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal')?.classList.remove('show');
}

function doLogout(){
    document.body.classList.add('fade-out');
    setTimeout(() => {
        window.location.href='../../auth/logout_process.php';
    }, 400);
}

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    let cur = window.pageYOffset;

    navbar?.classList.toggle('hide', cur > lastScroll && cur > 100);
    navbar?.classList.toggle('shrink', cur > 80);

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

let roomImages = [];
let roomIndex = 0;

/* OPEN */
function openRoomDetail(id){
    fetch(`ruangan_detail_ajax.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if(!data.length){
                showToast('Belum ada detail ruangan', 'error');
                return;
            }

            roomImages = data;
            roomIndex = 0;
            renderRoomImage();

            document.getElementById('roomModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        });
}

/* RENDER */
function renderRoomImage(){
    const img = roomImages[roomIndex];

    document.getElementById('roomModalImg').src =
        '../../assets/img/rooms/' + img.gambar;

    document.getElementById('roomModalCaption').innerText =
        img.posisi ? ('Posisi: ' + img.posisi) : '';

    document.getElementById('roomModalIndicator').innerText =
        (roomIndex + 1) + ' / ' + roomImages.length;
}

/* SLIDE */
function slideRoom(dir){
    roomIndex += dir;
    if(roomIndex < 0) roomIndex = roomImages.length - 1;
    if(roomIndex >= roomImages.length) roomIndex = 0;
    renderRoomImage();
}

/* CLOSE */
function closeRoomDetail(e){
    if(e && e.target !== e.currentTarget) return;

    document.getElementById('roomModal').classList.remove('show');
    document.body.style.overflow = '';
}

/* KEYBOARD */
document.addEventListener('keydown', e => {
    if(!document.getElementById('roomModal').classList.contains('show')) return;

    if(e.key === 'Escape') closeRoomDetail();
    if(e.key === 'ArrowRight') slideRoom(1);
    if(e.key === 'ArrowLeft') slideRoom(-1);
});

/* SWIPE (MOBILE) */
let touchStartX = 0;
document.getElementById('roomModalImg').addEventListener('touchstart', e=>{
    touchStartX = e.changedTouches[0].screenX;
});
document.getElementById('roomModalImg').addEventListener('touchend', e=>{
    const diff = e.changedTouches[0].screenX - touchStartX;
    if(Math.abs(diff) > 50){
        slideRoom(diff > 0 ? -1 : 1);
    }
});

</script>

<style>
.profile-dropdown.show{display:flex}
</style>

<div class="room-modal" id="roomModal" onclick="closeRoomDetail(event)">
    <div class="room-modal-box" onclick="event.stopPropagation()">

        <button class="room-close" onclick="closeRoomDetail()">✕</button>

        <button class="nav prev" onclick="slideRoom(-1)">‹</button>
        <button class="nav next" onclick="slideRoom(1)">›</button>

        <img id="roomModalImg">

        <div class="room-caption" id="roomModalCaption"></div>
        <div class="room-indicator" id="roomModalIndicator"></div>

    </div>
</div>

</body>
</html>
