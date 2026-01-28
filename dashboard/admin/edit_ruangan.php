<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

if (!isset($_GET['id'])) {
    header("Location: manajemen_ruangan.php");
    exit;
}

$id = (int)$_GET['id'];

$db   = new Database();
$conn = $db->connect();

/* ===== SYNC AVATAR SESSION (FINAL, WAJIB) ===== */
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
    && file_exists(__DIR__ . '/../../assets/img/avatars/' . $avatarFile)
    ? '../../assets/img/avatars/' . $avatarFile
    : null;

/* Ambil data ruangan */
$stmt = mysqli_prepare($conn,"SELECT * FROM ruangan WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) === 0){
    header("Location: manajemen_ruangan.php");
    exit;
}

$r = mysqli_fetch_assoc($result);

/* =============================
   DATA GAMBAR DETAIL RUANGAN
============================= */
$qDetail = mysqli_query($conn,"
    SELECT *
    FROM ruangan_detail
    WHERE ruangan_id = $id
    ORDER BY created_at ASC
");

/* FOOTER ROLE */
$role = $_SESSION['user']['role'] ?? 'admin';
$footerRole = $role === 'superadmin'
    ? 'Super Administrator'
    : 'Administrator Panel';
$footerDesc = $role === 'superadmin'
    ? 'Full System Control'
    : 'System Management Access';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Edit Ruangan | <?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?>
</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
    --info:#2563eb;
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

    opacity:0;
    transition:opacity .5s ease;
}
body.page-loaded{opacity:1}

/* NAVBAR */
.navbar{
    position:fixed;
    inset:0 0 auto 0;
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

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    color:#fff;
    text-decoration:none;
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

.navbar.shrink .nav-logo{
    height:26px;
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

/* CONTENT */
.wrapper{
    max-width:1280px;
    margin:0 auto;
}
.wrapper{padding:40px 26px 120px}

/* HEADER */
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:34px;
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
.page-header h1{font-size:26px;font-weight:700}
.page-header p{font-size:13px;opacity:.75}

.btn-back{
    text-decoration:none;
    color:#fff;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:10px 22px;
    border-radius:14px;
    font-size:13px;
}

.divider{
    margin:48px 0;
    border:1px solid rgba(255,255,255,.12);
}

.section-header{
    margin-bottom:18px;
}

.section-header h3{
    font-size:17px;
    font-weight:600;
}

.section-header p{
    font-size:12px;
    opacity:.6;
}

.detail-form{
    display:flex;
    gap:14px;
    align-items:center;
    margin-bottom:26px;
    flex-wrap:wrap;
}

.file-input{
    padding:10px 16px;
    background:rgba(255,255,255,.08);
    border:1px dashed rgba(255,255,255,.25);
    border-radius:12px;
    cursor:pointer;
    font-size:12px;
}

.detail-form select{
    padding:10px 14px;
    border-radius:12px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.2);
    color:#e5e7eb;
    font-size:12px;
}

.btn.small{
    padding:10px 18px;
    font-size:12px;
}

/* ===== DETAIL RUANGAN GRID ===== */
.detail-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
    gap:18px;
    margin-top:10px;
}

.detail-item{
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.15);
    border-radius:16px;
    padding:10px;
    transition:.25s ease;
}

.detail-item:hover{
    transform:translateY(-4px);
    box-shadow:0 18px 40px rgba(0,0,0,.45);
}

.detail-item img{
    width:100%;
    height:110px;
    object-fit:cover;
    border-radius:12px;
}

/* META (posisi + hapus) */
.detail-meta{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:8px;
    font-size:11px;
    opacity:.75;
}

.detail-meta a{
    color:#fca5a5;
    text-decoration:none;
    font-size:14px;
}

.detail-meta a:hover{
    color:#fecaca;
}

/* EMPTY STATE */
.empty-state{
    padding:18px;
    border-radius:16px;
    background:rgba(255,255,255,.05);
    border:1px dashed rgba(255,255,255,.2);
    font-size:13px;
    opacity:.6;
}

