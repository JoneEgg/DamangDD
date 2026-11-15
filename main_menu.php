<?php
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
<body>
    <a href="main_game.php">Play</a>
    <a href="logout.php">Logout</a>
</body>
</html>