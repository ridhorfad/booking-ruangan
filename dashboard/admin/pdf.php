<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin', 'superadmin']);

$db   = new Database();
$conn = $db->connect();

/* ===============================
   PARAMETER FILTER
================================ */
$user_id = $_GET['user'] ?? '';
$start   = $_GET['start'] ?? date('Y-m-01');
$end     = $_GET['end']   ?? date('Y-m-d');

/* Amankan input */
$user_id = mysqli_real_escape_string($conn, $user_id);

/* Validasi tanggal */
if ($start > $end) {
    [$start, $end] = [$end, $start];
}

/* ===============================
   QUERY DATA
================================ */
$query = "
    SELECT 
        b.tanggal,
        u.name AS user_name,
        r.nama AS ruangan,
        b.jam_mulai,
        b.jam_selesai,
        b.keperluan,
        b.jumlah_tamu,
        b.request_konsumsi,
        b.status,
        b.biaya,
        b.cancel_reason
    FROM booking b
    JOIN users u ON b.user_id = u.id
    JOIN ruangan r ON b.ruangan_id = r.id
    WHERE b.tanggal BETWEEN '$start' AND '$end'
      AND (b.user_id = '$user_id' OR '$user_id' = '')
    ORDER BY b.tanggal ASC
";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>
Laporan Booking | <?= htmlspecialchars(getSetting('system_name','RMBS')) ?>
</title>
<style>
@page{
    size: A4 landscape;
    margin: 20mm;
}

body{
    font-family: Arial, Helvetica, sans-serif;
    font-size:11px;
    color:#000;
    margin:0;
}

/* ===== HEADER ===== */
.print-header{
    width:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:10px;
}

.print-header img{
    height:55px;
}

.header-center{
    flex:1;
    text-align:center;
}

.company{
    font-size:13px;
    font-weight:bold;
    line-height:1.4;
}

.report-title{
    margin-top:6px;
    font-size:14px;
    font-weight:bold;
    text-transform:uppercase;
}

.report-info{
    margin-top:6px;
    font-size:11px;
    line-height:1.5;
}

/* garis pemisah header */
.header-separator{
    border:none;
    border-top:2px solid #000;
    margin:10px 0 16px;
}

/* ===== TABLE ===== */
table{
    width:100%;
    border-collapse:collapse;
    table-layout:fixed;
}

thead{
    display:table-header-group;
}

tfoot{
    display:table-footer-group;
}

th,td{
    border:1px solid #000;
    padding:6px 8px;
    word-wrap:break-word;
}

th{
    background:#eee;
    text-align:center;
    font-weight:bold;
}

/* cegah baris kepotong halaman */
tr{
    page-break-inside:avoid;
}

.text-center{text-align:center}
.text-right{text-align:right}

/* ===== FOOTER ===== */
.footer{
    margin-top:16px;
    font-size:11px;
    text-align:right;
}
</style>

</head>

<body onload="window.print()">

<!-- HEADER -->
<div class="print-header">
    <img src="../../assets/img/bummlogo.png">

    <div class="header-center">
        <div class="company">
    <?= htmlspecialchars(getSetting('system_name','Room Meeting Booking System')) ?>
</div>

        <div class="report-title">
            LAPORAN BOOKING RUANG MEETING
        </div>

        <div class="report-info">
            <strong>Periode:</strong>
            <?= date('d M Y', strtotime($start)) ?> s/d
            <?= date('d M Y', strtotime($end)) ?><br>

            <strong>Dicetak oleh:</strong>
            <?= htmlspecialchars($_SESSION['user']['name']) ?>
            (<?= strtoupper($_SESSION['user']['role']) ?>)<br>

            <strong>Tanggal Cetak:</strong>
            <?= date('d M Y H:i') ?>
        </div>
    </div>

    <img src="../../assets/img/logobumm.png">
</div>

<hr class="header-separator">

<!-- TABLE -->
<table>
<thead>
<tr>
    <th width="4%">No</th>
    <th width="10%">Tanggal</th>
    <th width="16%">User</th>
    <th width="14%">Ruangan</th>
    <th width="12%">Jam</th>
    <th width="18%">Keperluan</th>
    <th width="6%">Tamu</th>
    <th width="12%">Konsumsi</th>
    <th width="8%">Status</th>
    <th width="10%">Biaya</th>
</tr>
</thead>

<tbody>
<?php
$no = 1;
$totalIncome = 0;

if (mysqli_num_rows($result) == 0):
?>
<tr>
    <td colspan="10" class="text-center">
        Tidak ada data booking
    </td>
</tr>

<?php
else:
while ($r = mysqli_fetch_assoc($result)):
?>
<tr>
    <td class="text-center"><?= $no++ ?></td>
    <td class="text-center"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
    <td><?= htmlspecialchars($r['user_name']) ?></td>
    <td><?= htmlspecialchars($r['ruangan']) ?></td>
    <td class="text-center"><?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?></td>

        <td><?= nl2br(htmlspecialchars($r['keperluan'] ?: '-')) ?></td>
        <td class="text-center"><?= $r['jumlah_tamu'] ?: '-' ?></td>
        <td><?= htmlspecialchars($r['request_konsumsi'] ?: '-') ?></td>

    <td class="text-center">
    <?= ucfirst($r['status']) ?>

    <?php if ($r['status'] === 'cancelled' && !empty($r['cancel_reason'])): ?>
        <br>
        <small>
            (<?= htmlspecialchars($r['cancel_reason']) ?>)
        </small>
    <?php endif; ?>
</td>

    <td class="text-right">
<?php
if (in_array($r['status'], ['approved','cancelled']) && $r['biaya']) {

    // hanya approved yang dihitung income
    if ($r['status'] === 'approved') {
        $totalIncome += (int)$r['biaya'];
    }

    echo number_format($r['biaya'], 0, ',', '.');

    if ($r['status'] === 'cancelled') {
        echo '<br><small>(sebelum dibatalkan)</small>';
    }

} else {
    echo '-';
}
?>
</td>

</tr>
<?php
endwhile;
endif;
?>
</tbody>

<tfoot>
<tr>
    <th colspan="9" class="text-right">
        TOTAL PENDAPATAN
    </th>
    <th class="text-right">
        <?= number_format($totalIncome, 0, ',', '.') ?>
    </th>
</tr>
</tfoot>

</table>

<div class="footer">
    Dokumen ini dihasilkan oleh sistem
    <strong>
<?= htmlspecialchars(getSetting('system_name','RMBS')) ?>
</strong>
</div>

</body>
</html>
