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

if (!isset($_GET['id'])) {
    header("Location: kelola_booking.php");
    exit;
}

$booking_id = (int)$_GET['id'];

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

/* ================= AMBIL DATA BOOKING ================= */
$query = "
    SELECT 
        b.id,
        b.tanggal,
        b.jam_mulai,
        b.jam_selesai,
        b.keperluan,
        b.jumlah_tamu,
        b.request_konsumsi,
        b.biaya,
        b.status,
        b.cancel_reason,
        b.cancelled_at,   
        b.created_at,

        u.name AS pemesan,
        u.email,

        r.nama AS ruangan,
        r.kapasitas,
        r.fasilitas,
        r.gambar

    FROM booking b
    JOIN users u ON b.user_id = u.id
    JOIN ruangan r ON b.ruangan_id = r.id
    WHERE b.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    header("Location: kelola_booking.php");
    exit;
}
function rupiah($angka){
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Detail Booking | <?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?>
</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
    --success:#16a34a;
    --warning:#f59e0b;
    --danger:#dc2626;
    --glass:rgba(255,255,255,.06);
    --border:rgba(255,255,255,.12);
}

/* RESET */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif}

body{
    min-height:100vh;
    display:flex;
    flex-direction:column;

    padding-top:80px;
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
body.page-loaded{opacity:1}

/* ===== SMOOTH EXIT ===== */
.fade-out{
    opacity:0;
}

/* ===== NAVBAR EFFECT ===== */
.navbar.hide{
    transform:translateY(-100%);
}
.navbar.shrink{
    padding:8px 26px;
}
.navbar.shrink .nav-logo{
    height:26px;
}

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
    z-index:1000;
}
.nav-brand{
    display:flex;align-items:center;gap:12px;
    color:#fff;text-decoration:none;font-weight:700;
    font-size:14px;
}

/* ===== PENYELARASAN NAVBAR (GLOBAL ADMIN STYLE) ===== */
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

.nav-logo{height:32px}
.nav-user{display:flex;gap:14px;font-size:13px;align-items:center}
.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;border-radius:999px;font-size:11px
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

/* CONTENT */
.wrapper{
    flex:1; /* 🔥 INI KUNCINYA */
    max-width:1100px;
    margin:40px auto 0;
    padding:0 22px 120px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:36px;
}
.page-header h1::after{
    content:'';
    display:block;
    width:44px;
    height:3px;
    margin-top:8px;
    background:linear-gradient(to right,var(--primary),#b91c1c);
    border-radius:2px;
}
.page-header h1{font-size:26px}
.page-header p{font-size:13px;opacity:.8}

.btn-back{
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:10px 20px;
    border-radius:12px;
    color:#fff;text-decoration:none;
    font-size:13px;
}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:36px;
    backdrop-filter:blur(16px);

    opacity:0;
    transform:translateY(22px);
    animation:cardUp .6s cubic-bezier(.4,0,.2,1) forwards;
}

@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:36px;
}

/* IMAGE */
.room-img{
    width:100%;
    height:220px;
    object-fit:cover;
    border-radius:18px;
    border:1px solid var(--border);
}

/* INFO */
.info h3{font-size:20px;margin-bottom:10px}
.info p{font-size:14px;opacity:.85;margin-bottom:12px}
.meta{font-size:13px;opacity:.75;line-height:1.6}

/* STATUS */
.badge{
    display:inline-block;
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
}

.pending{
    background:rgba(245,158,11,.18);
    color:#fbbf24;
}
.approved{
    background:rgba(22,163,74,.18);
    color:#22c55e;
}
.rejected{
    background:rgba(220,38,38,.18);
    color:#f87171;
}
.cancelled{
    background:rgba(249,115,22,.18);
    color:#fb923c;
}

