<?php
    include('db.php');
    session_start();
    header('Location: admin_view.php');

    $aa = $_SESSION['user_key'];
    $key = implode(" ", $_SESSION['user_key']);

    $sql_disable = pg_query_params($conn, "UPDATE user_details SET status = 'disable' WHERE user_name = $2", [$key]);

    // STILL BROKEN I'M ALMOST THERE

?>