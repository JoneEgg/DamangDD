<?php
    session_start();
    include('db.php');

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] == 'POST'){
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm_pass'];
        $is_admin = isset($_POST['admin_access']);

# USERNAME FILTERS ====================================================
        if (empty($username)){
            $errors[] = "Username is required";
        } else if (strlen($username) < 8) {
            $errors[] = "Username must be 8 characters long or higher";
        }

        $usernamePattern = '/^(?=.*[\S])(?=.*[\s]).+$/';

        if (preg_match($usernamePattern, $username)){
            $errors[] = "Username must not have white space";
        }

        $result = pg_query_params($conn, 'SELECT user_name FROM user_details WHERE user_name = $1', [$username]);

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
            $adminValue = $is_admin ? 1 : 0;
            $insertResult = pg_query_params($conn, 'INSERT INTO user_details (user_name, password, admin) VALUES ($1, $2, $3)', [$username, $passwordHash, $adminValue]);

            if($insertResult){
                echo "Registered successfully! <br><br>";
            } else {
                echo 'Database error ' . pg_last_error($conn);
            }
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
    <form method="POST">
        <label for="username">Username: <br>
            <input type="text" placeholder="Username" name="username" value="<?php echo (isset($_POST['username'])) ? $_POST['username'] : ''; ?>">
        </label>
        <br><br>
        <label for="password">Password: <br>
            <input type="password" placeholder="Password" name="password">
        </label>
        
        <br><br>
        <label for="confirm password">Confirm Password: <br>
            <input type="password" placeholder="Confirm password" name="confirm_pass">
        </label>
        <br><br>
        <label for="admin">Are you an admin? 
            <input type="checkbox" name="admin_access" value="<?php echo isset($_POST['admin_access']) ? 'checked' : ''; ?>">
        </label>
        <br><br><br>
        <button type="submit">Register</button>
    </form>
    <br>
    <a href="login.php">Login</a>

<!-- DISPLAYING ERRORS ==================================================== -->
    <?php 
        foreach($errors as $error){
            echo $error . "<br>";
        }
    ?>
</body>
</html>