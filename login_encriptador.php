<?php
define("SECRET_KEY", "SGD_HEVES"); // Puedes modificarla por una clave más segura y privada
define("METHOD", "AES-256-CBC");

function encryptPassword($password) {
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($password, METHOD, SECRET_KEY, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

function decryptPassword($encrypted) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    return openssl_decrypt($ciphertext, METHOD, SECRET_KEY, 0, $iv);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Encriptador de Claves</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
        h1 { color: #005a86; text-align: center; }
        label, textarea, input { display: block; width: 100%; margin-bottom: 15px; }
        input[type="text"], textarea { padding: 10px; font-size: 16px; }
        button { padding: 10px 20px; background-color: #005a86; color: white; border: none; font-size: 16px; cursor: pointer; }
        button:hover { background-color: #004568; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Encriptador de Claves (AES-256)</h1>
        <form method="POST">
            <label for="clave">Ingrese la clave a encriptar:</label>
            <input type="text" name="clave" id="clave" required>
            <button type="submit">Encriptar</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['clave'])) {
            $clave = $_POST['clave'];
            $encriptada = encryptPassword($clave);
            $desencriptada = decryptPassword($encriptada);
            echo "<h3>Resultado:</h3>";
            echo "<label>Clave original:</label><textarea readonly>$clave</textarea>";
            echo "<label>Clave encriptada (guardar en BD):</label><textarea readonly>$encriptada</textarea>";
            echo "<label>Prueba de desencriptación:</label><textarea readonly>$desencriptada</textarea>";
        }
        ?>
    </div>
</body>
</html>
