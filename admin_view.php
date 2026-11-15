<?php
    session_start();
    include['db.php'];

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
</head>
<body>
    
</body>
</html>