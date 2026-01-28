<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

$db = new Database();
$conn = $db->connect();

requireRole(['employee']);

$user_id = $_SESSION['user']['id'];

/* ===== USER SESSION ===== */
$userName  = $_SESSION['user']['name'];
$userEmail = $_SESSION['user']['email'];
$userRole  = strtoupper($_SESSION['user']['role']);

$initial = strtoupper(substr($userName, 0, 1));

/* ===== FOOTER ===== */
$footerRole = 'Employee Portal';
$footerDesc = 'Internal User Access';
$roleBadge  = strtoupper($_SESSION['user']['role']);

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
   AVATAR PATH (STANDAR RMBS)
========================== */
$avatarPath = null;

if (!empty($_SESSION['user']['avatar'])) {
    $avatarFile = __DIR__ . '/../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    if (file_exists($avatarFile)) {
        $avatarPath = '../../assets/img/avatars/' . $_SESSION['user']['avatar'];
    }
}

$query = "
    SELECT 
        b.tanggal, 
        b.jam_mulai, 
        b.jam_selesai, 
        b.keperluan,
        b.status,
        b.cancel_reason,          -- ✅ TAMBAHAN
        r.nama AS nama_ruangan,
        r.kapasitas,
        r.fasilitas,
        r.gambar
    FROM booking b
    JOIN ruangan r ON b.ruangan_id = r.id
    WHERE b.user_id = ?
    ORDER BY b.tanggal DESC, b.jam_mulai DESC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Booking Saya | <?= htmlspecialchars(getSetting('system_name','RMBS')) ?>
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
    display:flex;
    flex-direction:column;

    padding-top:72px;
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
.fade-out{opacity:0}

/* NAVBAR */
.navbar{
    position:fixed;inset:0 0 auto 0;
    z-index:1000;
    background:rgba(15,23,42,.78);
    backdrop-filter:blur(12px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 8px 25px rgba(0,0,0,.45);
    transition:.35s ease;
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}

.nav-brand{
    display:flex;align-items:center;gap:12px;
    color:#fff;text-decoration:none;font-weight:700;
    font-size:14px;
}
.nav-logo{height:34px;transition:.3s}
.navbar.shrink .nav-logo{height:26px}

.nav-user{
    display:flex;align-items:center;gap:14px;font-size:13px;
}
.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
}

/* ===== PROFILE (SAMA DENGAN index.php & booking.php) ===== */
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

.user-name{
    font-size:13px;
    font-weight:500;
}

.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
    letter-spacing:.3px;
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
    width:200px;
    background:rgba(15,23,42,.9);
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

.hero{
    position:relative;
    padding:48px clamp(20px, 4vw, 72px) 34px;
    text-align:center;
    animation:heroIn .7s cubic-bezier(.4,0,.2,1);
}

.hero h1{font-size:24px;margin-bottom:6px}
.hero p{font-size:13px;opacity:.85}

.hero::after{
    content:'';
    position:absolute;
    left:50%;
    bottom:0;
    transform:translateX(-50%);
    width:90px;
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

.container{
    width:100%;
    max-width:none;
    margin:24px 0 120px;
    padding:0 clamp(20px, 4vw, 72px);
    flex:1;
}

/* ================= BACK BUTTON ================= */
.back-wrap{
    margin-bottom:32px;
}

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
    padding:32px clamp(20px, 3vw, 40px);
    border-radius:26px;
    box-shadow:0 40px 90px var(--shadow);
    animation:cardUp .7s cubic-bezier(.4,0,.2,1) forwards;
}

/* BOOKING LIST */
.booking-list{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:24px;
}

/* BOOKING ITEM */
.booking-item{
    background:rgba(255,255,255,.06);
    border:1px solid var(--border);
    border-radius:22px;
    overflow:hidden;
    transition:.35s;
    position:relative;
}
.booking-item:hover{
    transform:translateY(-4px);
    background:rgba(255,255,255,.1);
}

/* HEADER */
.booking-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 22px;
    background:linear-gradient(
        to right,
        rgba(0,0,0,.35),
        rgba(0,0,0,.15)
    );
    font-size:13px;
    border-bottom:1px solid rgba(255,255,255,.08);
}

