<?php
require 'config/database.php';
require 'includes/csrf.php'; // only if you want to protect it

$username = 'kiosk';
$email = 'kiosk@tamccdeli.com';
$plain_password = 'password1234'; // change to something secure
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'kiosk', 1)");
$stmt->bind_param("sss", $username, $email, $hashed_password);
if ($stmt->execute()) {
    echo "Kiosk user created successfully!<br>";
    echo "Username: $username<br>";
    echo "Password: $plain_password<br>";
} else {
    echo "Error: " . $conn->error;
}
?>