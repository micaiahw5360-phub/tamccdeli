<?php
require __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect_to_login();
}