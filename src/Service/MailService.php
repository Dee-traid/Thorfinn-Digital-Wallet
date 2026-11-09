<?php

namespace src\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Exception;


class MailService{

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->Port       = $_ENV['MAIL_PORT'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

        $mail->setFrom($_ENV['MAIL_USER'], $_ENV['MAIL_FROM_NAME']);
        $mail->isHTML(true);

        return $mail;
    }

    public function sendEmailVerification(string $email, string $fullName, string $link){
        try{
            $mail = $this->createMailer();
            
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES);
            $link = htmlspecialchars($link, ENT_QUOTES);
            $mail->Subject = 'Verify your email address';
            $mail->Body    = "<p>Hi {$name},</p>
                <p>Kindly verify your email by clicking the link below. It expires in 2 min.</p>
                <p><a href=\"{$link}\">Verify your email</a></p>";
                $mail->AltBody = "Verify your email: {$link}";

            $mail->send();

            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
            return true;

        } catch (MailException $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function token(string $email, string $fullName, string $token){
        $mail = null;
        try {
            $mail = $this->createMailer();

            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $verificationToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
            $mail->Subject = "Password reset token";
            $mail->Body    = "<p>Hi {$name},</p>
                <p>Your verification token expires in 2min.</p>
                <h1>{$token}</h1>";
                $mail->AltBody = "Verify your email: {$verificationToken}";

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function loginToken(string $email, string $fullName, string $token){
        $mail = null;
        try{
            $mail = $this->createMailer();

            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $loginToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
            $mail->Subject = "Login Verification Token";
            $mail->Body    = "<p>Hi {$name},</p>
                <p>Your verification token expires in 2min.</p>
                <h1>{$token}</h1>";
            $mail->AltBody = "Verify your email: {$loginToken}";

            $mail->send();
            return true;
        }catch(MailException $e){
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}