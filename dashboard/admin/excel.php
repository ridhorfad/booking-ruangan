<?php
require_once '../../middleware/auth.php';
require_once '../../middleware/office_hours.php';
require_once '../../middleware/role.php';
require_once '../../config/database.php';
require_once '../../helpers/settings.php';

requireRole(['admin','superadmin']);

$db   = new Database();
$conn = $db->connect();

/* ===============================
   PARAMETER FILTER
================================ */
$user_id = $_GET['user'] ?? '';
$start   = $_GET['start'] ?? date('Y-m-01');
$end     = $_GET['end']   ?? date('Y-m-d');

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

/* ===============================
   HEADER EXCEL
================================ */
$systemName = getSetting('system_name', 'RMBS');
$filename = "Laporan_Booking_{$systemName}_{$start}_sd_{$end}.xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

/* ===============================
   HITUNG RINGKASAN
================================ */
$totalIncome    = 0;
$totalBooking   = 0;
$totalApproved  = 0;
$totalCancelled = 0;

mysqli_data_seek($result, 0);
while ($x = mysqli_fetch_assoc($result)) {
    $totalBooking++;
    if ($x['status'] === 'approved') {
        $totalApproved++;
        $totalIncome += (int)$x['biaya'];
    }
    if ($x['status'] === 'cancelled') {
        $totalCancelled++;
    }
}
mysqli_data_seek($result, 0);

/* ===============================
   KONFIGURASI EXCEL
================================ */
$totalCol = 8; // No + 7 kolom data
?>

<table border="1" cellpadding="8" cellspacing="0" width="100%">

<!-- JUDUL -->
<tr>
    <th colspan="8"
        style="font-size:18px;padding:14px;
               background:#e5e7eb;
               text-align:center;
               font-weight:bold">
        LAPORAN BOOKING RUANGAN MEETING
    </th>
</tr>

<!-- INFO -->
<tr>
    <td colspan="8" style="padding:10px">
        <strong>Periode:</strong>
        <?= date('d M Y', strtotime($start)) ?> s/d
        <?= date('d M Y', strtotime($end)) ?><br>

        <strong>Dicetak oleh:</strong>
        <?= htmlspecialchars($_SESSION['user']['name']) ?>
        (<?= strtoupper($_SESSION['user']['role']) ?>)<br>

        <strong>Tanggal Cetak:</strong>
        <?= date('d M Y H:i') ?>
    </td>
</tr>

<!-- SUMMARY -->
<tr>
    <td colspan="8" style="padding:10px;background:#f8fafc">
        <table width="100%" cellpadding="6" cellspacing="0">
            <tr style="text-align:center;font-weight:bold">
                <td style="border:1px solid #ccc;background:#e0f2fe">
                    Total Booking<br><?= $totalBooking ?>
                </td>
                <td style="border:1px solid #ccc;background:#dcfce7">
                    Approved<br><?= $totalApproved ?>
                </td>
                <td style="border:1px solid #ccc;background:#fee2e2">
                    Cancelled<br><?= $totalCancelled ?>
                </td>
                <td style="border:1px solid #ccc;background:#fef9c3">
                    Total Pendapatan<br>
                    Rp <?= number_format($totalIncome,0,',','.') ?>
                </td>
            </tr>
        </table>
    </td>
</tr>

<tr>
    <td colspan="8">&nbsp;</td>
</tr>

<!-- HEADER TABLE -->
<thead>
<tr style="background:#f3f4f6;font-weight:bold;text-align:center">
    <th width="5%">No</th>
    <th width="12%">Tanggal</th>
    <th width="18%">User</th>
    <th width="18%">Ruangan</th>
    <th width="16%">Jam</th>
    <th width="22%">Keperluan</th>
    <th width="10%">Status</th>
    <th width="12%">Biaya (Rp)</th>
</tr>
</thead>

<tbody>
<?php if (mysqli_num_rows($result) == 0): ?>
<tr>
    <td colspan="8" align="center" style="padding:20px">
        Tidak ada data booking pada periode ini
    </td>
</tr>
<?php else: ?>
<?php $no = 1; ?>
<?php while ($r = mysqli_fetch_assoc($result)): ?>
<tr style="background:<?= ($no % 2 == 0) ? '#f9fafb' : '#ffffff' ?>">
    <td align="center"><?= $no++ ?></td>
    <td align="center"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
    <td><?= htmlspecialchars($r['user_name']) ?></td>
    <td><?= htmlspecialchars($r['ruangan']) ?></td>
    <td align="center"><?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?></td>
    <td style="white-space:normal"><?= htmlspecialchars($r['keperluan'] ?: '-') ?></td>

    <td align="center" style="white-space:normal">
        <?= ucfirst($r['status']) ?>
        <?php if ($r['status'] === 'cancelled'): ?>
            <br>
            <small style="color:#b91c1c">
                <?= htmlspecialchars($r['cancel_reason'] ?: '-') ?>
            </small>
        <?php endif; ?>
    </td>

    <td align="right">
        <?= ($r['status'] === 'approved')
            ? number_format($r['biaya'],0,',','.')
            : ($r['biaya']
                ? number_format($r['biaya'],0,',','.') . '<br><small>(sebelum dibatalkan)</small>'
                : '-') ?>
    </td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</tbody>

<tfoot>
<tr>
    <th colspan="7" align="right"
        style="background:#f9fafb;font-weight:bold">
        TOTAL PENDAPATAN
    </th>
    <th align="right"
        style="background:#dcfce7;color:#166534;font-weight:bold">
        <?= number_format($totalIncome,0,',','.') ?>
    </th>
</tr>
</tfoot>

</table>

<br>

<table width="100%">
<tr>
    <td width="60%">
        <strong>Catatan:</strong><br>
        - Pendapatan hanya dihitung dari booking berstatus <strong>Approved</strong><br>
        - Data dihasilkan otomatis oleh sistem
    </td>
    <td width="40%" align="center">
        Mengetahui,<br><br><br>
        <strong>_____________________</strong><br>
        Admin RMBS
    </td>
</tr>
</table>
