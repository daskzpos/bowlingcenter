<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
</head>
<body>
    <?php
    if (!class_exists('mysqli')) {
        echo '<div style="color:red;">Error: mysqli extension not loaded.</div>';
        exit;
    }

    $servername = "mysql";
    $username = "root";
    $password = "password";
    $dbname = "bowlingcenter";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo '<div style="color:red;">Connection failed: ' . $conn->connect_error . '</div>';
    } else {
        echo '<div style="color:green;">Connected successfully</div>';
    }
    ?>
</body>
</html>
