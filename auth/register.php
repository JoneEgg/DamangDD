    <?php
    session_start();
    require_once __DIR__ . '/../config/db.php';

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
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <title>Registration</title>
</head>
<body id="form_body">
    <div id="container">
        <div id="banner">
            <img id="logo_img" src="../assets/images/DD_logo.png" alt="Damang, Don't Drown! Logo">
        </div>
        
        <form method="POST">
            <div id="form-group">
                <label for="username">Username<br>
                    <input type="text" class="form-control" placeholder="Username" name="username" value="<?php echo (isset($_POST['username'])) ? $_POST['username'] : ''; ?>">
                </label>
            </div>
            <br>
            <div id="form-group">
                <label for="password">Password <br>
                    <input type="password" class="form-control" placeholder="Password" name="password">
                </label>
            </div>  
            <br>
            <div id="form-group">
                <label for="confirm password">Confirm Password<br>
                    <input type="password" class="form-control" placeholder="Confirm password" name="confirm_pass">
                </label>
            </div>
            <br>
            <div class="form-group form-check">
                <label for="admin">Are you an admin? 
                <input type="checkbox" class="form-check-input" name="admin_access" value="<?php echo isset($_POST['admin_access']) ? 'checked' : ''; ?>">
            </label>
            </div>
<!-- DISPLAYING ERRORS ==================================================== -->
            <?php 
                foreach($errors as $error){
                    echo $error . "<br>";
                }
            ?>
            <br><br>
            <button type="submit" class="btn btn-secondary">Register</button> 
            <br><br>
            <p>Already have an account? Log in <a href="../auth/login.php">here!</a></p>
        </form>
        <br>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>