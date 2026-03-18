<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Required environment variables (must be set in Render dashboard)
$host     = getenv('DB_HOST');
$port     = getenv('DB_PORT');
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname   = getenv('DB_NAME');
$ssl_ca   = getenv('DB_SSL_CA'); // Absolute path to CA certificate inside container

// ---- DEBUG START ----
echo "Current directory: " . __DIR__ . "<br>";
echo "CA path from env: " . $ssl_ca . "<br>";
if ($ssl_ca) {
    if (file_exists($ssl_ca)) {
        echo "CA file EXISTS.<br>";
        echo "First 100 characters: " . htmlspecialchars(file_get_contents($ssl_ca, false, null, 0, 100)) . "<br>";
    } else {
        echo "CA file DOES NOT EXIST.<br>";
    }
} else {
    echo "CA path is empty.<br>";
}
// You can also list files in the config directory to see what's there:
$configDir = __DIR__ . '/config';
if (is_dir($configDir)) {
    echo "Files in config directory: " . implode(', ', scandir($configDir)) . "<br>";
} else {
    echo "Config directory not found at: $configDir<br>";
}
// ---- DEBUG END ----

// Validate that all required variables are present
if (!$host || !$port || !$user || !$password || !$dbname || !$ssl_ca) {
    error_log("Missing required database environment variables");
    die("Database configuration error. Please check server logs.");
}

// Check if the CA file actually exists
if (!file_exists($ssl_ca)) {
    error_log("CA certificate not found at: $ssl_ca");
    die("Database SSL CA file missing. Please check configuration.");
}

try {
    $conn = mysqli_init();
    
    // Configure SSL with the provided CA certificate
    mysqli_ssl_set($conn, null, null, $ssl_ca, null, null);
    
    // Establish SSL connection
    mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, null, MYSQLI_CLIENT_SSL);
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Display error temporarily for debugging (remove or change to generic message after fixing)
    die("Database connection error: " . $e->getMessage());
}
?>