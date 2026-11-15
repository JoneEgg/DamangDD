<?php
    include('db.php');
    $error = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST"){
        $username = $_POST['username'];
        $password = $_POST['password'];

        $result = pg_query_params($conn, 'SELECT * FROM user_details WHERE user_name = $1', [$username]);
        $user = pg_fetch_assoc($result);

        if ($user){
            if (password_verify($password, $user['password'])){
                $_SESSION['username'] = $user['username'];
                header("Location: main_menu.php");
            } else {
                $error = "Invalid Credentials";
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <form method="POST">
        <label for="Username">Enter Username: <br>
            <input type="text" placeholder="Username" name="username" required>
        </label><br><br>
        <label for="Password">Enter Password: <br>
            <input type="password" placeholder="Password" name="password">
        </label><br><br>
        <button type="submit">Login</button>
        <br><br>
        <a href="register.php">Register</a>
    </form>
</body>
</html>