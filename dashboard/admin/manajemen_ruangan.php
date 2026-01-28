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

$ruangan = mysqli_query($conn,"SELECT * FROM ruangan ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
<?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?> | Manajemen Ruangan
</title>
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

/* RESET */
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

    display:flex;
    flex-direction:column;

    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{opacity:1}

/* ================= NAVBAR ================= */
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

/* NAVBAR SCROLL EFFECT */
.navbar{
    transition:transform .35s ease, padding .35s ease;
}

.navbar.hide{
    transform:translateY(-100%);
}

.navbar.shrink{
    padding:8px 26px;
}

.navbar.shrink .nav-logo{
    height:26px;
}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:#fff;
    font-weight:700;
    font-size:14px;
}

.nav-brand span{
    font-size:14px;
    letter-spacing:.3px;
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

/* ================= CONTENT ================= */
.wrapper{
    padding:40px 26px 120px;
    flex:1; /* ⬅️ INI PENTING */
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
    width:44px;
    height:3px;
    margin-top:8px;
    background:linear-gradient(to right,var(--primary),#b91c1c);
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
.btn:active{
    transform:scale(.96);
}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:34px;
    backdrop-filter:blur(16px);

    opacity:0;
    transform:translateY(20px);
    animation:cardIn .6s cubic-bezier(.4,0,.2,1) forwards;
    animation-delay:.15s;
}

@keyframes cardIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
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
/* ================= INPUT & TEXTAREA (SERIAS USERS.PHP) ================= */
/* ==================================================
   FORM INPUT – RMBS STYLE (FINAL)
================================================== */
input,
textarea,
select{
    width:100%;
    padding:14px 16px;
    border-radius:14px;

    background:rgba(255,255,255,.07);
    color:#e5e7eb;

    border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(10px);

    font-size:13px;

    transition:
        border .3s ease,
        background .3s ease,
        box-shadow .3s ease,
        transform .25s ease;
}

textarea{
    resize:none;
}

/* ================= PLACEHOLDER ================= */
input::placeholder,
textarea::placeholder{
    color:rgba(255,255,255,.55);
    font-weight:400;
}

/* ================= HOVER ================= */
input:hover,
textarea:hover,
select:hover{
    background:rgba(255,255,255,.1);
}

/* ================= FOCUS (MERAH RMBS) ================= */
input:focus,
textarea:focus,
select:focus{
    outline:none;

    border-color:#eb2525;
    background:rgba(255,255,255,.12);

    box-shadow:
        0 0 0 3px rgba(235,37,37,.35),
        0 12px 30px rgba(0,0,0,.35);

    transform:translateY(-1px);
}

/* ================= PLACEHOLDER SAAT FOCUS ================= */
input:focus::placeholder,
textarea:focus::placeholder{
    opacity:.35;
}

/* BUTTON */
.btn{
    padding:10px 18px;
    border-radius:12px;
    font-size:12px;
    font-weight:600;
    text-decoration:none;
    color:#fff;
    border:none;
    cursor:pointer;
    transition:.25s;
}
.btn-add{
    background:linear-gradient(to right,#16a34a,#15803d);
}
.btn-edit{
    background:linear-gradient(to right,#2563eb,#1e40af);
}
.btn-delete{
    background:linear-gradient(to right,#dc2626,#991b1b);
}
.btn:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 18px rgba(0,0,0,.35);
}

/* ================= TABLE ================= */
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

    /* 🔒 jaga layout tabel tetap rapi */
    table-layout:fixed;
}

th, td{
    padding:13px 16px;
    border-bottom:1px solid rgba(255,255,255,.06);
    vertical-align:middle;
    white-space:nowrap;
}

/* HEADER */
th{
    text-transform:uppercase;
    font-size:11px;
    letter-spacing:.5px;
    opacity:.7;
    background:rgba(255,255,255,.04);
    border-bottom:1px solid rgba(255,255,255,.12);
}

/* ROW */
tbody tr{
    transition:background .25s ease;
}
tbody tr:hover{
    background:rgba(255,255,255,.05);
}

/* ================= TEXT TRUNCATE (FASILITAS & DESKRIPSI) ================= */
.text-truncate{
    max-height:3em;        /* 2 baris (1.5 x 2) */
    line-height:1.5;
    overflow:hidden;
    text-overflow:ellipsis;

    white-space:normal;
    word-break:break-word;
}

/* ================= KUNCI KOLOM (MANAGEMENT RUANGAN) ================= */
th:nth-child(1),
td:nth-child(1){ width:18%; }           /* Nama */

th:nth-child(2),
td:nth-child(2){
    width:12%;
    text-align:center;                  /* Kapasitas */
}

th:nth-child(3),
td:nth-child(3){ width:22%; }           /* Fasilitas */

th:nth-child(4),
td:nth-child(4){ width:24%; }           /* Deskripsi */

th:nth-child(5),
td:nth-child(5){
    width:12%;
    text-align:center;                  /* Gambar */
}

th:nth-child(6),
td:nth-child(6){
    width:12%;
    text-align:center;                  /* Aksi */
    min-width:160px;
}

/* ================= ACTION ================= */
.action{
    display:flex;
    gap:8px;
    justify-content:center;
}

/* ================= EMPTY ================= */
.empty{
    text-align:center;
    padding:40px;
    opacity:.65;
    font-size:13px;
}


/* FOOTER */
.footer{
    position:fixed;
    left:0;
    right:0;
    bottom:0;

    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.12);

    padding:18px;
    font-size:12px;

    display:flex;
    justify-content:center;
    gap:10px;

    transform:translateY(100%);
    opacity:0;

    transition:
        transform .45s cubic-bezier(.4,0,.2,1),
        opacity .35s ease;

    z-index:900;
}

.footer.show{
    transform:translateY(0);
    opacity:1;
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
/* ===== PAGE ENTRY TRANSITION (KONSISTEN GLOBAL) ===== */
body{
    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{
    opacity:1;
}

/* ===== SMOOTH EXIT ===== */
.fade-out{
    opacity:0;
}

/* ================= LOGOUT MODAL (MODERN) ================= */
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

/* BOX */
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
    transition:.35s cubic-bezier(.4,0,.2,1);
}

.logout-modal.show .logout-box{
    transform:scale(1);
}

/* ICON */
.logout-icon{
    font-size:42px;
    margin-bottom:14px;
}

/* TEXT */
.logout-box h3{
    font-size:20px;
    margin-bottom:10px;
}

.logout-box p{
    font-size:14px;
    opacity:.9;
    line-height:1.6;
}

/* ACTION */
.logout-action{
    display:flex;
    gap:14px;
    margin-top:26px;
}

/* BUTTON BASE */
.logout-action button{
    flex:1;
    padding:12px;
    border-radius:14px;
    border:none;
    font-weight:600;
    cursor:pointer;
    transition:.25s ease;
}

/* CANCEL */
.btn-cancel{
    background:rgba(255,255,255,.15);
    color:#fff;
}
.btn-cancel:hover{
    background:rgba(255,255,255,.28);
}

/* YES */
.btn-yes{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
}
.btn-yes:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(235,37,37,.55);
}

/* ================= FILE INPUT ================= */
input[type="file"]{
    padding:10px;
    cursor:pointer;
}

input[type="file"]::file-selector-button{
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.25);
    color:#fff;
    padding:8px 14px;
    border-radius:10px;
    margin-right:12px;
    cursor:pointer;
    transition:.25s;
}

input[type="file"]::file-selector-button:hover{
    background:rgba(255,255,255,.25);
}
.form input:focus,
.form textarea:focus{
    box-shadow:
        0 0 0 3px rgba(37,99,235,.25),
        0 12px 30px rgba(0,0,0,.35);
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
    opacity:.85;
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

</style>
</head>

<body>

<!-- NAVBAR -->
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
            <h1>Manajemen Ruangan</h1>
            <p>Kelola data ruangan meeting perusahaan</p>
        </div>
        <a href="index.php" class="btn-back">← Dashboard</a>
    </div>

   <div class="card">

    <div class="form-title">Tambah Ruangan</div>

    <form class="form"
          action="ruangan_action.php"
          method="POST"
          enctype="multipart/form-data">

        <input type="hidden" name="aksi" value="tambah">

        <input type="text" name="kode" placeholder="Kode Ruangan (A, B, C)" required>
        <input type="text" name="nama" placeholder="Nama Ruangan" required>
        <input type="number" name="kapasitas" placeholder="Kapasitas" required>
        <input type="text" name="fasilitas" placeholder="Fasilitas" required>

        <!-- ✅ UPLOAD GAMBAR -->
        <input type="file" name="gambar" accept="image/*" required>

        <textarea name="deskripsi" placeholder="Deskripsi ruangan" rows="1"></textarea>

        <button class="btn btn-add" type="submit">Tambah Ruangan</button>
    </form>

    <div class="table-title">Daftar Ruangan</div>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Nama</th>
            <th>Kapasitas</th>
            <th>Fasilitas</th>
            <th>Deskripsi</th>
            <th>Gambar</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>

        <?php if(mysqli_num_rows($ruangan) > 0): ?>
            <?php while($r = mysqli_fetch_assoc($ruangan)): ?>
            <tr>
                <td><?= htmlspecialchars($r['nama']) ?></td>
                <td><?= htmlspecialchars($r['kapasitas']) ?></td>
               <td>
    <div class="text-truncate"
         title="<?= htmlspecialchars($r['fasilitas']) ?>">
        <?= htmlspecialchars($r['fasilitas']) ?>
    </div>
</td>

<td>
    <div class="text-truncate"
         title="<?= htmlspecialchars($r['deskripsi']) ?>">
        <?= htmlspecialchars($r['deskripsi']) ?>
    </div>
</td>

                <!-- 🖼️ PREVIEW GAMBAR -->
                <td>
                    <?php if(!empty($r['gambar'])): ?>
                        <img src="../../assets/img/rooms/<?= htmlspecialchars($r['gambar']) ?>"
     alt="Gambar <?= htmlspecialchars($r['nama']) ?>"
     style="
        width:70px;
        height:45px;
        object-fit:cover;
        border-radius:8px;
        border:1px solid rgba(255,255,255,.25);
     ">
                    <?php else: ?>
                        <span style="opacity:.5;font-size:12px">Tidak ada</span>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="action">
                        <a class="btn btn-edit"
                           href="edit_ruangan.php?aksi=edit&id=<?= $r['id'] ?>">
                           Edit
                        </a>
                        <a class="btn btn-delete"
                           href="ruangan_action.php?aksi=hapus&id=<?= $r['id'] ?>"
                           onclick="return confirm('Hapus ruangan ini?')">
                           Hapus
                        </a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="empty">
                    Belum ada data ruangan
                </td>
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
/* ================= NAVBAR + FOOTER AUTO BEHAVIOR ================= */
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');
const footer = document.querySelector('.footer');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

    /* ================= NAVBAR ================= */
    // hide navbar saat scroll ke bawah
    if (currentScroll > lastScrollTop && currentScroll > 100) {
        navbar.classList.add('hide');
    } else {
        navbar.classList.remove('hide');
    }

    // shrink navbar
    if (currentScroll > 80) {
        navbar.classList.add('shrink');
    } else {
        navbar.classList.remove('shrink');
    }

    /* ================= FOOTER (KEBALIKAN NAVBAR) ================= */
    // scroll ke bawah → footer muncul
    if (currentScroll > lastScrollTop && currentScroll > 300) {
    footer?.classList.add('show');
} else {
    footer?.classList.remove('show');
}

    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
});

/* ================= PAGE ENTRY TRANSITION ================= */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-loaded');
});

/* ================= LOGOUT MODAL ================= */
function openLogout(){
    document.getElementById('logoutModal')?.classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal')?.classList.remove('show');
}

function doLogout(){
    document.body.classList.add('fade-out');
    setTimeout(()=>{
        window.location.href = '../../auth/logout_process.php';
    }, 400);
}

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

</script>

</body>
</html>
