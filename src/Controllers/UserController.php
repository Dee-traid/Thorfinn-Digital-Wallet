<?php
namespace src\Controllers;

use src\Service\UserService;
use src\Utility\UtilityHelper;
use src\Utility\CSRF;
use Throwable;

class UserController{
    private UserService $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    public function register(){
        $json = file_get_contents('php://input');
        $data = json_decode($json, true); 

        $fullName = trim($data['full_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $walletTransactionPin = $data['wallet_transaction_pin']?? '';

        $requiredFields = [
            'full_name' => $fullName, 
            'email' => $email, 
            'password' => $password, 
            'confirm_password' => $confirmPassword,
            'wallet_transaction_pin' => $walletTransactionPin
            ];

            $this->registrationInputValidation($requiredFields);

            $this->userService->registerUser($fullName, $email, $password, $walletTransactionPin);
    }
   
    private function registrationInputValidation(array $fields){

        foreach($fields as $value => $field){
            if(empty($field)){
                UtilityHelper::sendJson('error', "Field $value must not be empty", 400);
            }
        }

        if(!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)){
            UtilityHelper::sendJson('error', "Invalid email address", 400);
        }

        $passwordRequirement = "/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/";
        if(!preg_match($passwordRequirement, $fields['password'])){
            UtilityHelper::sendJson('error',  "Password must be at least 8 characters and include a letter and a number", 400);
        }

        if($fields['password'] !== $fields['confirm_password']){
            UtilityHelper::sendJson('error', "Password does not match", 400);
        }
        
        $this->validateWalletPin($fields['wallet_transaction_pin']);
    }

    private function validateWalletPin($pin){
        $blocked = ['121212', '112233'];

        if(!preg_match('/^\d{6}$/', $pin)){
            UtilityHelper::sendJson('error', "pin must contain 6 digits", 400);
        }

        if (preg_match('/^(\d)\1{5}$/', $pin)) {
            UtilityHelper::sendJson('error', "Repeated digits are not allowed.", 400);
        }

        if (str_contains('0123456789', $pin) || str_contains('09876543210', $pin) || in_array($pin, $blocked, true)) {
            UtilityHelper::sendJson('error', 'PIN must not be sequential or a common pattern', 400);
        }

    }

    public function verifyEmail(){
        $token = trim($_GET['token'] ?? '');

        if(!preg_match('/^[a-f0-9]{64}$/', $token)){
            UtilityHelper::sendJson('error', 'Invalid verification token', 400);
            return;
        }

        $this->userService->verifyEmail($token);
    }

    public function resendVerification(){
        $data = json_decode(file_get_contents('php://input', true));
        $email = trim(strtolower($data['email']));
        
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            UtilityHelper::sendJson('error', "Invalid email address", 400);
        }

        $this->userService->resendEmailVerification($email);
    }

    public function csrfToken(): void{
        UtilityHelper::sendJson('success', 'ok', 200, ['csrf_token' => CSRF::generateCsrfToken()]);
    }

    public function verifyToken(){
        $data = json_decode(file_get_contents('php://input'), true);
        $token = trim($data['token'] ?? '');
        try {     
            if(!preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{6}$/', $token)){
                UtilityHelper::sendJson('error', "Enter a valid token", 400);
            }
            $this->userService->verifyToken($token);
        }catch(Throwable $e){
           error_log("Error verifying token" .$e->getMessage());
           UtilityHelper::sendJson('error', "Error verifying token", 401);
        }
    }

    public function Login(){
        try{
            $data = json_decode(file_get_contents('php://input'), true);
            $csrf = $data['csrf_token'] ?? '';
            $email = strtolower(trim(($data['email'] ?? '')));
            $password = $data['password'] ?? '';
            
            if(!CSRF::verifyCsrfToken($csrf)){
                UtilityHelper::sendJson('error', 'Session expired. Please refresh.', 403);
            }

            if($email === '' || $password === ''){
                UtilityHelper::sendJson('error', "Email and Password must not be empty", 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                UtilityHelper::sendJson('error', 'Invalid email or password', 400);
            }
            
            $ip = $_SERVER['REMOTE_ADDR'];
            $this->userService->userLogin($email, $password, $ip);

        }catch(Throwable $e){
            error_log("Login Validation Error" . $e->getMessage());
            UtilityHelper::sendJson('error', "Unable to process request", 500);
        }
    }

    public function verifyLogin(){
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = strtolower(trim($data['email'] ?? ''));
            $token = trim($data['token'] ?? '');

            if(empty($email) || empty($token)){
                UtilityHelper::sendJson('error', "Missing email or token", 400);
                return;
            }
    
            if(!preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{6}$/', $token)){
                UtilityHelper::sendJson('error', "Enter a valid token", 400);
            }

            $this->userService->verifyLogin($email, $token);
        } catch (\Throwable $th) {
            error_log("Error verifying login token: " . $e->getMessage());
            UtilityHelper::sendJson('error', "Error verifying token", 500);
        }
  
    }

    public function forgotPassword(): void{
        try{
            $data = json_decode(file_get_contents('php://input'), true);
            $email = strtolower(trim($data['email'] ?? ''));

            if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                UtilityHelper::sendJson('error', "Invalid email address", 401);
            }

            $this->userService->forgotPassword($email);
        }catch(Throwable $e){
            error_log('Password-Recovery validation error' . $e->getMessage());
            UtilityHelper::sendJson('error', "Error occurred while retrieving data", 500);
        }

    }

    public function passwordReset(){
        $data = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $token = trim($data['token'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        try {
            if(empty($email) || empty($token)){
                UtilityHelper::sendJson('error', "Missing email or token", 400);
                return;
            }

            if($password === '' || $confirmPassword === ''){
                UtilityHelper::sendJson('error', "Enter your new password", 400);
            }

            if(!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)){
                UtilityHelper::sendJson('error', "Password must be at least 8 characters and include a letter and a number", 500);
            }

            if($password !== $confirmPassword){
                UtilityHelper::sendJson('error', "Password does not match", 403);
            }

            $this->userService->passwordReset($email, $token, $password);
        } catch (Throwable $e) {
            error_log("Password update validation Error:" . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred while processing request", 500);
        }
    }
    

}