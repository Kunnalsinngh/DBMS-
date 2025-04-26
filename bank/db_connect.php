<?php
$host     = "localhost";
$username = "root";
$password = "";        
$dbname   = "bankdb";
$port     = 3306;     


$mysqli = new mysqli($host, $username, $password, $dbname, $port);
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
