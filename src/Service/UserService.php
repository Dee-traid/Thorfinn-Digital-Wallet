<?php

namespace src\Service;

use src\Repository\UserRepository;
use src\Models\User;
use src\Utility\UtilityHelper;
use src\Utility\CSRF;
use Firebase\JWT\JWT;
use Firebase\JWT\KEY;
use DateTimeImmutable;
use Exception;
use Throwable;

class UserService{
    private UserRepository $userRepository;
    private MailService $mailService;

    public function __construct(UserRepository $userRepository, MailService $mailService){
        $this->userRepository = $userRepository;
        $this->mailService = $mailService;
    }


    private function sendEmailVerification(User $user){
        try {

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $createdAt = new DateTimeImmutable();
            $expiresAt = new DateTimeImmutable('+2 hours');
            $userId = $user->getID();
            $email = $user->getEmail();
            $fullName = $user->getFullName();

            $this->userRepository->saveVerificationToken($userId,$email, $tokenHash, $createdAt, $expiresAt);
            $link = rtrim($_ENV['APP_URI'], '/') . '?action=verify-email&token=' . $token;

            return $this->mailService->sendEmailVerification($email, $fullName, $link);
            
        } catch (Throwable $e) {
            error_log("Error sending verification link" . $e->getMessage());
            UtilityHelper::sendJson('error', "Error sending verification link", 500);
        }
       
    }

