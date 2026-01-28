<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['superadmin']);

$role = $_SESSION['user']['role'] ?? 'superadmin';

if ($role === 'superadmin') {
    $footerRole = 'Super Administrator';
    $footerDesc = 'Full System Control';
} else {
    $footerRole = 'Administrator Panel';
    $footerDesc = 'System Management Access';
}

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

/* ================= FILTER ================= */
$start  = $_GET['start']  ?? '';
$end    = $_GET['end']    ?? '';
$user   = $_GET['user']   ?? '';
$action = $_GET['action'] ?? '';

$sql = "
    SELECT a.*, u.name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";

$params = [];
$types  = "";

/* FILTER TANGGAL */
if ($start && $end) {
    $sql .= " AND DATE(a.created_at) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= "ss";
}

/* FILTER USER */
if ($user) {
    $sql .= " AND u.name LIKE ?";
    $params[] = "%$user%";
    $types .= "s";
}

/* FILTER AKSI */
if ($action) {
    $sql .= " AND a.action LIKE ?";
    $params[] = "%$action%";
    $types .= "s";
}

$sql .= " ORDER BY a.created_at DESC LIMIT 200";

$stmt = mysqli_prepare($conn, $sql);

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Audit Log | RMBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

:root{
    --primary:#eb2525;
    --success:#16a34a;
    --danger:#dc2626;
    --info:#2563eb;
    --bg:#0b1220;
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
    transition:.35s;
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}
.navbar.shrink .nav-logo{height:24px}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:#fff;
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

/* ===== WRAPPER ===== */
.wrapper{
    padding:40px 26px 120px;
    flex:1;
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
.btn-back:hover{background:rgba(255,255,255,.16)}

/* ===== CARD ===== */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:34px;
    backdrop-filter:blur(16px);
    box-shadow:0 40px 90px rgba(0,0,0,.55);
}

/* ===== FILTER BAR ===== */
.filter-bar{
    background:rgba(255,255,255,.04);
    border:1px solid var(--border);
    border-radius:20px;
    padding:18px 20px;
    margin-bottom:22px;
}

.filter-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:14px;
}

/* INPUT */
.filter-input{
    background:rgba(15,23,42,.6);
    border:1px solid rgba(255,255,255,.14);
    color:#e5e7eb;
    padding:12px 14px;
    border-radius:14px;
    font-size:13px;
    transition:.25s ease;
}

.filter-input::placeholder{
    color:rgba(255,255,255,.45);
}

.filter-input:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.85);
}

/* BUTTON GROUP */
.filter-actions{
    display:flex;
    gap:10px;
    align-items:center;
}

.btn-filter{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:11px 18px;
    border-radius:14px;
    border:none;
    font-size:12px;
    font-weight:600;
    color:#fff;
    cursor:pointer;
    transition:.25s;
}

.btn-filter:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(235,37,37,.45);
}

.btn-reset{
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:11px 18px;
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

/* FILTER INFO */
.filter-info{
    margin-bottom:18px;
    font-size:13px;
    opacity:.75;
    display:flex;
    align-items:center;
    gap:6px;
}

/* ===== TABLE ===== */
.table-title{
    font-size:17px;
    font-weight:600;
    margin-bottom:14px;
}
.table-wrap{
    overflow-x:auto;
}
table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th,td{
    padding:13px 16px;
    text-align:left;
    white-space:nowrap;
}

tbody tr{
    border-bottom:1px solid rgba(255,255,255,.06);
}
tbody tr:hover{
    background:rgba(255,255,255,.06);
}

.empty{
    text-align:center;
    padding:40px;
    opacity:.65;
}

/* BADGE */
.badge{
    padding:5px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
    letter-spacing:.3px;
}
.badge-default{
    background:rgba(255,255,255,.12);
    color:#e5e7eb;
}
.badge-success{
    background:rgba(22,163,74,.25);
    color:#16a34a;
}
.badge-danger{
    background:rgba(220,38,38,.25);
    color:#dc2626;
}
.badge-info{
    background:rgba(37,99,235,.25);
    color:#2563eb;
}

/* ===== FOOTER ===== */
.footer{
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.12);
    padding:18px;
    text-align:center;
    font-size:12px;
    opacity:.8;
}


/* RESPONSIVE */
@media(max-width:600px){
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }
}
/* ===== PAGE TRANSITION (ENTRY EFFECT) ===== */
body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
}

.card{
    opacity:0;
    transform:translateY(18px);
    animation:cardIn .6s cubic-bezier(.4,0,.2,1) forwards;
    animation-delay:.15s;
}

@keyframes cardIn{
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

</style>
</head>

<body>

<!-- NAVBAR -->
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
        <div>
            <h1>Audit Log Sistem</h1>
            <p>Catatan seluruh aktivitas penting sistem</p>
        </div>
        <a href="index.php" class="btn-back">← Dashboard</a>
    </div>

    <div class="card">
<form method="GET" class="filter-bar">
    <div class="filter-grid">

        <input type="date"
               name="start"
               class="filter-input"
               title="Tanggal Mulai"
               value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">

        <input type="date"
               name="end"
               class="filter-input"
               title="Tanggal Akhir"
               value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">

        <input type="text"
               name="user"
               class="filter-input"
               placeholder="Nama User"
               value="<?= htmlspecialchars($_GET['user'] ?? '') ?>">

        <input type="text"
               name="action"
               class="filter-input"
               placeholder="Action"
               value="<?= htmlspecialchars($_GET['action'] ?? '') ?>">

        <div class="filter-actions">
            <button type="submit" class="btn-filter">Filter</button>
            <a href="audit_logs.php" class="btn-reset">Reset</a>
        </div>

    </div>
</form>

<?php if($start || $end || $user || $action): ?>
<div class="filter-info">
    🔍 Filter aktif
</div>
<?php endif; ?>

        <div class="table-title">Riwayat Aktivitas</div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Aksi</th>
                    <th>Deskripsi</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($logs) > 0): ?>
                    <?php while($l = mysqli_fetch_assoc($logs)): ?>
                    <tr>
                        <td><?= $l['created_at'] ?></td>
                        <td><?= htmlspecialchars($l['name'] ?? 'SYSTEM') ?></td>
                        <td>
                            <?php
                                $badge = 'badge-default';

if (str_contains($l['action'], 'LOGIN_SUCCESS')) {
    $badge = 'badge-success';
}
elseif (str_contains($l['action'], 'LOGIN_FAILED')) {
    $badge = 'badge-danger';
}
elseif (str_contains($l['action'], 'DELETE')) {
    $badge = 'badge-danger';
}
elseif (str_contains($l['action'], 'CREATE')) {
    $badge = 'badge-info';
}
                            ?>
                            <span class="badge <?= $badge ?>">
                                <?= $l['action'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($l['description']) ?></td>
                        <td><?= $l['ip_address'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty">Belum ada data audit log</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
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
/* ================= PAGE ENTRY ================= */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-loaded');
});

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

    if (currentScroll > lastScrollTop && currentScroll > 100) {
        navbar.classList.add('hide');
    } else {
        navbar.classList.remove('hide');
    }

    if (currentScroll > 80) {
        navbar.classList.add('shrink');
    } else {
        navbar.classList.remove('shrink');
    }

    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
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
    document.body.classList.remove('page-loaded');
    document.body.classList.add('fade-out');

    setTimeout(() => {
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

/* ESC close */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLogout();
});

/* Click outside modal */
document.getElementById('logoutModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'logoutModal') closeLogout();
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
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

</body>
</html>
