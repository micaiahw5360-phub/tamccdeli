<?php
use Resend\Resend;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body, $altBody = '') {
    global $conn;

    $apiKey = getenv('RESEND_API_KEY');
    if (!$apiKey) {
        error_log("Resend API key missing");
        return false;
    }

    // Use the Resend sandbox sender – no domain verification required
    $fromEmail = 'onboarding@resend.dev';
    $fromName = 'TAMCC Deli';

    $resend = new Resend($apiKey);

    try {
        $resend->emails->send([
            'from'    => "{$fromName} <{$fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $body,
            'text'    => $altBody ?: strip_tags($body),
        ]);

        // Log success
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status) VALUES (?, ?, 'sent')");
            $stmt->bind_param("ss", $to, $subject);
            $stmt->execute();
        }
        return true;
    } catch (Exception $e) {
        error_log("Resend error: " . $e->getMessage());

        // Log failure
        if ($conn) {
            $error_msg = substr($e->getMessage(), 0, 1000);
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status, error) VALUES (?, ?, 'failed', ?)");
            $stmt->bind_param("sss", $to, $subject, $error_msg);
            $stmt->execute();
        }
        return false;
    }
}