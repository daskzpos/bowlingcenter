<?php
$host = 'localhost';
$port = 3306;
$dbname = 'bowlingcenter';
$user = 'root';
$password = 'password';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname";
try {
    $pdo = new PDO($dsn, $user, $password);
    echo "Connected successfully";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
