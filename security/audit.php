<?php
function audit_log($conn, $action, $description = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user']['id'] ?? null;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent   = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
         VALUES (?,?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmt, "issss", $user_id, $action, $description, $ip, $agent);
    mysqli_stmt_execute($stmt);
}
