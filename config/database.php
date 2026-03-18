<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Use environment variable DATABASE_URL if available (Render)
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    // Parse DATABASE_URL (format: mysql://user:pass@host:port/dbname)
    $parts = parse_url($databaseUrl);
    $host = $parts['host'];
    $port = $parts['port'] ?? 3306;
    $user = $parts['user'];
    $password = $parts['pass'];
    $dbname = ltrim($parts['path'], '/');
} else {
    // Local XAMPP settings
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "tamccdeli";
}

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}
?>