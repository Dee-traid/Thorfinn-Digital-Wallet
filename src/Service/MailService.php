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

    public function addWallet(string $email, string $fullName, string $walletName): bool{
        $mail = null;
        try{
            $mail = $this->createMailer();

            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $walletName = htmlspecialchars($walletName, ENT_QUOTES, 'UTF-8');

            $mail->Subject = "New wallet added to your profile";
            $mail->Body = "<h3> Hi {$name},</h3>
                            <p> A new wallet <h3>{$walletName}</h3> have been added to your profile, Login to confirm. </p>
                        ";
            $mail->send();

            return true;
                        
        }catch(MailException $e){
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function updateWallet(string $email, string $fullName, string $walletName): bool {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $walletName = htmlspecialchars($walletName, ENT_QUOTES, 'UTF-8');

            $mail->Subject = "Wallet updated successfully";
            $mail->Body = "<h3>Hi {$name},</h3><p>Your wallet <b>{$walletName}</b> has been successfully updated.</p>";
            $mail->send();
            return true;

        } catch (MailException $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function deleteWallet(string $email, string $fullName, string $walletName): bool{
        try {

            $mail = $this->createMailer();
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $walletName = htmlspecialchars($walletName, ENT_QUOTES, 'UTF-8');

            $mail->Subject = "Wallet deleted";
            $mail->Body = "<h3>Hi {$name},</h3><p>Your wallet <b>{$walletName}</b> has been deleted from your profile successfully.</p>";
            $mail->send();
            return true;
            
        } catch (MailException $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function loginAlert(string $email, string $fullName, string $ip): bool{
        try {

            $mail = $this->createMailer();
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $ip = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');

            $mail->Subject = "New Login Activity";
            $mail->Body = "<h3>Hi {$name},</h3><p>A successful login to your account was detected from IP address: <b>{$ip}</b>.</p>";
            $mail->send();
            return true;

        } catch (MailException $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function passwordResetSuccess(string $email, string $fullName): bool{
        try {

            $mail = $this->createMailer();
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

            $mail->Subject = "Password Successfully Reset";
            $mail->Body = "<h3>Hi {$name},</h3><p>Your account password has been successfully reset. If you didn't do this, please contact support immediately.</p>";
            $mail->send();
            return true;

        } catch (MailException $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendTransactionNotification(string $email, string $fullName, float $amount, string $type, string $walletName, string $reference): bool{
        try {

            $mail = $this->createMailer();
            $mail->addAddress($email, $fullName);
            $name = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $walletName = htmlspecialchars($walletName, ENT_QUOTES, 'UTF-8');
            $typeFormatted = ucfirst(strtolower($type));
            $formattedAmount = number_format($amount, 2);

            $mail->Subject = "Transaction Alert: {$typeFormatted} of {$formattedAmount}";
            $mail->Body = "<h3>Hi {$name},</h3>
                            <p>A new <b>{$typeFormatted}</b> transaction has occurred on your wallet.</p>
                            <ul>
                                <li><b>Wallet:</b> {$walletName}</li>
                                <li><b>Amount:</b> {$formattedAmount}</li>
                                <li><b>Reference:</b> {$reference}</li>
                            </ul>";
            $mail->send();
            return true;

        } catch (MailException $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }

}