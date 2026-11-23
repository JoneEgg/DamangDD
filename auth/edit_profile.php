<?php
    session_start();
    require_once __DIR__ . '/../config/db.php';

    $errors = [];
    $success = "";

    if (!isset($_SESSION['username'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $old_user = $_SESSION['username'];
        $new_user = trim($_POST['new_user'] ?? '');
        $new_pass = $_POST['new_pass'] ?? '';
        $confirm_new_pass = $_POST['confirm_new_pass'] ?? '';

        if (empty($new_user) && empty($new_pass) && empty($confirm_new_pass)) {
            $errors[] = "Please enter a new username or a new password to update.";
        }

# NEW USERNAME FILTERS ====================================================
        if (!empty($new_user)) {
            if (strlen($new_user) < 8) {
                $errors[] = "Username must be 8 characters long or higher";
            }

            if (preg_match('/\s/', $new_user)) {
                $errors[] = "Username must not have white space";
            }

            $result = pg_query_params($conn, 'SELECT user_name FROM user_details WHERE user_name = $1', [$new_user]);

            if (pg_fetch_assoc($result)) {
                $errors[] = "Username already exists";
            }
        }

# NEW PASSWORD FILTERS ====================================================
        if (!empty($new_pass) || !empty($confirm_new_pass)) {
            if (strlen($new_pass) < 8) {
                $errors[] = "Password must be 8 or more characters long";
            }

            if ($new_pass !== $confirm_new_pass) {
                $errors[] = "Password did not match";
            }

            $passwordPattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_]).+$/';

            if (!preg_match($passwordPattern, $new_pass)) {
                $errors[] = "Password must contain lower, upper case and special characters";
            }

            if (empty($errors)) {
                $passwordHash = password_hash($new_pass, PASSWORD_BCRYPT);
            }
        }

# SUCCESS ====================================================        
        if (empty($errors)) {
            $current_user = $old_user;

            // Update user if provided
            if (!empty($new_user)) {
                $updateUser = pg_query_params(
                    $conn,
                    'UPDATE user_details SET user_name = $1 WHERE user_name = $2',
                    [$new_user, $current_user]
                );

                if ($updateUser) { // Updates the scoreboards with the new username
                    pg_query_params($conn, 'UPDATE scores SET user_name = $1 WHERE user_name = $2', [$new_user, $current_user]);
                    pg_query_params($conn, 'UPDATE endless_scores SET user_name = $1 WHERE user_name = $2', [$new_user, $current_user]);

                    $_SESSION['username'] = $new_user;
                    $current_user = $new_user;
                } else {
                    $errors[] = "Failed to update username.";
                }
            }

            // Update password if provided 
            if (!empty($new_pass) && empty($errors)) {
                $updatePass = pg_query_params(
                    $conn,
                    'UPDATE user_details SET password = $1 WHERE user_name = $2',
                    [$passwordHash, $current_user]
                );

                if (!$updatePass) {
                    $errors[] = "Failed to update password.";
                }
            }

            if (empty($errors)) {
                $success = "Profile updated successfully!";
            }
        }
    }
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
        <form method="POST">
            <div id="form-group">
                <label for="new_user">Change Username<br>
                    <input type="text" class="form-control" placeholder="Username" name="new_user" value="<?php echo (isset($_POST['new_user'])) ? $_POST['new_user'] : ''; ?>">
                </label>
            </div>
            <br>
            <div id="form-group">
                <label for="password">Change Password <br>
                    <input type="password" class="form-control" placeholder="Password" name="new_pass">
                </label>
            </div>  
            <br>
            <div id="form-group">
                <label for="confirm password">Confirm Password<br>
                    <input type="password" class="form-control" placeholder="Confirm password" name="confirm_new_pass">
                </label>
            </div>
<!-- DISPLAYING ERRORS ==================================================== -->
            <?php 
                foreach($errors as $error){
                    echo "<p style='color: #e0682b;'>" . $error;
                }

                echo "<p style='color: #9cbc6dff;'>" . $success;
            ?>
            <br><br>
            <button type="submit" class="btn btn-secondary">Confirm</button> 
            <br><br>
            <a href="../game/user_profile.php">Back</a>
        </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>