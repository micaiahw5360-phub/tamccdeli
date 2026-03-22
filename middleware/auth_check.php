<?php
require __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_helpers.php'; // for redirect_to_login

if (!isset($_SESSION['user_id'])) {
    redirect_to_login();
}