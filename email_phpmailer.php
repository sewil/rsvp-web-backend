<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmailWithPHPMailer($to, $subject, $htmlBody, $textBody = '') {
    $mail = new PHPMailer(true);
    try {
        if (DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // 0 = off, 1 = client, 2 = client and server
            $mail->Debugoutput = function($str, $level) {
                log_info("PHPMailer Debug ($level): $str");
            };
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->Timeout = 30; // SMTP timeout
        $mail->SMTPKeepAlive = true; // Keep connection alive

        // Recipients
        $mail->setFrom(FROM_EMAIL, 'OpenMG');
        $mail->addAddress($to);
        $mail->addReplyTo(FROM_EMAIL, 'OpenMG');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        if ($textBody) {
            $mail->AltBody = $textBody;
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_error("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