/* ACTION */
.action{
    margin-top:24px;
    display:flex;
    gap:12px;
}
.btn{
    padding:10px 22px;
    border-radius:12px;
    font-size:13px;
    font-weight:600;
    color:#fff;
    text-decoration:none;
}
.btn{
    transition:transform .25s ease, box-shadow .25s ease;
}
.btn:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 24px rgba(0,0,0,.35);
}
.btn:active{
    transform:scale(.96);
}
.btn-approve{background:linear-gradient(to right,#16a34a,#15803d)}
.btn-reject{background:linear-gradient(to right,#dc2626,#991b1b)}

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

<?php if (isset($_SESSION['flash'])): ?>
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
    background:
        <?= $_SESSION['flash']['type'] === 'success'
            ? 'linear-gradient(135deg,#16a34a,#065f46)'
            : 'linear-gradient(135deg,#dc2626,#7f1d1d)' ?>;
    box-shadow:0 20px 45px rgba(0,0,0,.45);
    z-index:9999;
    animation:toastIn .45s ease, toastOut .45s ease 3.8s forwards;
">
    <?= htmlspecialchars($_SESSION['flash']['message']) ?>
</div>

<style>
@keyframes toastIn{
    from{opacity:0;transform:translateY(-10px)}
    to{opacity:1;transform:translateY(0)}
}
@keyframes toastOut{
    to{opacity:0;transform:translateY(-10px)}
}
</style>

<?php unset($_SESSION['flash']); ?>
<?php endif; ?>


<nav class="navbar">
    <a href="index.php"
       class="nav-brand"
       onclick="smoothRedirect(event,this.href)">

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
        <?= htmlspecialchars($_SESSION['user']['name']); ?>
    </span>

    <span class="role-badge">
        <?= strtoupper($_SESSION['user']['role']); ?>
    </span>

    <span class="caret">▾</span>

    <div class="profile-dropdown">
        <a href="profile.php">
            👤 <strong>Profil Saya</strong>
            <div class="profile-email">
                <?= htmlspecialchars($_SESSION['user']['email']); ?>
            </div>
        </a>

        <a href="profile.php#security">🔒 Keamanan Akun</a>

        <a href="#" class="danger" onclick="openLogout()">
            🚪 Logout
        </a>
    </div>

</div>

</nav>

<div class="wrapper">

<div class="page-header">
    <div>
        <h1>Detail Booking</h1>
        <p>Informasi lengkap pemesanan ruangan</p>
    </div>
    <a href="kelola_booking.php" class="btn-back">← Kembali</a>
</div>

<div class="card">
<div class="grid">

    <img src="../../assets/img/rooms/<?= htmlspecialchars($data['gambar']) ?>"
         class="room-img">

    <div class="info">
       <?php
$statusLabel = match($data['status']){
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'cancelled' => 'Cancelled',
    default     => ucfirst($data['status'])
};
?>

<span class="badge <?= $data['status'] ?>">
    <?= $statusLabel ?>
</span>

<?php if ($data['status'] === 'cancelled'): ?>
    <div style="margin-top:14px;padding:14px 16px;border-radius:14px;
        background:rgba(249,115,22,.12);
        border:1px solid rgba(249,115,22,.35);
        font-size:13px;color:#fed7aa;">
        <strong>🚫 Booking Dibatalkan Admin</strong><br>
        <span style="opacity:.9">
            Alasan:
            <?= htmlspecialchars($data['cancel_reason'] ?: 'Tidak ada keterangan') ?>
        </span><br>

        <?php if(!empty($data['cancelled_at'])): ?>
            <small style="opacity:.7">
                Dibatalkan pada <?= date('d M Y H:i', strtotime($data['cancelled_at'])) ?>
            </small>
        <?php endif; ?>
    </div>
<?php endif; ?>

        <h3><?= htmlspecialchars($data['ruangan']) ?></h3>
        <p><?= htmlspecialchars($data['keperluan']) ?></p>

        <div class="meta">
            <strong>Kapasitas Ruangan:</strong> <?= $data['kapasitas'] ?> orang<br>
<strong>Jumlah Tamu:</strong> <?= (int)$data['jumlah_tamu'] ?> orang<br>
<strong>Request Konsumsi:</strong>
<?= $data['request_konsumsi'] ? htmlspecialchars($data['request_konsumsi']) : '-' ?><br><br>

<strong>Biaya:</strong>
<?php if (in_array($data['status'], ['approved','cancelled']) && $data['biaya'] !== null): ?>
    <span style="color:#22c55e;font-weight:600">
        <?= rupiah($data['biaya']) ?>
    </span>

    <?php if ($data['status'] === 'cancelled'): ?>
        <span style="display:block;font-size:12px;color:#fca5a5;margin-top:4px">
            (Biaya sebelum dibatalkan)
        </span>
    <?php endif; ?>

<?php else: ?>
    <span style="opacity:.6;font-style:italic">
        Belum ditentukan
    </span>
<?php endif; ?>

<br><br>

<strong>Fasilitas:</strong> <?= htmlspecialchars($data['fasilitas']) ?><br>
<strong>Dibuat:</strong> <?= date('d M Y H:i', strtotime($data['created_at'])) ?>
        </div>

    </div>

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
            Anda login sebagai <strong><?= strtoupper($_SESSION['user']['role']) ?></strong>.<br>
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

window.addEventListener('scroll',()=>{
    const cur = window.pageYOffset || document.documentElement.scrollTop;

    navbar.classList.toggle('hide', cur > lastScroll && cur > 100);
    navbar.classList.toggle('shrink', cur > 80);

    lastScroll = cur <= 0 ? 0 : cur;
});

/* ================= LOGOUT MODAL ================= */
function openLogout(){
    document.getElementById('logoutModal').classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal').classList.remove('show');
}

function doLogout(){
    document.body.classList.add('fade-out');
    setTimeout(()=>{
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

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
