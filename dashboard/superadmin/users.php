<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/audit.php';
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

$action = $_GET['action'] ?? '';
$error  = $_GET['error'] ?? '';

/* ===== TAMBAH USER ===== */
if ($action === 'tambah' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama  = trim($_POST['nama']);
    $email = strtolower(trim($_POST['email']));
    $role  = $_POST['role'];
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $cek = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT id FROM users WHERE email='$email'")
    );
    if ($cek) {
        header("Location: users.php?error=email");
        exit;
    }

    mysqli_query($conn,"
    INSERT INTO users (name,email,password,role)
    VALUES ('$nama','$email','$pass','$role')
");

$newUserId = mysqli_insert_id($conn);

/* AUDIT LOG */
audit_log(
    'CREATE_USER',
    'Create user: '.$email.' (role: '.$role.')',
    $_SESSION['user']['id']
);

header("Location: users.php");
exit;
}

/* ===== HAPUS USER ===== */
if ($action === 'hapus') {

    $id = (int)$_GET['id'];

    $cek = mysqli_fetch_assoc(
        mysqli_query($conn,"SELECT role FROM users WHERE id=$id")
    );

    if ($cek && $cek['role'] === 'superadmin') {
        header("Location: users.php?error=protected");
        exit;
    }

    /* Ambil data user sebelum dihapus */
$userDel = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT email, role FROM users WHERE id=$id")
);

mysqli_query($conn,"DELETE FROM users WHERE id=$id");

/* AUDIT LOG */
audit_log(
    'DELETE_USER',
    'Delete user: '.$userDel['email'].' (role: '.$userDel['role'].')',
    $_SESSION['user']['id']
);

header("Location: users.php");
exit;
}

/* ===== DATA USER ===== */
$users = mysqli_query($conn,"
    SELECT id,name,email,role,created_at
    FROM users
    ORDER BY 
        CASE role
            WHEN 'superadmin' THEN 1
            WHEN 'admin' THEN 2
            ELSE 3
        END, name
");

/* ===== RECENT BOOKING (READ ONLY) ===== */
$recentBooking = mysqli_query($conn,"
    SELECT 
        b.tanggal,
        b.jam_mulai,
        b.jam_selesai,
        b.status,
        r.nama AS ruangan,
        u.name AS pemesan
    FROM booking b
    JOIN ruangan r ON b.ruangan_id = r.id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 5
");

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen User | RMBS</title>
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
/* ===== STATUS BADGE (BOOKING / USER) ===== */
/* STATUS BADGE */
.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
    display:inline-block;
}

.badge-pending{
    background:rgba(245,158,11,.2);
    color:#f59e0b;
}

.badge-approved{
    background:rgba(22,163,74,.2);
    color:#16a34a;
}

.badge-rejected{
    background:rgba(220,38,38,.2);
    color:#dc2626;
}
.badge-cancelled{
    background:rgba(220,38,38,.25);
    color:#fecaca;
}

.badge{
    transition:.25s;
}
.badge:hover{
    filter:brightness(1.15);
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

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:34px;
    backdrop-filter:blur(16px);
}

/* FORM */
.form-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:18px;
}
.form{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:36px;
}
/* ===== FORM INPUT DARK STYLE ===== */
input,
select{
    width:100%;
    padding:13px 15px;
    border-radius:12px;

    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.14);

    color:#e5e7eb;
    font-size:13px;
    transition:.25s ease;
}

/* Placeholder */
input::placeholder{
    color:rgba(255,255,255,.45);
}

/* Focus */
input:focus,
select:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(235,37,37,.25);
    background:rgba(15,23,42,.9);
}

/* Select option */
select option{
    background:#020617;
    color:#e5e7eb;
}

/* BUTTON */
.btn{
    padding:10px 18px;
    border-radius:12px;
    font-size:12px;
    font-weight:600;
    border:none;
    cursor:pointer;
    color:#fff;
}
/* ===== ACTION BUTTONS ===== */
.btn-add{
    background:linear-gradient(to right,#16a34a,#15803d);
    box-shadow:0 10px 28px rgba(22,163,74,.35);
    transition:.25s ease;
}
.btn-add:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 38px rgba(22,163,74,.55);
}
.btn-add:active{transform:scale(.97)}

/* EDIT */
.btn-edit{
    background:linear-gradient(to right,#2563eb,#1e40af);
    box-shadow:0 10px 28px rgba(37,99,235,.35);
    transition:.25s ease;
}
.btn-edit:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 38px rgba(37,99,235,.55);
}
.btn-edit:active{transform:scale(.97)}

/* DELETE */
.btn-delete{
    background:linear-gradient(to right,#dc2626,#991b1b);
    box-shadow:0 10px 28px rgba(220,38,38,.35);
    transition:.25s ease;
}
.btn-delete:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 38px rgba(220,38,38,.55);
}
.btn-delete:active{transform:scale(.97)}

/* ================= TABLE (MATCH REPORTS STYLE) ================= */
.table-title{
    font-size:17px;
    font-weight:600;
    margin-bottom:14px;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;

    /* 🔒 kunci struktur seperti reports.php */
    table-layout:fixed;
}

