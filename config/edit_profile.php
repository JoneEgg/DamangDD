<?php
require_once __DIR__ . '/../config/db.php';
session_start();

    if(!isset($_SESSION['username'])){
        header("Location: ../auth/login.php");
        exit();
    }

?>