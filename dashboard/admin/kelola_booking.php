<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/maintenance.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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

/* ===== DATABASE ===== */
$db   = new Database();
$conn = $db->connect();

/* ===== SYNC AVATAR SESSION (WAJIB UNTUK PROFILE) ===== */
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

$keyword = $_GET['q'] ?? '';

$sql = "
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
        b.created_at,
        r.nama AS ruangan,
        u.name AS pemesan
    FROM booking b
    JOIN ruangan r ON b.ruangan_id = r.id
    JOIN users u ON b.user_id = u.id
";

if (!empty($keyword)) {
    $sql .= " WHERE u.name LIKE ? ";
}

$sql .= " ORDER BY b.created_at DESC ";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($keyword)) {
    $search = "%{$keyword}%";
    mysqli_stmt_bind_param($stmt, "s", $search);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Kelola Booking | <?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?>
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
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Montserrat',sans-serif;
}

body{
    min-height:100vh;
    display:flex;
    flex-direction:column;

    padding-top:78px;
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
.fade-out{opacity:0}

/* ===== NAVBAR ===== */
.navbar{
    position:fixed;
    inset:0 0 auto 0;
    z-index:1000;
    background:rgba(15,23,42,.72);
    backdrop-filter:blur(10px);
    padding:12px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 8px 25px rgba(0,0,0,.45);
    transition:.35s ease;
}
.navbar.hide{transform:translateY(-100%)}
.navbar.shrink{padding:8px 26px}

.nav-brand{
    display:flex;
    align-items:center;
    gap:12px;
    color:#fff;
    text-decoration:none;
    font-weight:700;
    font-size:14px;
}

/* ===== PENYELARASAN FONT NAVBAR (FINAL) ===== */
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

.nav-logo{
    height:34px;
    transition:.35s;
}
.navbar.shrink .nav-logo{height:26px}

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

.wrapper{
    width:100%;
    margin:20px 0 100px;
    padding:0 16px;
    flex:1;
}

/* PAGE HEADER */
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:28px;
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
.page-header p{
    font-size:13px;
    opacity:.8;
}

/* BACK BUTTON */
.btn-back{
    text-decoration:none;
    color:#fff;
    background:rgba(255,255,255,.08);
    border:1px solid var(--border);
    padding:10px 20px;
    border-radius:12px;
    font-size:13px;
    transition:.3s;
}
.btn-back:hover{
    background:rgba(255,255,255,.15);
    transform:translateX(-4px);
}

.card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:26px;
    padding:24px 20px;
    backdrop-filter:blur(16px);

    opacity:0;
    transform:translateY(26px);
    animation:cardUp .7s cubic-bezier(.4,0,.2,1) forwards;
}

/* ================= TABLE ================= */
.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;

    /* 🔒 kunci struktur kolom */
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
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.5px;
    opacity:.7;
    background:rgba(255,255,255,.04);
    border-bottom:1px solid rgba(255,255,255,.12);
}

/* HOVER */
tbody tr{
    transition:background .25s ease;
}
tbody tr:hover{
    background:rgba(255,255,255,.05);
}

/* ================= FIX KOLOM (SEJAJAR REPORTS) ================= */
th:nth-child(1),
td:nth-child(1){ width:16%; }          /* Pemesan */

th:nth-child(2),
td:nth-child(2){ width:16%; }          /* Ruangan */

th:nth-child(3),
td:nth-child(3){ 
    width:14%; 
    text-align:center;                 /* Tanggal */
}

th:nth-child(4),
td:nth-child(4){ 
    width:14%; 
    text-align:center;                 /* Jam */
}

th:nth-child(5),
td:nth-child(5){ width:20%; }          /* Keperluan */

th:nth-child(6),
td:nth-child(6){ 
    width:10%; 
    text-align:center;                 /* Status */
}

th:nth-child(7),
td:nth-child(7){ 
    width:20%; 
    text-align:center;                 /* Aksi */
    min-width:190px;
}