    public function verifyEmail(string $token): void{

        try {
            $record = $this->userRepository->findTokenVerification(hash('sha256', $token));
            $timeStamp = new DateTimeImmutable();
            if($record === null){
                UtilityHelper::sendJson('error', "Invalid/ Expired token", 400);
            }

            if($timeStamp > new DateTimeImmutable($record['expires_at'])){
                UtilityHelper::sendJson('error', "Verification link expired, Kindly request for a new link", 400);
            }

            $this->userRepository->markEmailVerified($record['user_id']);
            UtilityHelper::sendJson('success', "Email has been verified", 200);

        } catch (Throwable $e) {
            error_log("An error occurred in verifying Email" . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred while verifying email, Please try again" , 500);
        }
    }

    public function resendEmailVerification(string $email): void{
        try {

            $user = $this->userRepository->findUserByEmail($email);
            if($user === null && !$user->getEmailVerified()){
                $this->sendEmailVerification($user);
                UtilityHelper::sendJson('success', "Account with $user does not exist", 200);
            }

        } catch (Throwable $e) {
            error_log("Resend verification error:" . $e->getMessage);
            UtilityHelper::sendJson('error', "An error occurred", 500);
        }
    }

    public function registerUser(string $fullName, string $email, string $password, string $walletTransactionPin){
        $email = strtolower(trim($email));
        try {

            $existingUser = $this->userRepository->findUserByEmail($email);
            if ($existingUser !== null) {
                UtilityHelper::sendJson('error', 'Email already exists, Please sign into your account', 409);
            }

            $id = uniqid('user-',true);
            $passwordHash = UtilityHelper::hashValue($password);
            $walletTransactionPinHash = UtilityHelper::hashValue($walletTransactionPin);
            $timeStamp = new DateTimeImmutable();

            $user = new User($id, 
                $fullName, 
                $email, 
                $passwordHash, 
                $walletTransactionPinHash, 
                true, 
                false, 
                $timeStamp, 
                $timeStamp
            );

            $this->userRepository->createUser($user);

            try {
                $this->sendEmailVerification($user);
            } catch (Throwable $e) {
                error_log('Email verification failed: ' . $e->getMessage());
            }

            return UtilityHelper::sendJson('success', "User created successfully", 201);

        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred creating user", 500);
        }
    }

    public function generateToken(): string{
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $chars  = $letters . $digits;

        $token = $letters[random_int(0, 25)]
            . $digits[random_int(0, 9)];

        for ($i = 0; $i < 4; $i++) {
            $token .= $chars[random_int(0, 35)];
        }
        return $token;
    }

    public function userLogin(string $email, string $password, string $ip): void{
        try{

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                UtilityHelper::sendJson('error', 'Invalid email address', 400);
            }

            $loginAttempts = $this->userRepository->loginAttempts($email);
            if($loginAttempts >= 3){
                UtilityHelper::sendJson('error', "Too many attempts, Try again in 5min", 429);
            }

            $user = $this->userRepository->findUserByEmail($email);
            if($user === null || !UtilityHelper::verifyValue($password, $user->getPasswordHash())){
                if($user !== null){
                    $userId = $user->getID();
                    $this->userRepository->saveLoginAttempts($userId, $email, $ip);
                }

                UtilityHelper::sendJson('error', 'Incorrect email or password', 401);
            }

            if (!$user->getIsActive()) {
                UtilityHelper::sendJson('error', 'Account is disabled', 403);
            }

            if(!$user->getEmailVerified()){
                $this->resendEmailVerification($email);
                UtilityHelper::sendJson('success', 'A verification link has been sent to your account to verify your wallet account', 200);
            }

             if($user && UtilityHelper::verifyValue($password, $user->getPasswordHash())){
                
                $token = $this->generateToken();
                $tokenHash = hash('sha256', $token);
                $user = $this->userRepository->findUserByEmail($email);
                $email = $user->getEmail();
                $userId = $user->getID();
                $fullName = $user->getFullName();
                $createdAt = new DateTimeImmutable();
                $expiresAt = new DateTimeImmutable('+ 2min');
    
                $this->userRepository->saveToken($userId, $email, $tokenHash, $createdAt, $expiresAt);
                $emailToken = $this->mailService->loginToken($email,  $fullName, $token);

                $loginAttempts = $this->userRepository->deleteLoginAttempts($email);
                
                UtilityHelper::sendJson('success', "A verification code has been sent to your email", 200, [
                    'requires_2fa' => true,
                    'email' => $email
                ]);

            }
             UtilityHelper::sendJson('error', "Incorrect email or password", 400);
           
        }catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            UtilityHelper::sendJson('error', 'An error occurred, Please try again.', 500);
        }
    
    }

    public function verifyLogin(string $email, string $token){
        try {

            $user = $this->userRepository->findUserByEmail($email);
            if($user === null){
                 UtilityHelper::sendJson('error', "User not found", 404);
                 return;
            }

            $tokenHash = hash('sha256', $token);
            $record = $this->userRepository->findVerificationToken($tokenHash);
            if ($record === null) {
                UtilityHelper::sendJson(
                    'error',
                    'Invalid or expired login code',
                    400
                );
            }

            $now = new DateTimeImmutable();
            $expiresAt = new DateTimeImmutable($record['expires_at']);

            if ($now >= $expiresAt) {
                UtilityHelper::sendJson(
                    'error',
                    'Login code has expired. Please log in again.',
                    400
                );
            }

            $user = $this->userRepository->findUserByEmail($email);

            if ($user === null) {
                UtilityHelper::sendJson(
                    'error',
                    'Invalid login verification',
                    401
                );
            }
    
            if ((string) $user->getID() !== (string) $record['user_id']) {
                UtilityHelper::sendJson(
                    'error',
                    'Invalid login verification',
                    401
                );
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                throw new Exception('Unable to start PHP session.');
            }
    
            $key = $_ENV['JWT_KEY'];
            if (!$key) {
                throw new Exception("JWT_KEY is not configured.");
            }

            $payload = [
                "issuer" => "Thorfinn Wallet",
                "issuedAt" => time(),
                "expiresAt" => time() + 1800,
                "userId" => $user->getID()
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');
             
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getID();
            $csrfToken = CSRF::regenerateCsrfToken();
            
            UtilityHelper::sendJson('success', 'Login successful', 200, [
                'token' => $jwt,
                'csrf_token' => $csrfToken,
                'user'  => [
                    'id'        => $user->getID(),
                    'full_name' => $user->getFullName(),
                    'email'     => $user->getEmail(),
                ],
            ]);

        } catch (Throwable $e) {
             error_log("Login token verification error" . $e->getMessage());
             UtilityHelper::sendJson('error', "Unable to verify token, Try again", 500);
        }
         
     }
    

    public function forgotPassword(string $email){
       try {

            $user = $this->userRepository->findUserByEmail($email);
            if(!$user){
                UtilityHelper::sendJson('error', "User not found", 400);
            }
            
            $token = $this->generateToken();
            $tokenHash = hash('sha256', $token);
            $createdAt = new DateTimeImmutable();
            $expiresAt = new DateTimeImmutable('+2 min');
            $userId = $user->getID();
            $email = $user->getEmail();
            $fullName = $user->getFullName();

            $this->userRepository->saveToken($userId, $email, $tokenHash, $createdAt, $expiresAt);

            $tokenSent = $this->mailService->token($email, $fullName, $token);
             if($tokenSent === true){
                UtilityHelper::sendJson('success', "Verification token has been sent to your email, Expires in 2min", 200);
             }

       } catch (Throwable $e) {
            error_log("Error sending token to user email" . $e->getMessage());
            UtilityHelper::sendJson('error', "Error saving data", 500);
       }

    }
    public function verifyToken(string $token): void{
      try {

            $tokenHash = hash('sha256', $token);
            $record = $this->userRepository->findVerificationToken($tokenHash);
            $timeStamp = new DateTimeImmutable();

            if($record === null){
                UtilityHelper::sendJson('error', "Invalid or expired token", 400);
            }

            if($timeStamp > new DateTimeImmutable($record['expires_at'])){
                UtilityHelper::sendJson('error', "Token expired, Kindly request for a new token", 400);
            }

            UtilityHelper::sendJson('success', "Token verification successful", 200);

      } catch (Throwable $e) {
        error_log("An error occurred in sending token to email" . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred verifying token, Please try again" , 500);
      }
    }


    public function passwordReset(string $email, string $token, string $password){
        try {

            $user = $this->userRepository->findUserByEmail($email);
            if($user === null){
                UtilityHelper::sendJson('error', "User not found", 404);
                return;
            }
            $passwordHash = UtilityHelper::hashValue($password);
            $updatedAt = new DateTimeImmutable();

            $user->setPasswordHash($passwordHash);
            $user->setUpdatedAt($updatedAt);

            $updated = $this->userRepository->passwordUpdate($user);   
            if (!$updated) {
                UtilityHelper::sendJson('error', "Could not update password, Try again", 500);
            }

            UtilityHelper::sendJson('success', "Password successfully updated", 200);

        } catch (Throwable $e) {
            error_log("Password-update error:" . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred updating password, Please try again", 500);

        }
    }


}