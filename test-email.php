<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';             // Gmail SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'ianleradd@email.com';    // Your Gmail
    $mail->Password = 'rzxr hcnl jnaq crjm';        // Gmail App Password
    $mail->SMTPSecure = 'tls';                  // Encryption
    $mail->Port = 587;

    // Recipients
    $mail->setFrom('ianleradd@email.com', 'Test App'); // Sender
    $mail->addAddress('ian56lerald@gmail.com');        // Recipient (CHANGE THIS)

    // Content
    $mail->Subject = 'Test Email from PHPMailer';
    $mail->Body    = 'If you are reading this, PHPMailer is working!';

    $mail->send();
    echo '✅ Email has been sent successfully.';
} catch (Exception $e) {
    echo "❌ Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
