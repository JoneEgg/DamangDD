<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: login.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Menu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body id="form_body">
    <div id="menu_container">
        <div id="menu_banner">
            <img id="logo_img" src="./images/DDD_logo.png" alt="Damang, Don't Drown! Logo">
        </div>

        <p>
            <a href="main_game.php">Play</a> | 
            <a href="logout.php">Logout</a>
            <!-- ^^ Change to PNG buttons -->
        </p>
    </div>
</body>
</html>