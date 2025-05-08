<?php

include 'db_config.php';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "
SELECT * FROM faceencodings 
LEFT JOIN users ON faceencodings.user_id = users.user_id 
LEFT JOIN rfidcards ON users.user_id = rfidcards.user_id

UNION

SELECT * FROM faceencodings 
RIGHT JOIN users ON faceencodings.user_id = users.user_id 
RIGHT JOIN rfidcards ON users.user_id = rfidcards.user_id
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

$stmt = "DELETE FROM rfidcards WHERE card_id = 'f3c49fe4' ";
mysqli_query($conn,$stmt);

mysqli_close($conn);
