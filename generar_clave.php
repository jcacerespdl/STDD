<?php
define("SECRET_KEY", "SGD_HEVES");
define("METHOD", "AES-256-CBC");

function encryptPassword($password) {
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($password, METHOD, SECRET_KEY, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

echo encryptPassword("Heves.2025");
