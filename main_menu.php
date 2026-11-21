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
            <a href="main_game_End.php">Play</a> | 
            <a href="scoreboard.php">Scoreboard</a> | 
            <a href="logout.php">Logout</a>
            
            <!-- ^^ Change to PNG buttons -->
        </p>

        <p>
            <a href="main_game_endless.php"><h2>Endless Mode!!</h2></a>
        </p>
    </div>
</body>
</html>