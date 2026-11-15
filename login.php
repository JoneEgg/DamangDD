<?php
    session_start();
    include('db.php');
    $errors = [];

    if ($_SERVER["REQUEST_METHOD"] == "POST"){
        $username = $_POST['username'];
        $password = $_POST['password'];

        $result = pg_query_params($conn, 'SELECT * FROM user_details WHERE user_name = $1', [$username]);
        $user = pg_fetch_assoc($result);

        if ($user){
            if ($user && password_verify($password, $user['password'])){
                $_SESSION['username'] = $user['user_name'];
                if ($user['admin'] == 1){
                    $_SESSION['admin_access'] = $user['admin'];
                    header("Location: admin_view.php");
                    exit;
                } 
                header("Location: main_menu.php");
                exit;
            } else {
            $errors[] = "Invalid Credentials";
            }
        } 
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <title>Login</title>
</head>
<body id="form_body">
    <div id="container">
        <div id="banner">
            <img id="logo_img" src="./images/DDD_logo.png" alt="Damang, Don't Drown! Logo">
        </div>
        
        <form method="POST">
            <div id="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" placeholder="Username" name="username" required>
            </div>
            <br>
            <div id="form-group">
                <label for="exampleInputPassword1">Password</label>
                <input type="password" class="form-control" placeholder="Password" name="password" required>
            </div>
            <br><br>
            <button type="submit" class="btn btn-secondary">Login</button>
            <br><br>
            <p>Don't have an account yet? Register <a href="register.php">here!</a></p>
        </form>
        </div>

<!-- DISPLAYING ERRORS ==================================================== -->
    <?php 
        foreach($errors as $error){
            echo $error . "<br>";
        }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>