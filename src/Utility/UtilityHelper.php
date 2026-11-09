<?php
namespace src\Utility;

class UtilityHelper{
    
    public static function sendJson($status, $message,  $code, $extra=[]){
        http_response_code($code);
        $response = array_merge([
            "status" => $status,
            "message" => $message
        ], $extra);
        echo json_encode($response);
        exit();
    }

    public static function hashValue(string $value){
        return password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyValue(string $value, string $hash){
        return password_verify($value,  $hash);
    }
    
    public static function generateWalletTag(string $walletName): string{
        $name = preg_replace('/[^A-Za-z]/', '', $walletName);

        $prefix = strtoUpper(substr($name, 0, 3));
        if(strlen($prefix < 3)){
            $prefix = str_pad('$prefix', 3, 'X');
        }

        $randomNumber = random_int(000000, 999999);

        $walletTag = $prefix . $randomNumber;

        return $walletTag;
    }
    
}