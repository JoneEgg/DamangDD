<?php
    session_start();
    include('db.php');

    if (!isset($_SESSION['admin_access'])){
        header("Location: login.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == "POST"){
        $user_key = implode(" ", $_POST['disable_user']);
        $_SESSION['user_key'] = $user_key;
        header("Location: disable.php");
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
       
        <form method="POST">
            <table class="list-group">
                <tr>
                    <th>User</th>
                    <th>Score</th>
                    <th>Height</th>
                    <th></th>
                </tr>

                    <?php
                        $results = pg_query($conn, 'SELECT * FROM scores ORDER BY high_score DESC');
                        $user = pg_fetch_all($results);

                        foreach($user as $u): 
                            $uname = htmlspecialchars($u['user_name'], ENT_QUOTES, 'UTF-8');?>
                            
                        <tr>
                            <td><?php echo $uname ?></td>
                            <td><?php echo $u['high_score'] ?> </td>
                            <td><?php echo $u['height_reached'] ?> </td>
                            <td> <button type="submit" name="disable_user" value="<? $uname ?>" class="btn btn-secondary">Disable</button> </td>
                        </tr>
                    <?php endforeach; ?>                    
            </table>
        </form>

        <br><br>
        <a href="logout.php">Exit</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>