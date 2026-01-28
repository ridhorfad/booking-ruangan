<?php
/* ================= SESSION ================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ================= STATE ================= */
$showRegister = isset($_GET['register']) && $_GET['register'] == 1;
$isSuperadminLogin = isset($_GET['superadmin']) && $_GET['superadmin'] == 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login | RMBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ===================================================
   ROOT & RESET
=================================================== */
:root{
    --rmbs-red: 235, 37, 37;
    --rmbs-red-dark: 185, 28, 28;
    --dark-blue: 11, 18, 32;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    font-family:'Montserrat',sans-serif;
    background:var(--dark);
    overflow:hidden;
}

/* ===================================================
   BACKGROUND
=================================================== */
.bg{
    position:fixed;
    inset:0;
    background:
        radial-gradient(
            circle at top left,
            rgba(var(--rmbs-red), .32),
            transparent 48%
        ),
        radial-gradient(
            circle at bottom right,
            rgba(var(--rmbs-red), .18),
            transparent 55%
        ),
        linear-gradient(
            135deg,
            rgba(var(--dark-blue), .96),
            rgba(6, 12, 22, .98)
        );
    background-size:160% 160%;
    animation:bgMove 22s ease infinite;
}

@keyframes bgMove{
    0%{background-position:0% 50%}
    50%{background-position:100% 50%}
    100%{background-position:0% 50%}
}

.bg::after{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(235,37,37,.28) + noise 0.028;
    opacity:.028; /* ⭐ PALING IDEAL */
    pointer-events:none;
}

.bg::before{
    content:'';
    position:absolute;
    inset:-50%;
    background:linear-gradient(
        120deg,
        transparent,
        rgba(255,255,255,.06),
        transparent
    );
    animation:lightMove 12s linear infinite;
}

@keyframes lightMove{
    from{transform:translateX(-50%)}
    to{transform:translateX(50%)}
}

/* ===================================================
   LAYOUT
=================================================== */
.wrapper{
    position:relative;
    z-index:2;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:60px;
    gap:80px;
}

/* ===================================================
   INTRO
=================================================== */
.intro{
    max-width:520px;
    color:#fff;
    animation:fadeLeft 1s ease forwards;
}

.intro h1{
    font-size:42px;
    font-weight:700;
    line-height:1.2;
    margin-bottom:20px;
}

.intro p{
    font-size:15px;
    opacity:.9;
    line-height:1.6;
}

/* ===================================================
   AUTH CARD
=================================================== */
.auth-box{
    width:100%;
    max-width:420px;
    background:linear-gradient(
        rgba(255,255,255,.22),
        rgba(255,255,255,.14)
    );
    backdrop-filter:blur(34px);
    border:1px solid rgba(255,255,255,.28);
    padding:42px 38px;
    border-radius:26px;
    box-shadow:
        0 50px 120px rgba(0,0,0,.55),
        inset 0 1px 0 rgba(255,255,255,.35);
    animation:fadeUp .9s ease forwards;
    color:#fff;
}

/* ===================================================
   BRAND
=================================================== */
.brand{
    text-align:center;
}

.brand img{
    width:72px;
}

.brand h2{
    margin-top:6px;
    font-size:18px;
    letter-spacing:.6px;
}

/* ===== BRAND TEXT ===== */
.brand-title{
    margin-top:10px;
    font-size:18px;
    font-weight:700;
    letter-spacing:.6px;
}

.brand-sub{
    margin-top:4px;
    font-size:12px;
    opacity:.75;
    letter-spacing:.4px;
}

/* ===== FORM TITLE ===== */
.form-title{
    margin:22px 0 18px;
    font-size:19px;
    font-weight:600;
    text-align:center;
    letter-spacing:.3px;
}

/* ===================================================
   NOTIFICATION
=================================================== */
.notif{
    margin:14px 0;
    padding:10px 14px;
    border-radius:10px;
    font-size:13px;
    text-align:center;
    opacity:0;
    transform:translateY(-6px);
    transition:.4s ease;
    pointer-events:none;
}

.notif.show{
    opacity:1;
    transform:translateY(0);
}

.notif.error{
    background:rgba(235,37,37,.35);
    border:1px solid rgba(255,255,255,.25);
}

.notif.success{
    background:rgba(16,185,129,.35);
    border:1px solid rgba(255,255,255,.25);
}

/* ===================================================
   FORM
=================================================== */
h3{
    text-align:center;
    margin:20px 0;
    font-size:20px;
}

.form-group{
    margin-bottom:14px;
}

label{
    font-size:13px;
    font-weight:600;
    display:block;
    margin-bottom:6px;
}

input{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:none;
    background:rgba(255,255,255,.95);
    font-size:14px;
}

input:focus{
    outline:none;
    box-shadow:0 0 0 3px rgba(235,37,37,.35);
}

