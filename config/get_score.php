<?php
require_once __DIR__ . '/../config/db.php';
session_start();

    if(!isset($_SESSION['username'])){
        header("Location: ../auth/login.php");
        exit();
    }

$query = 'SELECT s.user_name, s.high_score, s.height_reached, u.status
             FROM endless_scores as s
             LEFT JOIN user_details as u ON s.user_name = u.user_name
             ORDER BY s.high_score DESC
             ';
$results = pg_query($conn, $query);
$score = pg_fetch_all($results) ?: [];

?>