/* ================= STATUS BADGE ================= */
.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
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
    background:rgba(127,29,29,.25);
    color:#fca5a5;
}

/* ================= ACTION ================= */
.action{
    display:flex;
    gap:8px;
    justify-content:center;
}

.btn{
    padding:7px 14px;
    border-radius:10px;
    font-size:11px;
    font-weight:600;
    border:none;
    cursor:pointer;
    color:#fff;
    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.btn:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 18px rgba(0,0,0,.35);
}

.btn:active{
    transform:scale(.96);
}

.btn-approve{
    background:linear-gradient(to right,#16a34a,#15803d);
}

.btn-reject{
    background:linear-gradient(to right,#dc2626,#991b1b);
}


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

/* ANIMATION */
@keyframes cardUp{
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
    max-width:420px;
    width:100%;
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
.btn-yes:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 30px rgba(235,37,37,.55);
}

/* ================= APPROVE MODAL ================= */
.approve-modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    backdrop-filter:blur(8px);
    display:flex;
    align-items:center;
    justify-content:center;
    opacity:0;
    pointer-events:none;
    transition:.35s ease;
    z-index:9999;
}

.approve-modal.show{
    opacity:1;
    pointer-events:auto;
}

.approve-box{
    width:100%;
    max-width:520px;
    background:linear-gradient(
        rgba(255,255,255,.18),
        rgba(255,255,255,.08)
    );
    backdrop-filter:blur(28px);
    border:1px solid rgba(255,255,255,.25);
    border-radius:28px;
    padding:34px;
    box-shadow:0 40px 100px rgba(0,0,0,.65);
    color:#fff;
    transform:scale(.92);
    transition:.35s;
}

.approve-modal.show .approve-box{
    transform:scale(1);
}

.approve-box h3{
    text-align:center;
    font-size:22px;
    margin-bottom:6px;
}

.approve-subtitle{
    text-align:center;
    font-size:13px;
    opacity:.75;
    margin-bottom:26px;
}

.approve-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.approve-grid .full{
    grid-column:1 / -1;
}

.approve-grid label{
    font-size:12px;
    font-weight:600;
    margin-bottom:6px;
    display:block;
}

.approve-grid input{
    width:100%;
    padding:13px 14px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(15,23,42,.65);
    color:#fff;
    font-size:13px;
}

.approve-grid input[readonly]{
    background:rgba(255,255,255,.08);
    cursor:not-allowed;
}

.approve-action{
    display:flex;
    gap:14px;
    margin-top:28px;
}

.approve-action .btn-cancel{
    flex:1;
}

.approve-action .btn-approve{
    flex:1;
}

th:nth-child(3),
th:nth-child(4),
th:nth-child(6),
th:nth-child(7),
td:nth-child(3),
td:nth-child(4),
td:nth-child(6),
td:nth-child(7){
    text-align:center;
}
td:last-child{
    min-width:190px;
}

.filter-bar{
    display:flex;
    gap:14px;
    align-items:center;
    flex-wrap:wrap;
}

.filter-bar input{
    width:240px;                 /* ⬅️ kunci ukuran */
    padding:9px 14px;            /* ⬅️ lebih ramping */
    border-radius:12px;
    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    font-size:12.5px;
}


.btn-filter{
    background:linear-gradient(to right,#eb2525,#b91c1c);
    padding:8px 18px;
    border-radius:12px;
    border:none;
    color:#fff;
    font-weight:600;
    font-size:12px;
    cursor:pointer;
}

.btn-reset{
    padding:8px 16px;
    border-radius:12px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    text-decoration:none;
    font-size:12px;
}
.header-right{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:10px;
}

/* SEARCH KECIL */
.search-under{
    display:flex;
    gap:8px;
    margin-top:10px; 
}

.search-under input{
    width:180px;
    padding:7px 12px;
    border-radius:10px;
    background:rgba(15,23,42,.65);
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    font-size:12px;
}

.search-under button{
    padding:7px 14px;
    border-radius:10px;
    border:none;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
    color:#fff;
    background:linear-gradient(to right,#eb2525,#b91c1c);
}

@media (max-width: 768px){

    /* ================= PAGE HEADER ================= */
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:14px;
    }

    .header-right{
        align-items:flex-start;
        width:100%;
    }

    .search-under{
        width:100%;
    }

    .search-under input{
        width:100%;
        flex:1;
    }

    /* ================= TABLE ================= */
    table{
        font-size:12px;
    }

    th, td{
        padding:10px 12px;
    }

    /* sembunyikan kolom kurang penting */
    th:nth-child(5),
    td:nth-child(5){
        display:none; /* Keperluan */
    }

    /* ================= ACTION BUTTON ================= */
    .action{
        flex-wrap:wrap;
        gap:6px;
    }

    .btn{
        padding:6px 12px;
        font-size:10.5px;
    }

    /* ================= APPROVE MODAL ================= */
    .approve-grid{
        grid-template-columns:1fr;
    }

    .approve-box{
        padding:26px 20px;
    }

    .approve-box h3{
        font-size:18px;
    }

    /* ================= NAVBAR ================= */
    .nav-brand span{
        display:none;
    }

    .user-name{
        display:none;
    }

    .role-badge{
        padding:4px 10px;
        font-size:10px;
    }
}

@media (max-width: 768px){

    /* 🔥 MATIKAN ATURAN DESKTOP */
    table{
        table-layout: auto;
    }

    th, td{
        white-space: normal;
    }

    /* TABLE SCROLL HORIZONTAL */
    .table-wrap{
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

@media (max-width: 768px){

    td:last-child{
        min-width: unset;
    }

    .action{
        flex-direction: column;
        align-items: stretch;
    }

    .action .btn,
    .action form button{
        width:100%;
        text-align:center;
    }
}

@media (max-width: 768px){
    th:nth-child(4),
    td:nth-child(4){
        display:none; /* Jam */
    }
}

</style>
</head>

<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand" onclick="smoothRedirect(event,this.href)">
        <img src="../../assets/img/logobummnew.png" class="nav-logo">
        <span>
<?= htmlspecialchars(getSetting('system_name', 'RMBS')) ?> | Dashboard
</span>
    </a>
    <div class="nav-user profile-trigger">

    <?php
    $avatarFile = $_SESSION['user']['avatar'] ?? null;
    $avatarPath = $avatarFile
        && file_exists(__DIR__.'/../../assets/img/avatars/'.$avatarFile)
        ? '../../assets/img/avatars/'.$avatarFile
        : null;
    ?>

    <div class="avatar-mini">
        <?php if ($avatarPath): ?>
            <img src="<?= $avatarPath ?>" alt="Avatar"
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

        <a href="#" class="danger" onclick="confirmLogout(event)">
            🚪 Logout
        </a>
    </div>

</div>

</nav>

<div class="wrapper">

   <div class="page-header">

    <div>
        <h1>Kelola Booking</h1>
        <p>Approve & pantau jadwal pemesanan ruangan</p>
    </div>

    <div class="header-right">
        <a href="index.php"
           class="btn-back"
           onclick="smoothRedirect(event,this.href)">
            ← Dashboard
        </a>

        <!-- SEARCH DI BAWAH DASHBOARD -->
        <form method="GET" class="search-under">
            <input
                type="text"
                name="q"
                placeholder="Cari pemesan..."
                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            >
            <button type="submit">Cari</button>
        </form>
    </div>

</div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pemesan</th>
                        <th>Ruangan</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Keperluan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['pemesan']) ?></td>
                        <td><?= htmlspecialchars($row['ruangan']) ?></td>
                        <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                        <td><?= substr($row['jam_mulai'],0,5) ?>
    –
    <?= substr($row['jam_selesai'],0,5) ?></td>
                        <td><?= htmlspecialchars($row['keperluan']) ?></td>
                        <td>
                            <span class="badge <?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
<td>
    <div class="action">

        <!-- DETAIL SELALU ADA -->
        <a href="detail_booking.php?id=<?= $row['id'] ?>"
           class="btn"
           style="background:linear-gradient(to right,#2563eb,#1e40af)">
           Detail
        </a>

        <?php if ($row['status'] === 'pending'): ?>

            <!-- APPROVE (HANYA PENDING) -->
            <button
                type="button"
                class="btn btn-approve"
                onclick="openApproveModal(
                    <?= $row['id'] ?>,
                    '<?= htmlspecialchars($row['pemesan']) ?>',
                    '<?= htmlspecialchars($row['ruangan']) ?>',
                    <?= (int)$row['jumlah_tamu'] ?>,
                    '<?= htmlspecialchars($row['request_konsumsi']) ?>'
                )">
                Approve
            </button>

            <!-- CANCEL -->
            <button
                type="button"
                class="btn"
                style="background:linear-gradient(to right,#f97316,#c2410c)"
                onclick="openCancelModal(
                    <?= $row['id'] ?>,
                    '<?= htmlspecialchars($row['pemesan']) ?>',
                    '<?= htmlspecialchars($row['ruangan']) ?>'
                )">
                Cancel
            </button>

            <!-- TOLAK -->
            <form action="booking_action.php" method="POST">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <button type="submit" class="btn btn-reject">Tolak</button>
            </form>

        <?php elseif ($row['status'] === 'approved'): ?>

            <!-- APPROVED → TIDAK ADA APPROVE -->
            <button
                type="button"
                class="btn"
                style="background:linear-gradient(to right,#f97316,#c2410c)"
                onclick="openCancelModal(
                    <?= $row['id'] ?>,
                    '<?= htmlspecialchars($row['pemesan']) ?>',
                    '<?= htmlspecialchars($row['ruangan']) ?>'
                )">
                Cancel
            </button>

        <?php endif; ?>

    </div>
