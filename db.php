<?php
$conn = mysqli_connect("localhost", "root", "","ipt2_activity");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}
?>