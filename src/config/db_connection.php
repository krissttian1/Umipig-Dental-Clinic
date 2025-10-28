<?php
require_once __DIR__ . '/vendor/autoload.php'; // Load Composer autoloader

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create a MySQL connection using environment variables
$conn = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME']
);

// Check for connection error
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>
