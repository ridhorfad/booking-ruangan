<?php
// 🔒 Anti cache & anti back browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Sistem Maintenance | RMBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#eb2525;
    --primary-dark:#b91c1c;
    --glass:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.18);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Montserrat',sans-serif;
}

body{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:#e5e7eb;

    background:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(235,37,37,.35), transparent 45%),
        linear-gradient(to bottom right,#0b1220,#020617);
    background-size:28px 28px,28px 28px,cover,cover;
}

/* BLUR SHAPE */
.bg{
    position:fixed;
    width:420px;
    height:420px;
    background:rgba(235,37,37,.3);
    filter:blur(140px);
    border-radius:50%;
    z-index:-1;
}
.bg.left{top:-160px;left:-160px}
.bg.right{bottom:-160px;right:-160px}

/* BOX */
.box{
    max-width:460px;
    padding:46px 38px;
    border-radius:28px;

    background:var(--glass);
    border:1px solid var(--border);
    backdrop-filter:blur(20px);

    box-shadow:0 45px 100px rgba(0,0,0,.6);
    animation:fadeUp .7s cubic-bezier(.4,0,.2,1);
}

@keyframes fadeUp{
    from{opacity:0;transform:translateY(18px)}
    to{opacity:1;transform:translateY(0)}
}

/* ICON */
.icon{
    width:72px;
    height:72px;
    margin:0 auto 22px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;

    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    box-shadow:0 20px 50px rgba(235,37,37,.55);
    font-size:34px;
}

/* TEXT */
h1{
    font-size:24px;
    margin-bottom:10px;
}

p{
    font-size:14px;
    opacity:.85;
    line-height:1.7;
}

/* BADGE */
.badge{
    display:inline-block;
    margin-top:22px;
    padding:8px 18px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;

    background:rgba(235,37,37,.2);
    color:#fca5a5;
    border:1px solid rgba(235,37,37,.35);
}

/* FOOTER */
.footer{
    margin-top:26px;
    font-size:12px;
    opacity:.6;
}

.btn-login{
    display:inline-block;
    margin-top:22px;
    padding:10px 22px;
    border-radius:14px;
    font-size:13px;
    font-weight:600;
    text-decoration:none;

    background:linear-gradient(to right,#eb2525,#b91c1c);
    color:#fff;

    transition:.25s;
}
.btn-login:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(235,37,37,.45);
}

</style>
</head>

<body>

<div class="bg left"></div>
<div class="bg right"></div>

<div class="box">
    <div class="icon">⚙️</div>

    <h1>Sistem Dalam Maintenance</h1>

    <p>
        Sistem <strong>Room Meeting Booking System (RMBS)</strong><br>
        sedang dalam proses pemeliharaan untuk peningkatan layanan.
        <br><br>
        Silakan coba kembali beberapa saat lagi.
    </p>

    <div class="badge">MAINTENANCE MODE AKTIF</div>

    <a href="/auth/login.php?superadmin=1" class="btn-login">
    🔐 Login sebagai Superadmin
</a>

    <div class="footer">
        © <?= date('Y') ?> RMBS • System Maintenance
    </div>
</div>

<script>
const CHECK_INTERVAL = 3000; // 3 detik

setInterval(() => {
    fetch('/booking-ruangan/helpers/check_maintenance.php', {
        cache: 'no-store'
    })
    .then(res => res.json())
    .then(data => {
        if (data.maintenance === 'off') {
            // redirect TANPA masuk history
            window.location.replace('/booking-ruangan/auth/login.php');
        }
    })
    .catch(() => {});
}, CHECK_INTERVAL);
</script>

</body>
</html>
