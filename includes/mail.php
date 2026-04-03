<?php
/**
 * Send email using Resend API
 */
function sendEmail($to, $subject, $htmlBody = '', $textBody = '') {
    $apiKey = getenv('RESEND_API_KEY');
    if (!$apiKey) {
        error_log("RESEND_API_KEY not set in environment");
        return false;
    }
    
    // Use environment variable for FROM email, fallback to a default (must be verified in Resend)
    $fromEmail = getenv('RESEND_FROM_EMAIL') ?: 'noreply@yourdomain.com';
    $fromName = getenv('RESEND_FROM_NAME') ?: 'TAMCC Deli';
    $from = "$fromName <$fromEmail>";
    
    if (empty($htmlBody) && !empty($textBody)) {
        $htmlBody = nl2br($textBody);
    } elseif (empty($textBody) && !empty($htmlBody)) {
        $textBody = strip_tags($htmlBody);
    }
    
    $data = [
        'from'    => $from,
        'to'      => $to,
        'subject' => $subject,
        'html'    => $htmlBody,
        'text'    => $textBody
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        error_log("Resend email sent to $to: " . $response);
        return true;
    } else {
        error_log("Resend failed to $to: HTTP $httpCode - $response");
        return false;
    }
}

/**
 * Build order confirmation email content
 */
function buildOrderEmail($order_id, $total, $payment_method, $pickup_time = null, $instructions = '') {
    $subject = "Order Confirmation #$order_id - TAMCC Deli";
    $body = "<h2>Thank you for your order!</h2>
                    <p><strong>Order #:</strong> $order_id</p>
                    <p><strong>Total:</strong> $" . number_format($total, 2) . "</p>
                    <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                    <p><strong>Pickup Time:</strong> " . ($pickup_time ? date('M j, Y g:i a', strtotime($pickup_time)) : 'As soon as possible') . "</p>
                    <p><strong>Special Instructions:</strong> " . nl2br(htmlspecialchars($instructions)) . "</p>
                    <p>Your order will be ready for pickup at the TAMCC Deli counter.</p>
                    <p>Thank you for choosing Marryshow Mealhouse!</p>";
    return ['subject' => $subject, 'body' => $body];
}

/**
 * Build wallet top‑up confirmation email
 */
function buildTopupEmail($amount) {
    $subject = "Wallet Top-Up Successful - TAMCC Deli";
    $body = "<h2>Wallet Top-Up Confirmation</h2>
                <p>Amount added: <strong>$" . number_format($amount, 2) . "</strong></p>
                <p>Your wallet balance has been updated.</p>
                <p>You can now use your wallet to pay for orders at the kiosk or online.</p>";
    return ['subject' => $subject, 'body' => $body];
}
?>