/* ===================================================
   BUTTON
=================================================== */
button{
    width:100%;
    padding:13px;
    margin-top:14px;

    background:linear-gradient(
        to right,
        rgb(var(--rmbs-red)),
        rgb(var(--rmbs-red-dark))
    );

    border:none;
    border-radius:14px;
    color:#fff;
    font-weight:700;
    font-size:14px;
    letter-spacing:.3px;

    cursor:pointer;
    position:relative;
    overflow:hidden;

    box-shadow:
        0 14px 35px rgba(235,37,37,.45),
        inset 0 1px 0 rgba(255,255,255,.25);

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        filter .25s ease;
}
button:hover{
    transform:translateY(-2px);
    box-shadow:
        0 22px 55px rgba(235,37,37,.6),
        inset 0 1px 0 rgba(255,255,255,.3);
    filter:brightness(1.05);
}
button:active{
    transform:translateY(0);
    box-shadow:
        0 10px 25px rgba(235,37,37,.45);
}

/* ===================================================
   SWITCH
=================================================== */
.switch{
    margin-top:14px;
    font-size:13px;
    text-align:center;
}

.switch a{
    color:#fff;
    font-weight:600;
    text-decoration:underline;
}

/* ===================================================
   ANIMATION
=================================================== */
@keyframes fadeUp{
    from{opacity:0;transform:translateY(40px)}
    to{opacity:1;transform:translateY(0)}
}

@keyframes fadeLeft{
    from{opacity:0;transform:translateX(-40px)}
    to{opacity:1;transform:translateX(0)}
}

@keyframes fadeBg{
    from{opacity:0}
    to{opacity:1}
}

/* ===================================================
   RESPONSIVE
=================================================== */
@media(max-width:900px){
    .wrapper{
        flex-direction:column;
        padding:30px;
        gap:40px;
    }
    .intro{
        text-align:center;
    }
}
</style>
</head>

<body>

<div class="bg"></div>

<div class="wrapper">

<div class="intro">
    <h1>Room Meeting<br>Booking System</h1>
    <p>
        Platform terpusat untuk pemesanan ruang meeting internal
        guna mendukung kolaborasi, efisiensi, dan produktivitas kerja.
    </p>
</div>

<div class="auth-box">

<div class="brand">
    <img src="../assets/img/logobummnew.png" alt="BUMM">

    <h2 class="brand-title">BUMM</h2>

    <p class="brand-sub">
        Room Meeting Booking System
    </p>
</div>

<?php if($isSuperadminLogin): ?>
<div class="notif show" style="
    background:rgba(235,37,37,.35);
    border:1px solid rgba(255,255,255,.25);
    font-size:13px;
    margin-top:14px;
">
    🔐 <strong>Superadmin Access</strong><br>
    Login khusus untuk manajemen sistem saat maintenance
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="notif error show" id="notif">
    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div class="notif success show" id="notif">
    <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if(!$showRegister || $isSuperadminLogin): ?>
<form method="POST" action="login_process.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
<h3 class="form-title">Masuk ke Akun Anda</h3>

<div class="form-group">
    <label>Email</label>
    <input type="email" name="email" required autocomplete="email">
</div>

<div class="form-group">
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password">
</div>

<button type="submit">Masuk</button>

<?php if(!$isSuperadminLogin): ?>
<div class="switch">
    Belum punya akun? <a href="?register=1">Daftar</a>
</div>
<?php endif; ?>

</form>

<?php else: ?>
<form method="POST" action="register_process.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
<h3>Registrasi Akun</h3>

<div class="form-group">
    <label>Nama Lengkap</label>
    <input type="text" name="name" required>
</div>

<div class="form-group">
    <label>Email (Gmail)</label>
    <input type="email" name="email" required
        pattern="^[a-zA-Z0-9]+@gmail\.com$">
    <small style="font-size:11px;opacity:.8">
        * Harus Gmail & mengandung huruf + angka
    </small>
</div>

<div class="form-group">
    <label>Password</label>
    <input type="password" name="password" required minlength="8"
        pattern="(?=.*[A-Z])(?=.*\d).*">
    <small style="font-size:11px;opacity:.8">
        * Minimal 8 karakter, huruf besar & angka
    </small>
</div>

<button type="submit">Daftar</button>

<div class="switch">
    Sudah punya akun? <a href="login.php">Login</a>
</div>
</form>
<?php endif; ?>

</div>
</div>

<script>
document.addEventListener('input', e=>{
    if(e.target.name==='password' && e.target.form?.action.includes('register')){
        const v=e.target.value;
        const ok=/[A-Z]/.test(v)&&/\d/.test(v)&&v.length>=8;
        e.target.style.boxShadow=ok
            ?'0 0 0 2px rgba(16,185,129,.6)'
            :'0 0 0 2px rgba(235,37,37,.6)';
    }
});

window.addEventListener('DOMContentLoaded',()=>{
    const n=document.getElementById('notif');
    if(n){
        setTimeout(()=>{
            n.classList.remove('show');
            setTimeout(()=>n.remove(),400);
        },2300);
    }
});
</script>

</body>
</html>
