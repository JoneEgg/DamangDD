<?php
    session_start();
    include('db.php');

    if (!isset($_SESSION['admin_access'])){
        header("Location: login.php");
        exit();
    }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body class="admin_body">

    

    <div id="container">
        <div id="banner">
            <img id="logo_img" src="./images/DDD_logo.png" alt="Damang, Don't Drown! Logo">
        </div>
        <p><b>Welcome to Admin Access!</b><br></p>
       
        <ul class="list-group">
            <?php
                $results = pg_query($conn, 'SELECT * FROM user_details');
                $user = pg_fetch_all($results);
                        // This should actually be showing all the scores from the /scores/ table, but as of 11/16/2026 the main gameplay code is still not available.

                foreach($user as $u){
                    echo "<li class='list-group-item'>" . $u['user_name'] . "  <a href='delete.php'>Delete</a>";
                        // Delete not functioning yet, fix:
                            // Each loop should present their id as a hidden variable which will be read by the sql query in /delete.php/.
                }
            ?>
        </ul>

        <br><br>
        <a href="logout.php">Exit</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>