th,td{
    padding:13px 16px;
    border-bottom:1px solid rgba(255,255,255,.06);
    vertical-align:middle;
}

th{
    font-size:11px;
    text-transform:uppercase;
    opacity:.7;
    letter-spacing:.5px;
}

/* ===== KUNCI KOLOM USERS (5 KOLOM) ===== */
th:nth-child(1),
td:nth-child(1){
    width:22%;          /* Nama */
}

th:nth-child(2),
td:nth-child(2){
    width:28%;          /* Email */
}

th:nth-child(3),
td:nth-child(3){
    width:14%;          /* Role */
    text-align:center;
}

th:nth-child(4),
td:nth-child(4){
    width:18%;          /* Dibuat */
    text-align:center;
}

th:nth-child(5),
td:nth-child(5){
    width:18%;          /* Aksi */
    text-align:center;
}

/* hover konsisten */
tbody tr:hover{
    background:rgba(255,255,255,.05);
}

/* action */
.action{
    display:flex;
    gap:8px;
    justify-content:center;
    flex-wrap:wrap;
}

/* empty */
.empty{
    text-align:center;
    padding:46px;
    opacity:.6;
    font-size:13px;
}

/* FOOTER */
.footer{
    margin-top:80px;
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.12);
    padding:18px;
    text-align:center;
    font-size:12px;
    opacity:.8;
}

/* RESPONSIVE */
@media(max-width:1100px){
    .form{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }
}
/* ===== PAGE ENTRY TRANSITION ===== */
body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
}

/* Card masuk halus */
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
        linear-gradient(rgba(255,255,255,.18), rgba(255,255,255,.08));
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
.btn-yes{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
}

/* ===== PAGE EXIT ===== */
.fade-out{
    opacity:0;
    transition:opacity .4s ease;
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
        <img src="<?= $avatarPath ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
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
            <h1>Manajemen User</h1>
            <p>Kelola akun admin dan karyawan sistem</p>
        </div>
        <a href="index.php" class="btn-back">← Dashboard</a>
    </div>

    <div class="card">

        <div class="form-title">Tambah User</div>

        <form class="form" method="POST" action="?action=tambah">
            <input type="text" name="nama" placeholder="Nama" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="">Pilih Role</option>
                <option value="admin">Admin</option>
                <option value="employee">Employee</option>
            </select>
            <button class="btn btn-add" type="submit">Tambah User</button>
        </form>

        <div class="table-title">Daftar User</div>

        <table>
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if(mysqli_num_rows($users) > 0): ?>
                <?php while($u = mysqli_fetch_assoc($users)): ?>
                <tr>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= strtoupper($u['role']) ?></td>
                    <td><?= date('d M Y',strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="action">
                            <?php if($u['role'] !== 'superadmin'): ?>

                                <a class="btn btn-edit"
                                   href="users_edit.php?id=<?= $u['id'] ?>">
                                   Edit
                                </a>

                                <a class="btn btn-delete"
                                   href="?action=hapus&id=<?= $u['id'] ?>"
                                   onclick="return confirm('Hapus user ini?')">
                                   Hapus
                                </a>

                            <?php else: ?>
                                <small>Protected</small>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty">Belum ada data user</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div>
    <div class="card" style="margin-top:40px">

    <div class="table-title">Recent Booking (Terbaru)</div>

    <table>
        <thead>
            <tr>
                <th>Pemesan</th>
                <th>Ruangan</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>

        <?php if(mysqli_num_rows($recentBooking) > 0): ?>
            <?php while($b = mysqli_fetch_assoc($recentBooking)): ?>
            <tr>
                <td><?= htmlspecialchars($b['pemesan']) ?></td>
                <td><?= htmlspecialchars($b['ruangan']) ?></td>
                <td><?= date('d M Y', strtotime($b['tanggal'])) ?></td>
                <td><?= substr($b['jam_mulai'],0,5) ?> - <?= substr($b['jam_selesai'],0,5) ?></td>
                <td>
    <?php
        $statusClass = match($b['status']) {
    'pending'   => 'badge-pending',
    'approved'  => 'badge-approved',
    'rejected'  => 'badge-rejected',
    'cancelled' => 'badge-cancelled',
    default     => 'badge-pending'
};
    ?>
    <span class="badge <?= $statusClass ?>">
        <?= strtoupper($b['status']) ?>
    </span>
</td>

            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="empty">Belum ada booking</td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>

</div>


</div>

<footer class="footer">
    <span>
        © <?= date('Y') ?>
        <strong><?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?></strong>
    </span>
    <span> • </span>
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

    // hide saat scroll ke bawah
    if (currentScroll > lastScrollTop && currentScroll > 100) {
        navbar.classList.add('hide');
    } else {
        navbar.classList.remove('hide');
    }

    // shrink logo
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
    // animasi keluar halus
    document.body.classList.remove('page-loaded');
    document.body.classList.add('fade-out');

    setTimeout(() => {
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

/* ================= UX BONUS ================= */
// tekan ESC untuk tutup modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLogout();
});

// klik area luar modal untuk tutup
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
mysqli_close($conn);
?>


</body>
</html>
