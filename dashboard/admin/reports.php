<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

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

/* ===== AVATAR NAVBAR ===== */
$avatarFile = $_SESSION['user']['avatar'] ?? null;
$avatarPath = $avatarFile
    && file_exists(__DIR__.'/../../assets/img/avatars/'.$avatarFile)
    ? '../../assets/img/avatars/'.$avatarFile
    : null;

/* ===== DATA USER UNTUK FILTER ===== */
$users = mysqli_query($conn, "SELECT id, name FROM users ORDER BY name");
$user_id = $_GET['user'] ?? '';

$role = $_SESSION['user']['role'] ?? 'admin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

/* FILTER */
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-d');

/* VALIDASI RANGE TANGGAL */
if ($start > $end) {
    // tukar otomatis supaya tidak error
    [$start, $end] = [$end, $start];
}

/* ================= DATA REPORT BOOKING (FINAL & AMAN) ================= */
$sql = "
    SELECT 
        b.id,
        b.tanggal,
        b.jam_mulai,
        b.jam_selesai,
        b.status,
        b.biaya,
        b.cancel_reason,
        u.name AS user_name,
        r.nama AS ruangan
    FROM booking b
    JOIN users u ON b.user_id = u.id
    JOIN ruangan r ON b.ruangan_id = r.id
    WHERE b.tanggal BETWEEN ? AND ?
";

$params = [$start, $end];
$types  = "ss";

/* Filter user (jika dipilih) */
if (!empty($user_id)) {
    $sql      .= " AND b.user_id = ? ";
    $params[] = (int) $user_id;
    $types   .= "i";
}

$sql .= " ORDER BY b.tanggal DESC ";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$report = mysqli_stmt_get_result($stmt);

/* ===============================
   DETEKSI FILTER AKTIF
================================ */
$isFiltered = false;

// jika user dipilih ATAU tanggal diubah dari default
if ($user_id !== '' || isset($_GET['start']) || isset($_GET['end'])) {
    $isFiltered = true;
}

/* SUMMARY */
$summary = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT 
        COUNT(*) AS total,
        SUM(status='pending')   AS pending,
        SUM(status='approved')  AS approved,
        SUM(status='rejected')  AS rejected,
        SUM(status='cancelled') AS cancelled
    FROM booking
    WHERE tanggal BETWEEN '$start' AND '$end'
"));

$income = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT 
        SUM(biaya) AS total_income
    FROM booking
    WHERE status = 'approved'
    AND tanggal BETWEEN '$start' AND '$end'
    AND (user_id = '$user_id' OR '$user_id' = '')
"));

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Reports | <?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?>
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
    transition:
        transform .35s ease,
        padding .35s ease,
        background .35s ease;
}

.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}
.navbar.shrink .nav-logo{
    height:26px;
}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    color:#fff;
    text-decoration:none;
    font-weight:700;
    font-size:14px;
}
.nav-logo{
    height:34px;
    transition:.35s ease;
}
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
    gap:8px;
    flex-wrap:wrap;
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
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
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


/* ================= TABLE ================= */
table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;

    /* 🔒 KUNCI STRUKTUR */
    table-layout:fixed;
}

th,td{
    padding:13px 16px;
    border-bottom:1px solid rgba(255,255,255,.06);
    vertical-align:middle;
}

/* 🔧 KUNCI KOLOM (6 KOLOM FIX BIAYA) */
th:nth-child(1),
td:nth-child(1){
    width:14%;
    text-align:center;
}

th:nth-child(2),
td:nth-child(2){
    width:18%;
    text-align:center;
}

th:nth-child(3),
td:nth-child(3){
    width:22%;
    text-align:center;
}

th:nth-child(4),
td:nth-child(4){
    width:18%;
    text-align:center;
}

th:nth-child(5),
td:nth-child(5){
    width:14%;
    text-align:center;
}

th:nth-child(6),
td:nth-child(6){
    width:14%;
    text-align:center;
}

/* FIX VISUAL ALIGNMENT USER & RUANGAN */
th:nth-child(2),
td:nth-child(2),
th:nth-child(3),
td:nth-child(3){
    text-align:center;
}

tbody tr:hover{
    background:rgba(255,255,255,.05);
}

th{
    font-size:11px;
    text-transform:uppercase;
    opacity:.7;
    letter-spacing:.5px;
}

/* STATUS BADGE */
.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
}

