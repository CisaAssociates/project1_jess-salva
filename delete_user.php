<?php 

include("db_config.php");

if($_SERVER['REQUEST_METHOD'] == "GET"){

    $conn = new mysqli($host,$user,$pass,$db);
    $sql = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $sql->bind_param("i", $_GET["id"]);
    if($sql->execute()){
        header("Location: manage-users.php");
    }

}

