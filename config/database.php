<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class Database {
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            $host     = getenv('DB_HOST') ?: 'localhost';
            $port     = getenv('DB_PORT') ?: 3306;
            $user     = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';
            $dbname   = getenv('DB_NAME') ?: 'tamccdeli';
            $ssl_ca   = getenv('DB_SSL_CA');

            $conn = mysqli_init();
            // Set connection timeout to avoid long hangs
            mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            if ($ssl_ca) {
                mysqli_ssl_set($conn, null, null, $ssl_ca, null, null);
            }
            // Use persistent connection flag if desired (MYSQLI_CLIENT_COMPRESS)
            mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, null, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_COMPRESS);
            $conn->set_charset("utf8mb4");
            // Set consistent time zone (use UTC to avoid confusion)
            $conn->query("SET time_zone = '+00:00'");
            self::$conn = $conn;
        }
        return self::$conn;
    }
}

try {
    $conn = Database::getConnection();
} catch (mysqli_sql_exception $e) {
    error_log(sprintf(
        "Database connection failed [%s] on %s: %s",
        $e->getCode(),
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        $e->getMessage()
    ));
    die("Database connection error. Please try again later.");
}