</td>

                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;opacity:.7">
                            Belum ada data booking
                        </td>
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
    document.body.classList.add('page-loaded'); // aman untuk semua halaman
});

/* ================= SMOOTH REDIRECT ================= */
function smoothRedirect(e,url){
    e.preventDefault();
    document.body.classList.add('fade-out');
    setTimeout(()=>location.href=url,400);
}

/* ================= NAVBAR AUTO HIDE + SHRINK ================= */
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll',()=>{
    let cur = window.pageYOffset;
    navbar.classList.toggle('hide', cur > lastScroll && cur > 100);
    navbar.classList.toggle('shrink', cur > 80);
    lastScroll = cur <= 0 ? 0 : cur;
});

/* ================= LOGOUT MODAL ================= */
function confirmLogout(e){
    e.preventDefault();
    document.getElementById('logoutModal')?.classList.add('show');
}

function closeLogout(){
    document.getElementById('logoutModal')?.classList.remove('show');
}

function doLogout(){
    document.body.classList.add('fade-out');
    setTimeout(()=>{
        window.location.href = '../../auth/logout_process.php';
    },400);
}

/* Tutup modal pakai ESC */
document.addEventListener('keydown',(e)=>{
    if(e.key === 'Escape'){
        closeLogout();
        closeApprove(); // 🔹 tutup modal approve juga
    }
});

