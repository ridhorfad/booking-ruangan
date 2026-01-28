<?php
require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->connect();

$ruangan_id = (int) ($_GET['id'] ?? 0);

$q = mysqli_query($conn, "
    SELECT 
        gambar,
        posisi
    FROM ruangan_detail
    WHERE ruangan_id = $ruangan_id
    ORDER BY created_at ASC
");

$data = [];
while ($r = mysqli_fetch_assoc($q)) {
    $data[] = $r;
}

header('Content-Type: application/json');
echo json_encode($data);
