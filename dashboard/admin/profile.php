<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/maintenance.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

$role = $_SESSION['user']['role'] ?? 'admin';

$footerRole = $role === 'superadmin'
    ? 'Super Administrator'
    : 'Administrator Panel';

$footerDesc = $role === 'superadmin'
    ? 'Full System Control'
    : 'System Management Access';

/* ================= DATABASE ================= */
$db   = new Database();
$conn = $db->connect();

$userId = (int) $_SESSION['user']['id'];

/* ===== SYNC AVATAR SESSION (ANTI BALIK INISIAL) ===== */
if (empty($_SESSION['user']['avatar'])) {
    $q = mysqli_query($conn,"SELECT avatar FROM users WHERE id=$userId LIMIT 1");
    if ($r = mysqli_fetch_assoc($q)) {
        $_SESSION['user']['avatar'] = $r['avatar'] ?? null;
    }
}

/* ===== AVATAR ===== */
$avatarFile = $_SESSION['user']['avatar'] ?? null;
$avatarPath = ($avatarFile && file_exists(__DIR__.'/../../assets/img/avatars/'.$avatarFile))
    ? '../../assets/img/avatars/'.$avatarFile
    : null;

$initial = strtoupper(substr($_SESSION['user']['name'],0,1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Profil | <?= htmlspecialchars(getSetting('system_name','RMBS')) ?>
</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ================= ROOT ================= */
:root{
    --primary:#eb2525;
    --primary-dark:#b91c1c;
    --glass:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.18);
    --shadow:rgba(0,0,0,.55);
}

/* ================= RESET ================= */
*{
    margin:0;padding:0;box-sizing:border-box;
    font-family:'Montserrat',sans-serif
}

/* ================= BASE ================= */
body{
    min-height:100vh;
    padding-top:76px;
    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 40%),
        linear-gradient(to bottom right,#0b1220,#020617);
    color:#e5e7eb;
    opacity:0;
    transition:opacity .45s ease;
}
body.loaded{opacity:1}
.fade-out{opacity:0}

/* ================= NAVBAR ================= */
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
    box-shadow:0 10px 30px rgba(0,0,0,.55);
    transition:transform .35s ease,padding .35s ease;
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
    height:34px;
    transition:.35s ease;
}

/* ikut shrink saat scroll */
.navbar.shrink .nav-logo{
    height:26px;
}

.profile-trigger{
    display:flex;
    align-items:center;
    gap:14px;
    cursor:pointer;
    position:relative;
}

.avatar-mini{
    width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,#eb2525,#b91c1c);
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:700;
}
.avatar-mini img{
    width:100%;height:100%;
    object-fit:cover;border-radius:50%
}

.user-name{font-size:13px;font-weight:500}
.role-badge{
    background:rgba(235,37,37,.18);
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
}
.caret{font-size:12px;opacity:.85}

