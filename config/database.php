<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'tamccdeli';
$ssl_ca = getenv('DB_SSL_CA'); // optional path to CA certificate

try {
    $conn = mysqli_init();
    
    // If a CA certificate is provided, configure SSL
    if ($ssl_ca) {
        mysqli_ssl_set($conn, null, null, $ssl_ca, null, null);
    }
    
    // Establish connection with SSL flag (required by Aiven)
    mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, null, MYSQLI_CLIENT_SSL);
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Show the error on screen (temporary)
    die("Database connection error: " . $e->getMessage());
}
?>