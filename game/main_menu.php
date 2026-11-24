<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../auth/login.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Menu</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body id="form_body">
    <div id="container">
        <div id="nav_bar">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="../game/main_game.php">Play</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="../game/main_game_endless.php">Endless</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../game/scoreboard.php">Scoreboards</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../game/user_profile.php">Profile</a>
                </li>
            </ul>
        </div>
        
        <div id="menu_banner">
            <img id="logo_img" src="../assets/images/DD_logo.png" alt="Damang, Don't Drown! Logo">
            <p>A game by BL_0T</p>
        </div>
        
        <br><br>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>