<?php
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/get_score.php';


    if (!isset($_SESSION['username'])){
        header("Location: ../auth/login.php");
        exit();
    }
    
    // $hscore = 0;
    // foreach ($score as $u){
    //     if ($u['user_name'] === $_SESSION['username']){
    //         $hscore = $u['high_score'];
    //         break;
    //     }
    // }

    $results = pg_query_params($conn, 'SELECT * FROM scores WHERE user_name = $1', [$_SESSION['username']]);
    $hscore = pg_fetch_assoc($results) ?: 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
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
                    <a class="nav-link active" aria-current="page" href="../game/user_profile.php">Profile</a>
                </li>
            </ul>
        </div>

        <div id="profile_info">
            <p>
                USER
                <br>
                <b><h2>[ <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?> ]</h2></b> 
                <br><br>

                <h3><b>ENDLESS MODE</b></h3>
                <br>
                HIGHEST SCORE
                <br>
                <b><h2><?php echo $hscore['high_score'] ?></h2></b> 
                <br>

                HIGHEST REACHED
                <br>
                <b><h2><?php echo $hscore['height_reached'] ?></h2></b> 
                <br><br>

            </p>
        <div id="footer">

            <nav class="nav">
                <a class="nav-link active" aria-current="page" href="../auth/edit_profile.php">Edit Profile</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </nav>            

        </div>
        </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>