<?php
// Force autoloader inclusion
if (!class_exists('Resend\Resend')) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        error_log("Composer autoloader not found at: $autoloadPath");
        die("System error: missing dependencies. Please run 'composer install'.");
    }
}

use Resend\Resend;

function sendEmail($to, $subject, $body, $altBody = '') {
    global $conn;

    $apiKey = getenv('RESEND_API_KEY');
    if (!$apiKey) {
        error_log("Resend API key missing");
        return false;
    }

    // Use the sandbox sender if you haven't set up your own domain yet
    $fromEmail = getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev';
    $fromName = getenv('RESEND_FROM_NAME') ?: 'TAMCC Deli';

    $resend = new Resend($apiKey);

    try {
        $resend->emails->send([
            'from'    => "{$fromName} <{$fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $body,
            'text'    => $altBody ?: strip_tags($body),
        ]);

        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status) VALUES (?, ?, 'sent')");
            $stmt->bind_param("ss", $to, $subject);
            $stmt->execute();
        }
        return true;
    } catch (Exception $e) {
        error_log("Resend error: " . $e->getMessage());
        if ($conn) {
            $error_msg = substr($e->getMessage(), 0, 1000);
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status, error) VALUES (?, ?, 'failed', ?)");
            $stmt->bind_param("sss", $to, $subject, $error_msg);
            $stmt->execute();
        }
        return false;
    }
}