/* Klik backdrop untuk tutup modal */
document.getElementById('logoutModal')?.addEventListener('click',(e)=>{
    if(e.target.id === 'logoutModal'){
        closeLogout();
    }
});

/* ======================================================
   APPROVE BOOKING MODAL + INPUT BIAYA
====================================================== */
function openApproveModal(id, pemesan, ruangan, tamu, konsumsi){
    document.getElementById('approve_id').value = id;
    document.getElementById('approve_pemesan').value = pemesan;
    document.getElementById('approve_ruangan').value = ruangan;
    document.getElementById('approve_tamu').value = tamu + ' orang';
    document.getElementById('approve_konsumsi').value = konsumsi || '-';

    document.getElementById('approveModal')?.classList.add('show');
}

function closeApprove(){
    document.getElementById('approveModal')?.classList.remove('show');
}

/* Klik backdrop approve modal untuk tutup */
document.getElementById('approveModal')?.addEventListener('click',(e)=>{
    if(e.target.id === 'approveModal'){
        closeApprove();
    }
});

const profileTrigger = document.querySelector('.profile-trigger');
const profileDropdown = document.querySelector('.profile-dropdown');

profileTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown?.classList.toggle('show');
});

document.addEventListener('click', () => {
    profileDropdown?.classList.remove('show');
});