.booking-item::after{
    content:'';
    position:absolute;
    inset:0;
    border-radius:22px;
    background:linear-gradient(
        to right,
        rgba(235,37,37,.12),
        transparent 60%
    );
    opacity:0;
    transition:.35s;
    pointer-events:none;
}

.booking-item:hover::after{
    opacity:1;
}

.booking-item:hover{
    transform:translateY(-6px);
    box-shadow:0 30px 70px rgba(0,0,0,.55);
}

.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
}
.badge.pending{
    background:rgba(234,179,8,.18);
    color:#fde68a;
}
.badge.approved{
    background:rgba(34,197,94,.2);
    color:#4ade80;
}
.badge.rejected{
    background:rgba(239,68,68,.2);
    color:#fca5a5;
}
.badge.done{
    background:rgba(59,130,246,.2);
    color:#93c5fd;
}

.badge.upcoming{background:rgba(22,163,74,.2);color:#4ade80}

/* BODY */
.booking-body{
    display:grid;
    grid-template-columns:1fr 200px;
    gap:26px;
    padding:28px;
}
.booking-left{
    display:flex;
    gap:22px;
}
.booking-img{
    width:150px;
    height:110px;
    border-radius:16px;
    object-fit:cover;
    border:1px solid var(--border);
}
.booking-info h3{font-size:18px;margin-bottom:6px}
.booking-info p{font-size:14px;opacity:.85;margin-bottom:10px}
.booking-meta{font-size:13px;opacity:.75;line-height:1.6}

.booking-time{
    text-align:right;
    font-size:14px;
    line-height:1.8;
    font-weight:600;
    color:#e5e7eb;
}

/* EMPTY */
.empty{
    text-align:center;
    padding:80px 0;
    opacity:.75;
    font-size:15px;
}

.footer{
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    padding:20px clamp(20px, 4vw, 72px);
    font-size:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    border-top:1px solid rgba(255,255,255,.12);
}

.footer strong{color:#fff}

/* ANIMATION */
@keyframes heroIn{
    from{opacity:0;transform:translateY(-14px)}
    to{opacity:1;transform:translateY(0)}
}
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}

/* RESPONSIVE */
@media(max-width:900px){
    .booking-body{grid-template-columns:1fr}
    .booking-time{text-align:left}
    .booking-left{flex-direction:column}
    .booking-img{width:100%;height:180px}
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
    background:rgba(255,255,255,.15);
    border:none;
    color:#fff;
    font-weight:600;
}
.btn-yes{
    flex:1;
    padding:12px;
    border-radius:14px;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    border:none;
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

    <span class="user-name">
        <?= htmlspecialchars($_SESSION['user']['name']); ?>
    </span>

    <span class="role-badge"><?= $roleBadge ?></span>
    <span class="caret">▾</span>

    <div class="profile-dropdown">
        <a href="profile.php">
            👤 <strong>Profil Saya</strong>
            <div class="profile-email">
                <?= htmlspecialchars($_SESSION['user']['email']); ?>
            </div>
        </a>
        <a href="profile.php#security">🔒 Keamanan Akun</a>
        <a href="#" class="danger" onclick="openLogout(event)">🚪 Logout</a>
    </div>
</div>

</nav>

<div class="hero">
    <h1>Booking Saya</h1>
<p>
Riwayat pemesanan ruang meeting –
<?= htmlspecialchars(getSetting('system_name','Room Meeting Booking System')) ?>
</p>
</div>

<div class="container">

<?php if(isset($_SESSION['flash'])): ?>
<div id="flashToast" style="
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
    background:linear-gradient(135deg,#16a34a,#065f46);
    box-shadow:0 20px 45px rgba(0,0,0,.45);
    z-index:9999;
    animation:
        toastIn .45s cubic-bezier(.4,0,.2,1),
        toastOut .45s ease 3.8s forwards;
">
    <?= htmlspecialchars($_SESSION['flash']['message']); ?>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<!-- ===== BACK BUTTON ===== -->
<div class="back-wrap">
    <a href="#"
       class="btn-back"
       onclick="goBack(event)">
        ← Kembali
    </a>
</div>

<div class="card">

<?php if(mysqli_num_rows($result) === 0): ?>
    <div class="empty">
        📭 Belum ada booking yang dilakukan.<br>
        <small>Silakan lakukan pemesanan ruang meeting.</small>
    </div>

<?php else: ?>
<div class="booking-list">
<?php while($row = mysqli_fetch_assoc($result)):
$now = time();
$end = strtotime($row['tanggal'].' '.$row['jam_selesai']);

if ($row['status'] === 'pending') {
    $status = 'pending';
    $label  = '⏳ MENUNGGU PERSETUJUAN';
}
elseif ($row['status'] === 'rejected') {
    $status = 'rejected';
    $label  = '❌ DITOLAK';
}
elseif ($row['status'] === 'cancelled') {
    $status = 'rejected'; // tetap pakai style merah
    $label  = '🚫 DIBATALKAN ADMIN';
}
elseif ($row['status'] === 'approved') {

    if ($end >= $now) {
        $status = 'approved';
        $label  = '✅ DISETUJUI';
    } else {
        $status = 'done';
        $label  = '✔️ SELESAI';
    }

}
else {
    // fallback aman jika ada status aneh
    $status = 'pending';
    $label  = '⏳ MENUNGGU PERSETUJUAN';
}

?>
<div class="booking-item">

    <!-- HEADER -->
    <div class="booking-head">
        <span class="badge <?= $status ?>">
            <?= $label ?>
        </span>
        <span><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
    </div>

    <!-- BODY -->
    <div class="booking-body">
        <div class="booking-left">
            <img src="../../assets/img/rooms/<?= htmlspecialchars($row['gambar']); ?>" class="booking-img">
            <div class="booking-info">
    <h3><?= htmlspecialchars($row['nama_ruangan']); ?></h3>

    <p><?= htmlspecialchars($row['keperluan']); ?></p>

    <!-- 🔴 BOOKING INFO (ALASAN CANCEL) -->
    <?php if ($row['status'] === 'cancelled'): ?>
        <div style="
            margin:10px 0 12px;
            padding:10px 14px;
            border-radius:12px;
            background:rgba(220,38,38,.12);
            border:1px solid rgba(220,38,38,.35);
            font-size:12.5px;
            color:#fca5a5;
        ">
            <strong>Alasan Pembatalan:</strong><br>
            <?= htmlspecialchars($row['cancel_reason'] ?: 'Tidak ada keterangan') ?>
        </div>
    <?php endif; ?>

    <div class="booking-meta">
        Kapasitas: <?= $row['kapasitas']; ?> orang<br>
        Fasilitas: <?= htmlspecialchars($row['fasilitas']); ?>
    </div>
</div>

        </div>

        <div class="booking-time">
            ⏰ <?= substr($row['jam_mulai'],0,5); ?> – <?= substr($row['jam_selesai'],0,5); ?>
        </div>
    </div>

</div>

<?php endwhile; ?>
</div>
<?php endif; ?>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

</div>
</div>

<footer class="footer">
    <span>© <?= date('Y') ?> <strong><?= htmlspecialchars(getSetting('system_name','RMBS')) ?></strong>
    <span>•</span>
    <span><?= $footerRole ?> • <?= $footerDesc ?></span>
</footer>

<!-- LOGOUT MODAL -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">⚠️</div>
        <h3>Konfirmasi Logout</h3>
        <p>
            Anda login sebagai <strong><?= $roleBadge ?></strong>.<br>
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

/* ================= SMOOTH REDIRECT ================= */
function smoothRedirect(e,url){
    e.preventDefault();
    document.body.classList.add('fade-out');
    setTimeout(()=>location.href=url,400);
}

/* ================= HISTORY BACK (RESET STATE) ================= */
function goBack(e){
    e.preventDefault();
    document.body.classList.add('fade-out');

    // ⛔ jangan pakai history.back()
    // ✅ redirect langsung agar form & button reset
    setTimeout(()=>{
        window.location.href = 'booking.php';
    },400);
}

/* ================= AUTO REMOVE FLASH TOAST ================= */
document.addEventListener('DOMContentLoaded',()=>{
    const toast = document.getElementById('flashToast');
    if(toast){
        setTimeout(()=>{
            toast.remove();
        }, 4500); // sedikit lebih lama dari animasi
    }
});

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
    setTimeout(()=>{
        window.location.href='../../auth/logout_process.php';
    },400);
}

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll',()=>{
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
</script>

</body>
</html>
