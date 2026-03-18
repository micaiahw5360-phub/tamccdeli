<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'tamccdeli';
$ssl_ca = getenv('DB_SSL_CA'); // path to CA certificate

// If CA path is set but file doesn't exist, try system CA bundle
if ($ssl_ca && !file_exists($ssl_ca)) {
    error_log("CA file not found at: $ssl_ca. Trying system CA bundle.");
    // Common system CA bundle paths
    $system_ca = '/etc/ssl/certs/ca-certificates.crt';
    if (file_exists($system_ca)) {
        $ssl_ca = $system_ca;
    } else {
        // If system bundle also missing, disable CA verification (NOT RECOMMENDED for production)
        error_log("System CA bundle also not found. Proceeding without CA verification (INSECURE).");
        $ssl_ca = null;
    }
}

try {
    $conn = mysqli_init();
    
    // If a CA certificate is available, configure SSL
    if ($ssl_ca) {
        mysqli_ssl_set($conn, null, null, $ssl_ca, null, null);
    }
    
    // Establish connection with SSL flag (required by Aiven)
    // Use MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT only if no CA cert available (insecure)
    $flags = $ssl_ca ? MYSQLI_CLIENT_SSL : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, null, $flags);
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error: " . $e->getMessage());
}
?>