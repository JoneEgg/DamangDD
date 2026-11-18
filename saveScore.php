<?php
    include('db.php');
    session_start();

    if(!isset($_SESSION['username'])){
        header("Location: login.php");
        exit();
    }

    $username = $_SESSION['username'];
    $high_score = (int)($_POST['bestCoins']);
    $height_reached = (int)($_POST['bestScore']);

        $insert_sql = 'INSERT INTO scores (user_name, high_score, height_reached) 
            VALUES ($1, $2, $3)
            ON CONFLICT (user_name)
            DO UPDATE SET 
            high_score = GREATEST(scores.high_score, EXCLUDED.high_score),
            height_reached = GREATEST(scores.height_reached, EXCLUDED.height_reached)';
        $sql = pg_query_params($conn, $insert_sql, [$username, $high_score, $height_reached]);

?>
