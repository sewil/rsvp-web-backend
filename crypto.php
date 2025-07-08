<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
use Bcrypt\Bcrypt;

function hashPassword(string $password): string {
    $bcrypt = new Bcrypt();
    
    $hashedPassword = $bcrypt->encrypt($password,'2a', 13);
    return $hashedPassword;
}

function verifyPassword(string $password, string $hashedPassword): bool {
    $bcrypt = new Bcrypt();
    return $bcrypt->verify($password, $hashedPassword);
}

function generateToken(array $userData): string {
    // Add a random nonce (prevents token guessing even for same data)
    $userData['nonce'] = bin2hex(random_bytes(16));

    $json = json_encode($userData);

    $iv = random_bytes(16); // AES-256-CBC uses a 16-byte IV
    $cipherText = openssl_encrypt($json, 'aes-256-cbc', AES_KEY, OPENSSL_RAW_DATA, $iv);

    $payload = base64_encode($iv . $cipherText); // Pack IV + ciphertext into one token
    return $payload;
}

function decryptToken(string $token): ?array {
    $decoded = base64_decode($token);
    if ($decoded === false || strlen($decoded) < 17) {
        return null; // Invalid token
    }
    
    $iv = substr($decoded, 0, 16);
    $cipherText = substr($decoded, 16);
    
    $json = openssl_decrypt($cipherText, 'aes-256-cbc', AES_KEY, OPENSSL_RAW_DATA, $iv);
    if ($json === false) {
        return null; // Decryption failed
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function verifyTOTP(string $secret, string $token): bool {
    $otp = new Greymich\TOTP\TOTP($secret);
    return hash_equals( $otp->get(), $token );
}
?>