/* dropdown */
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
}
.profile-dropdown.show{display:flex}
.profile-dropdown a{
    padding:12px 16px;
    font-size:13px;
    color:#e5e7eb;
    text-decoration:none;
}
.profile-dropdown a:hover{background:rgba(255,255,255,.08)}
.profile-dropdown .danger{color:#fca5a5}

/* ================= CONTENT ================= */
.wrapper{
    max-width:900px;
    margin:40px auto 120px;
    padding:0 22px;
}

.back-btn{
    display:inline-block;
    margin-bottom:20px;
    padding:10px 20px;
    border-radius:14px;
    text-decoration:none;
    color:#fff;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    transition:.25s;
}
.back-btn:hover{background:rgba(255,255,255,.16)}

/* ================= CARD ================= */
.card{
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid var(--border);
    border-radius:26px;
    padding:38px;
    box-shadow:0 40px 90px var(--shadow);
    animation:cardUp .6s ease forwards;
}

/* ================= AVATAR ================= */
.avatar{
    width:120px;height:120px;border-radius:50%;
    margin:26px auto 30px;
    background:linear-gradient(135deg,#eb2525,#b91c1c);
    display:flex;align-items:center;justify-content:center;
    font-size:42px;font-weight:700;color:#fff;
    border:4px solid rgba(255,255,255,.18);
    box-shadow:
        0 0 0 6px rgba(235,37,37,.15),
        0 28px 60px rgba(0,0,0,.6);
    position:relative;overflow:hidden;
    transition:.35s cubic-bezier(.4,0,.2,1);
}
.avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.avatar::after{
    content:'';
    position:absolute;inset:0;
    background:linear-gradient(120deg,transparent,rgba(255,255,255,.25),transparent);
    transform:translateX(-120%);
    transition:.6s;
}
.avatar:hover{
    transform:translateY(-4px) scale(1.03);
    box-shadow:
        0 0 0 6px rgba(235,37,37,.25),
        0 38px 80px rgba(0,0,0,.7);
}
.avatar:hover::after{transform:translateX(120%)}

/* ================= FORM ================= */
label{font-size:12px;opacity:.8}
input{
    width:100%;
    padding:12px 14px;
    border-radius:14px;
    background:rgba(255,255,255,.12);
    border:none;
    color:#fff;
    margin:8px 0 18px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;
    font-weight:600;
    cursor:pointer;
    transition:.25s;
}
button:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 35px rgba(235,37,37,.55);
}

/* ================= FOOTER ================= */
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

/* ================= ANIMATION ================= */
@keyframes cardUp{
    from{opacity:0;transform:translateY(18px)}
    to{opacity:1;transform:translateY(0)}
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
    <img src="../../assets/img/logobummnew.png" class="nav-logo" alt="Logo PT">
    <span>
<?= htmlspecialchars(getSetting('system_name','RMBS')) ?> | Dashboard
</span>
</a>

    <div class="profile-trigger">
        <div class="avatar-mini">
            <?php if($avatarPath): ?>
                <img src="<?= $avatarPath ?>">
            <?php else: ?>
                <?= $initial ?>
            <?php endif; ?>
        </div>

        <span class="user-name"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
        <span class="role-badge"><?= strtoupper($role) ?></span>
        <span class="caret">▾</span>

        <div class="profile-dropdown">
            <a href="profile.php">👤 Profil Saya</a>
            <a href="profile.php#security">🔒 Keamanan Akun</a>
            <a href="#" class="danger" onclick="confirmLogout(event)">🚪 Logout</a>
        </div>
    </div>
</nav>

<div class="wrapper">

<a href="index.php" class="back-btn">← Kembali ke Dashboard</a>

<div class="card">

<h2 style="text-align:center;margin-bottom:10px">Profil Saya</h2>

<div class="avatar">
<?php if($avatarPath): ?>
    <img src="<?= $avatarPath ?>">
<?php else: ?>
    <?= $initial ?>
<?php endif; ?>
</div>

<form action="profile_update.php" method="POST" enctype="multipart/form-data">

<label>Foto Profil</label>
<input type="file" name="avatar" accept="image/*">

<label>Nama</label>
<input type="text" name="name"
       value="<?= htmlspecialchars($_SESSION['user']['name']) ?>" required>

<label>Email</label>
<input type="email" name="email"
       value="<?= htmlspecialchars($_SESSION['user']['email']) ?>" required>

<button type="submit">💾 Simpan Perubahan</button>
</form>

</div>
</div>

<footer class="footer">
<span>© <?= date('Y') ?> <strong><?= htmlspecialchars(getSetting('system_name','RMBS')) ?></strong>
</span>
<span>•</span>
<span><?= $footerRole ?> • <?= $footerDesc ?></span>
</footer>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.body.classList.add('loaded');
});

/* NAVBAR SCROLL EFFECT */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const cur = window.pageYOffset;

    navbar.classList.toggle('hide', cur > lastScroll && cur > 120);
    navbar.classList.toggle('shrink', cur > 90);

    lastScroll = cur <= 0 ? 0 : cur;
});

/* DROPDOWN */
const trigger=document.querySelector('.profile-trigger');
const dropdown=document.querySelector('.profile-dropdown');
trigger.addEventListener('click',e=>{
    e.stopPropagation();
    dropdown.classList.toggle('show');
});
document.addEventListener('click',()=>dropdown.classList.remove('show'));

function confirmLogout(e){
    e.preventDefault();
    if(confirm('Yakin logout?')){
        window.location='../../auth/logout_process.php';
    }
}
</script>

</body>
</html>
