<?php 

include 'db_config.php';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "SELECT * FROM faceencodings INNER JOIN users on faceencodings.user_id = users.user_id INNER JOIN ON rfidcards ON users.user_id = rfidcards.user_id";
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

mysqli_close($conn);