.pending{background:rgba(245,158,11,.2);color:#f59e0b}
.approved{background:rgba(22,163,74,.2);color:#16a34a}
.rejected{background:rgba(220,38,38,.2);color:#dc2626}
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

.income{
    border:1px solid rgba(22,163,74,.45);
    background:linear-gradient(
        to right,
        rgba(22,163,74,.22),
        rgba(255,255,255,.04)
    );
}
.income h3{
    color:#22c55e;
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
    overflow:hidden;
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

<nav class="navbar">
    <a href="index.php" class="nav-brand">
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

        <!-- FILTER USER -->
        <select name="user"
            style="
                background:rgba(15,23,42,.65);
                border:1px solid rgba(255,255,255,.14);
                color:#e5e7eb;
                padding:10px 12px;
                border-radius:12px;
                font-size:13px;
            ">
            <option value="">Semua User</option>
            <?php while($u = mysqli_fetch_assoc($users)): ?>
                <option value="<?= $u['id'] ?>"
                    <?= ($user_id == $u['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- FILTER TANGGAL -->
        <input type="date" name="start" value="<?= $start ?>">
        <input type="date" name="end" value="<?= $end ?>">

        <!-- FILTER -->
        <button class="btn-filter">Filter</button>

<?php if ($isFiltered): ?>
    <a href="reports.php"
       class="btn-filter"
       style="
           background:rgba(255,255,255,.12);
           color:#fff;
       ">
        Reset
    </a>
<?php endif; ?>

        <!-- EXPORT EXCEL -->
        <a href="excel.php?<?= http_build_query($_GET) ?>"
           class="btn-filter"
           style="background:#16a34a">
            Excel
        </a>

        <!-- EXPORT PDF -->
        <a href="pdf.php?<?= http_build_query($_GET) ?>"
           class="btn-filter"
           style="background:#dc2626">
            PDF
        </a>

    </form>
</div>

    <div class="card">

        <div class="summary">
    <div class="box">
        <h3><?= $summary['total'] ?></h3>
        <span>Total Booking</span>
    </div>

    <div class="box">
        <h3><?= $summary['pending'] ?></h3>
        <span>Pending</span>
    </div>

    <div class="box">
        <h3><?= $summary['approved'] ?></h3>
        <span>Approved</span>
    </div>

    <div class="box">
        <h3><?= $summary['rejected'] ?></h3>
        <span>Rejected</span>
    </div>

    <div class="box">
    <h3><?= $summary['cancelled'] ?></h3>
    <span>Cancelled</span>
</div>


    <!-- ✅ TOTAL PENDAPATAN -->
    <div class="box income">
        <h3>
            Rp <?= number_format((int)($income['total_income'] ?? 0), 0, ',', '.') ?>
        </h3>
        <span>Total Pendapatan</span>
    </div>
</div>

        <table>
            <tr>
                <th>Tanggal</th>
                <th>User</th>
                <th>Ruangan</th>
                <th>Jam</th>
                <th>Status</th>
                <th>Biaya</th>
            </tr>
            <?php while($r = mysqli_fetch_assoc($report)): ?>
            <tr>
                <td><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                <td><?= htmlspecialchars($r['user_name']) ?></td>
                <td><?= htmlspecialchars($r['ruangan']) ?></td>
                <td><?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?></td>
                <td>
    <span class="badge <?= $r['status'] ?>">
        <?= strtoupper($r['status']) ?>
    </span>

    <?php if ($r['status'] === 'cancelled'): ?>
        <div style="
            margin-top:6px;
            font-size:11px;
            opacity:.75;
            line-height:1.4;
        ">
            <?= htmlspecialchars($r['cancel_reason'] ?? '-') ?>
        </div>
    <?php endif; ?>
</td>

<td>
<?php if (in_array($r['status'], ['approved','cancelled']) && !is_null($r['biaya'])): ?>
    <span class="badge approved">
        Rp <?= number_format((int)$r['biaya'], 0, ',', '.') ?>
    </span>

    <?php if ($r['status'] === 'cancelled'): ?>
        <div style="
            margin-top:4px;
            font-size:11px;
            opacity:.6;
        ">
            (biaya sebelum dibatalkan)
        </div>
    <?php endif; ?>

<?php else: ?>
    <span style="opacity:.6">-</span>
<?php endif; ?>
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
            Anda login sebagai <strong>ADMIN</strong>.<br>
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