/* ================= CANCEL BOOKING MODAL ================= */
function openCancelModal(id, pemesan, ruangan){
    document.getElementById('cancel_id').value = id;
    document.getElementById('cancel_pemesan').value = pemesan;
    document.getElementById('cancel_ruangan').value = ruangan;

    document.getElementById('cancelModal')?.classList.add('show');
}

function closeCancel(){
    document.getElementById('cancelModal')?.classList.remove('show');
}

/* ESC close cancel modal */
document.addEventListener('keydown',(e)=>{
    if(e.key === 'Escape'){
        closeCancel();
    }
});

/* Click backdrop close */
document.getElementById('cancelModal')?.addEventListener('click',(e)=>{
    if(e.target.id === 'cancelModal'){
        closeCancel();
    }
});

</script>

<!-- ================= APPROVE BOOKING MODAL ================= -->
<div class="approve-modal" id="approveModal">
    <div class="approve-box">

        <h3>Approve Booking</h3>
        <p class="approve-subtitle">
            Masukkan biaya sebelum menyetujui booking
        </p>

<form method="POST" action="booking_action.php">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="id" id="approve_id">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

            <div class="approve-grid">
                <div>
                    <label>Pemesan</label>
                    <input type="text" id="approve_pemesan" readonly>
                </div>

                <div>
                    <label>Ruangan</label>
                    <input type="text" id="approve_ruangan" readonly>
                </div>

                <div>
                    <label>Jumlah Tamu</label>
                    <input type="text" id="approve_tamu" readonly>
                </div>

                <div>
                    <label>Request Konsumsi</label>
                    <input type="text" id="approve_konsumsi" readonly>
                </div>

                <div class="full">
                    <label>Biaya (Rp)</label>
                    <input
                        type="number"
                        name="biaya"
                        min="0"
                        placeholder="Contoh: 150000"
                        required>
                </div>
            </div>

            <div class="approve-action">
                <button type="button" class="btn-cancel" onclick="closeApprove()">
                    Batal
                </button>
                <button type="submit" class="btn-approve">
                    Approve Booking
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ================= CANCEL BOOKING MODAL ================= -->
<div class="approve-modal" id="cancelModal">
    <div class="approve-box">

        <h3>Cancel Booking</h3>
        <p class="approve-subtitle">
            Berikan alasan pembatalan booking
        </p>

<form method="POST" action="booking_action.php">
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="id" id="cancel_id">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

        <div class="approve-grid">
            <div class="full">
                <label>Pemesan</label>
                <input type="text" id="cancel_pemesan" readonly>
            </div>

            <div class="full">
                <label>Ruangan</label>
                <input type="text" id="cancel_ruangan" readonly>
            </div>

            <div class="full">
                <label>Alasan Pembatalan</label>
                <textarea
                    name="cancel_reason"
                    rows="4"
                    required
                    placeholder="Contoh: Jadwal bentrok / Ruangan tidak tersedia"
                    style="
                        width:100%;
                        padding:14px;
                        border-radius:14px;
                        background:rgba(15,23,42,.65);
                        border:1px solid rgba(255,255,255,.18);
                        color:#fff;
                        font-size:13px;
                        resize:none;
                    "></textarea>
            </div>
        </div>

        <div class="approve-action">
            <button type="button" class="btn-cancel" onclick="closeCancel()">
                Batal
            </button>
            <button type="submit"
                class="btn"
                style="background:linear-gradient(to right,#f97316,#c2410c)">
                Cancel Booking
            </button>
        </div>
</form>

    </div>
</div>

</body>
</html>
