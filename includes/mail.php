<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body, $altBody = '') {
    global $conn; // Use the global database connection

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->Timeout    = 10; // 10 seconds timeout

        $mail->setFrom(getenv('SMTP_FROM'), getenv('SMTP_FROM_NAME'));
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();

        // Log success (if database connection exists and table is present)
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status) VALUES (?, ?, 'sent')");
            if ($stmt) {
                $stmt->bind_param("ss", $to, $subject);
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);

        // Log failure
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status, error) VALUES (?, ?, 'failed', ?)");
            if ($stmt) {
                $error_msg = substr($mail->ErrorInfo, 0, 1000);
                $stmt->bind_param("sss", $to, $subject, $error_msg);
                $stmt->execute();
                $stmt->close();
            }
        }

        return false;
    }
}