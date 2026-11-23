<?php
    session_start();
    require_once __DIR__ . '/../config/db.php';

    if (!isset($_SESSION['admin_access'])){
        header("Location: ../auth/login.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == "POST"){
        $_SESSION['user_key'] = $_POST['change_status'];
        header("Location: ../admin/disable.php");
        exit();
    }

    $query = 'SELECT s.user_name, s.high_score, s.height_reached, u.status
             FROM scores as s
             LEFT JOIN user_details as u ON s.user_name = u.user_name
             ORDER BY s.high_score DESC
             ';
    $results = pg_query($conn, $query);
    $user = pg_fetch_all($results) ?: [];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body class="admin_body">

    

    <div id="container">
        <div id="banner">
            <img id="logo_img" src="../assets/images/DDD_logo.png" alt="Damang, Don't Drown! Logo">
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
                        foreach($user as $u): 
                            $uname = htmlspecialchars($u['user_name'], ENT_QUOTES, 'UTF-8');
                            $is_disabled = ($u['status'] === 'disabled');
                            $btn_label = $is_disabled ? 'Enable' : 'Disable';
                            $btn_class = $is_disabled ? 'btn-success' : 'btn-secondary';
                    ?>
                            
                        <tr>
                            <td><?php echo $uname ?></td>
                            <td><?php echo $u['high_score'] ?> </td>
                            <td><?php echo $u['height_reached'] ?> </td>
                            <td> <button id="btn" type="submit" name="change_status" value="<?php echo $uname ?>" class="btn <?php echo $btn_class; ?>"><?php echo $btn_label ?></button> </td>
                        </tr>
                    <?php endforeach; ?>                    
            </table>
        </form>

        <br><br>
        <a href="../auth/logout.php">Exit</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>