/* CARD */
.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:36px; /* samakan */
    backdrop-filter:blur(16px);
    max-width:1100px;
    margin:0 auto;

    opacity:0;
    transform:translateY(20px);
    animation:cardIn .6s cubic-bezier(.4,0,.2,1) forwards;
    animation-delay:.15s;
}

/* FORM */
.form-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:18px;
}
.form{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:28px;
    align-items:start;
}

.form-left{
    display:grid;
    gap:14px;
}

.image-card{
    width:100%;
    padding:18px;
    background:rgba(255,255,255,.05);
    border:1px dashed rgba(255,255,255,.25);
    border-radius:18px;
    text-align:center;
}
.image-card{
    max-width:320px;
}
.image-card img{
    width:100%;
    height:160px;
    object-fit:cover;
    border-radius:12px;
    margin-bottom:14px;
}

.no-image{
    height:160px;
    display:flex;
    align-items:center;
    justify-content:center;
    opacity:.6;
    font-size:13px;
}

.upload-btn{
    display:inline-block;
    padding:10px 18px;
    background:rgba(37,99,235,.18);
    border:1px solid rgba(37,99,235,.35);
    border-radius:12px;
    cursor:pointer;
    transition:.25s;
}
.upload-btn:hover{
    background:rgba(37,99,235,.35);
}

.form-right{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:18px;
}

.btn-save{
    width:100%;
    max-width:320px;
    padding:14px;
    font-size:14px;
}
input,
textarea{
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

textarea{resize:none}

input::placeholder,
textarea::placeholder{
    color:rgba(255,255,255,.55);
}

input:hover,
textarea:hover{
    background:rgba(255,255,255,.1);
}

input:focus,
textarea:focus{
    outline:none;
    border-color:#eb2525;
    background:rgba(255,255,255,.12);

    box-shadow:
        0 0 0 3px rgba(235,37,37,.35),
        0 12px 30px rgba(0,0,0,.35);

    transform:translateY(-1px);
}

/* PREVIEW GAMBAR */
.preview{
    grid-column:span 2;
    display:flex;
    align-items:center;
    gap:16px;
}
.preview img{
    width:110px;
    height:70px;
    object-fit:cover;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.25);
    background:#020617;
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
.btn-save{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    transition:.25s;
}

.btn-save:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(235,37,37,.45);
}
.btn-save:active{
    transform:scale(.97);
}


/* FOOTER */
.footer{
    background:rgba(15,23,42,.75);
    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.12);
    padding:18px;
    font-size:12px;
    display:flex;
    justify-content:center;
    gap:10px;
    opacity:.8;
}

/* RESPONSIVE */
@media(max-width:1100px){
    .form{grid-template-columns:1fr 1fr}
    .preview{grid-column:span 2}
}
@media(max-width:600px){
    .page-header{flex-direction:column;align-items:flex-start;gap:14px}
}

