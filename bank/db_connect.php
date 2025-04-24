<?php
$host     = "localhost";
$username = "root";
$password = "";        // ← make sure this is blank
$dbname   = "bankdb";
$port     = 3306;      // or 3307 if you moved MySQL to that port

// include port if non-default
$mysqli = new mysqli($host, $username, $password, $dbname, $port);
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
