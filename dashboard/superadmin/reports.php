<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

$db   = new Database();
$conn = $db->connect();

$role = $_SESSION['user']['role'] ?? 'admin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator';
    $footerDesc = 'System Management Access';
}

/* ===== FILTER TANGGAL (DEFAULT AWAL BULAN) ===== */
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-d');

$where = '';

/* ===== VALIDASI & WHERE DINAMIS ===== */
if (!empty($start) && !empty($end)) {

    // jika tanggal terbalik → tukar
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $where = "WHERE b.tanggal BETWEEN '$start' AND '$end'";
}

/* DATA REPORT BOOKING */
$report = mysqli_query($conn,"
    SELECT 
        b.id,
        b.tanggal,
        b.jam_mulai,
        b.jam_selesai,
        b.status,
        u.name AS user_name,
        r.nama AS ruangan
    FROM booking b
    JOIN users u ON b.user_id = u.id
    JOIN ruangan r ON b.ruangan_id = r.id
    $where
    ORDER BY b.tanggal DESC
");

/* SUMMARY */
$summary = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT 
        COUNT(*) AS total,
        SUM(status='pending')   AS pending,
        SUM(status='approved')  AS approved,
        SUM(status='rejected')  AS rejected,
        SUM(status='cancelled') AS cancelled
    FROM booking b
    $where
"));

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reports | RMBS</title>
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

/* ===== WRAPPER ===== */
.wrapper{
    flex:1;
    padding:40px 26px 120px;
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
/* FILTER */
/* ===== FILTER ===== */
.filter{
    display:flex;
    gap:10px;
}

.filter input{
    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.14);
    color:#e5e7eb;
    padding:10px 12px;
    border-radius:12px;
    font-size:13px;
    transition:.25s;
}

.filter input::placeholder{
    color:rgba(255,255,255,.45);
}

.filter input:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.9);
}

.btn-filter{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    border:none;
    color:#fff;
    padding:10px 18px;
    border-radius:14px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
    transition:.25s;
}

.btn-filter:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(235,37,37,.45);
}

.btn-filter:active{
    transform:scale(.97);
}

.btn-filter{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    border:none;
    color:#fff;
    padding:10px 18px;
    border-radius:14px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
    transition:.25s;
}

.btn-reset{
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:10px 18px;
    border-radius:14px;
    font-size:12px;
    font-weight:600;
    color:#fff;
    text-decoration:none;
    transition:.25s;
}
.btn-reset:hover{
    background:rgba(255,255,255,.16);
}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:34px;

    backdrop-filter:blur(16px);
    box-shadow:0 40px 90px rgba(0,0,0,.55);

    opacity:0;
    transform:translateY(18px);
    animation:cardUp .6s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes cardUp{
    to{opacity:1;transform:translateY(0)}
}

/* SUMMARY */
.summary{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:30px;
}

.box{
    padding:22px;
    border-radius:20px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.12);
    backdrop-filter:blur(12px);
    box-shadow:0 18px 40px rgba(0,0,0,.45);
    transition:.3s;

    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:4px;
}

.box:hover{
    transform:translateY(-4px);
}

.box h3{
    font-size:24px;
}

.box span{
    font-size:12px;
    opacity:.65;
}


/* ================= TABLE (FINAL – CONSISTENT ADMIN STYLE) ================= */
table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
    table-layout:fixed; /* kunci struktur */
}

th,td{
    padding:14px 16px;
    border-bottom:1px solid rgba(255,255,255,.06);
    text-align:center;
    vertical-align:middle;
}

/* ===== KUNCI KOLOM (PROPORSI STABIL) ===== */
th:nth-child(1),
td:nth-child(1){
    width:14%; /* Tanggal */
}

th:nth-child(2),
td:nth-child(2){
    width:20%; /* User */
}

th:nth-child(3),
td:nth-child(3){
    width:24%; /* Ruangan */
}

th:nth-child(4),
td:nth-child(4){
    width:22%; /* Jam */
}

th:nth-child(5),
td:nth-child(5){
    width:20%; /* Status */
}

/* ===== HOVER ROW ===== */
tbody tr:hover{
    background:rgba(255,255,255,.05);
}

/* ===== HEADER ===== */
th{
    font-size:11px;
    text-transform:uppercase;
    opacity:.7;
    letter-spacing:.5px;
}

/* ===== STATUS BADGE ===== */
.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
    display:inline-block;
}

.pending{
    background:rgba(245,158,11,.2);
    color:#f59e0b;
}

.approved{
    background:rgba(22,163,74,.2);
    color:#16a34a;
}

.rejected{
    background:rgba(220,38,38,.2);
    color:#dc2626;
}

.cancelled{
    background:rgba(220,38,38,.25);
    color:#fecaca;
}



/* FOOTER */
.footer{
    background:rgba(15,23,42,.75);
    padding:22px;
    font-size:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    border-top:1px solid rgba(255,255,255,.12);
}
body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
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

</style>
</head>

<body>

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
        <h1>Reports Booking</h1>
        <form class="filter" method="GET">
    <input type="date"
           name="start"
           title="Tanggal Mulai"
           value="<?= $start ?>">

    <input type="date"
           name="end"
           title="Tanggal Akhir"
           value="<?= $end ?>">

    <button type="submit" class="btn-filter">Filter</button>
    <a href="reports.php" class="btn-reset">Reset</a>
</form>

    </div>

    <div class="card">

        <div class="summary">
    <div class="box"><h3><?= $summary['total'] ?></h3><span>Total Booking</span></div>
    <div class="box"><h3><?= $summary['pending'] ?></h3><span>Pending</span></div>
    <div class="box"><h3><?= $summary['approved'] ?></h3><span>Approved</span></div>
    <div class="box"><h3><?= $summary['rejected'] ?></h3><span>Rejected</span></div>
    <div class="box"><h3><?= $summary['cancelled'] ?></h3><span>Cancelled</span></div>
</div>

        <table>
            <tr>
                <th>Tanggal</th>
                <th>User</th>
                <th>Ruangan</th>
                <th>Jam</th>
                <th>Status</th>
            </tr>
            <?php while($r = mysqli_fetch_assoc($report)): ?>
            <tr>
                <td><?= $r['tanggal'] ?></td>
                <td><?= htmlspecialchars($r['user_name']) ?></td>
                <td><?= htmlspecialchars($r['ruangan']) ?></td>
                <td><?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?></td>
                <td>
                    <span class="badge <?= $r['status'] ?>">
                        <?= strtoupper($r['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
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

/* ESC untuk tutup modal */
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeLogout();
});

/* Klik backdrop untuk tutup */
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