@keyframes cardIn{
    to{opacity:1;transform:translateY(0)}
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
        linear-gradient(rgba(255,255,255,.18),rgba(255,255,255,.08));
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

        <a href="#" class="danger" onclick="openLogout(event)">
            🚪 Logout
        </a>
    </div>

</div>

</nav>

<div class="wrapper">

    <div class="page-header">
        <div>
            <h1>Edit Ruangan</h1>
            <p>Perbarui data ruangan meeting</p>
        </div>
        <a href="manajemen_ruangan.php" class="btn-back">← Kembali</a>
    </div>

    <div class="card">

        <div class="form-title">Form Edit Ruangan</div>

       <form class="form"
      action="ruangan_action.php"
      method="POST"
      enctype="multipart/form-data">

    <input type="hidden" name="aksi" value="update">
    <input type="hidden" name="id" value="<?= $r['id']; ?>">

    <!-- ===== FORM LEFT ===== -->
    <div class="form-left">
        <input type="text" name="nama"
               value="<?= htmlspecialchars($r['nama']); ?>"
               placeholder="Nama Ruangan"
               required>

        <input type="number" name="kapasitas"
               value="<?= htmlspecialchars($r['kapasitas']); ?>"
               placeholder="Kapasitas"
               required>

        <input type="text" name="fasilitas"
               value="<?= htmlspecialchars($r['fasilitas']); ?>"
               placeholder="Fasilitas"
               required>

        <textarea name="deskripsi"
                  rows="2"
                  placeholder="Deskripsi ruangan"><?= htmlspecialchars($r['deskripsi']); ?></textarea>
    </div>

    <!-- ===== FORM RIGHT ===== -->
    <div class="form-right">
        <div class="image-card">

            <?php if (!empty($r['gambar'])): ?>
                <img src="../../assets/img/rooms/<?= htmlspecialchars($r['gambar']); ?>"
                     alt="Preview Ruangan">
            <?php else: ?>
                <div class="no-image">Tidak ada gambar</div>
            <?php endif; ?>

            <label class="upload-btn">
                Ganti Gambar
                <input type="file"
       name="gambar"
       accept="image/png,image/jpeg,image/webp"
       hidden>
            </label>

        </div>
        <!-- BUTTON -->
    <button class="btn btn-save" type="submit">
    💾 Simpan Perubahan
</button>
    </div>
</form>

<hr class="divider">

<div class="section-header">
    <h3>Detail Ruangan</h3>
    <p>Kelola galeri foto ruangan</p>
</div>

<!-- ===== FORM TAMBAH GAMBAR ===== -->
<form action="ruangan_detail_action.php"
      method="POST"
      enctype="multipart/form-data"
      class="detail-form">

    <input type="hidden" name="ruangan_id" value="<?= $r['id']; ?>">

    <label class="file-input">
        📷 Pilih Gambar
        <input type="file" name="gambar" required hidden>
    </label>

    <select name="posisi">
        <option value="kiri">Sisi Kiri</option>
        <option value="kanan">Sisi Kanan</option>
        <option value="depan">Depan</option>
        <option value="belakang">Belakang</option>
        <option value="lainnya" selected>Lainnya</option>
    </select>

    <button type="submit" class="btn btn-save small">
        ➕ Tambah
    </button>
</form>

<!-- ===== LIST GAMBAR ===== -->
<?php if(mysqli_num_rows($qDetail) > 0): ?>
<div class="detail-grid">

<?php while($img = mysqli_fetch_assoc($qDetail)): ?>
    <div class="detail-item">

        <img src="../../assets/img/rooms/<?= htmlspecialchars($img['gambar']); ?>"
             alt="Detail Ruangan">

        <div class="detail-meta">
            <span>
                <?= htmlspecialchars($img['posisi'] ?? 'lainnya'); ?>
            </span>

            <a href="ruangan_detail_action.php?hapus=<?= $img['id']; ?>"
               onclick="return confirm('Hapus gambar ini?')"
               title="Hapus gambar">
                🗑
            </a>
        </div>

    </div>
<?php endwhile; ?>

</div>
<?php else: ?>
<div class="empty-state">
    📷 Belum ada gambar detail ruangan
</div>
<?php endif; ?>
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
            <strong><?= strtoupper($_SESSION['user']['role']); ?></strong>.<br>
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
document.addEventListener('DOMContentLoaded',()=>{
    document.body.classList.add('page-loaded');
});

/* NAVBAR AUTO HIDE + SHRINK */
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll',()=>{
    const cur = window.pageYOffset;
    navbar.classList.toggle('hide', cur > lastScrollTop && cur > 100);
    navbar.classList.toggle('shrink', cur > 80);
    lastScrollTop = cur <= 0 ? 0 : cur;
});

/* LOGOUT MODAL */
function openLogout(e){
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

/* preview gambar saat upload */
document.querySelector('input[type=file]')?.addEventListener('change', e=>{
    const img = document.querySelector('.image-card img');
    if(img && e.target.files[0]){
        img.src = URL.createObjectURL(e.target.files[0]);
    }
});
</script>

</body>
</html>
