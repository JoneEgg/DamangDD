<?php
    require_once __DIR__ . '/../config/db.php';
    session_start();


    $key = $_SESSION['user_key'];
    $status_check = pg_query_params($conn, 'SELECT status FROM user_details WHERE user_name = $1', [$key]);
    $db_status = pg_fetch_assoc($status_check);


    $new_status = ($db_status['status'] === 'active') ? 'disabled' : 'active';
    $set_status = pg_query_params($conn, 'UPDATE user_details SET status = $1 WHERE user_name = $2', [$new_status, $key]);

    header('Location: ../admin/admin_view.php');
?>