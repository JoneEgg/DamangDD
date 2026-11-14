<?php
    include('db.php');

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] == ['POST']){
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm_pass'];
# USERNAME FILTERS ====================================================
        if (empty($username)){
            $errors[] = "Username is required";
        } else if (strlen($username) != 8) {
            $errors[] = "Username must be 8 characters long";
        }

        $usernamePattern = '/^(?=.*[\S])(?=.*[\s]).+$/';

        if (preg_match($usernamePattern, $username)){
            $errors[] = "Username must not have white space";
        }

        $result = pg_query_params($conn, 'SELECT user_name FROM user_details WHERE user_name = $username]');

        if (pg_fetch_assoc($result)){
            $errors[] = "Username already exists";
        }
# PASSWORD FILTER ====================================================
        if (empty($password) || empty($confirm_pass)) {
            $errors[] = "Please provide a password";   
        } else if (strlen($password) < 8){
            $errors[] = "Password must be 8 or more characters long";
        }

        if ($password != $confirm_pass){
            $errors[] = "Password did not match";
        }

        $passwordPattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_]).+$/';

        if (!preg_match($passwordPattern, $password)){
            $errors[] = "Password must contain lower, upper case and special characters";
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
# SUCCESS ====================================================
        if (empty($errors)){
            pg_query_params($conn, 'INSERT INTO user_details (user_name, password) VALUES (user_name = $username, password = $password)');
            
            echo "Registered successfully!";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
</head>
<body>
    <form method="POST"
        <label for="username">Usernane: </label>
        <input type="text" name="username" value="<?php echo (isset($_POST['username'])) ? $_POST['username'] : ""; ?>">
        <br><br>
        <label for="password">Password: </label>
        <input type="password" name="password">
        <br><br>
        <label for="confirm password">Confirm Password: </label>
        <input type="password" name="confirm_pass">
        <br><br>
        <button type="submit">Register</button>
    </form>
    <br><br>
    <a href="login.php">Login</a>

<!-- DISPLAYING ERRORS ==================================================== -->
    <?php 
        foreach($errors as $error){
            echo $error . "<br>";
        }
    ?>
</body>
</html>