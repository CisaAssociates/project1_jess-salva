<?php 

include 'db_config.php';

$conn = mysqli_connect($host,$user,$pass,$db);

$stmt = "ALTER TABLE attendance_logs ADD COLUMN access_granted BOOL";

if(mysqli_query($conn,$stmt)){
    echo "sucess";
};