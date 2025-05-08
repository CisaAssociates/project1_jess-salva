<?php

include 'db_config.php';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "
SELECT * FROM users
";

$result = mysqli_query($conn, $sql);

if ($result) {
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "Error: " . mysqli_error($conn);
}

$stmt = "INSERT INTO users (first_name, last_name, email, password, role) VALUES ('admin', ' ', 'albolerasjoshualuis@gmail.com', 'admin123', 'Admin')";
mysqli_query($conn,$stmt);

mysqli_close($conn);
