<?php
date_default_timezone_set('America/Lima');

define('ENCRYPTION_KEY', 'Heves');
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('FIXED_IV', substr(hash('sha256', 'Heves-IV'), 0, 16));

function encrypt_password($password) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = FIXED_IV;
    $encrypted = openssl_encrypt($password, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($encrypted);
}

session_start();
if (empty($_POST['usuario']) || empty($_POST['contrasena'])) {
    header("Location: index.php?alter=3"); // Datos vacíos
    exit();
}

include_once("conexion/conexion.php");

$usuario = trim($_POST['usuario']);
$claveOriginal = trim($_POST['contrasena']);
$hashAntiguo = md5($usuario . $claveOriginal);

// Primero obtenemos los datos del usuario para saber qué método de validación usar
$sqlCheck = "SELECT * FROM Tra_M_Trabajadores WHERE cUsuario = ?";
$rsCheck = sqlsrv_query($cnx, $sqlCheck, [$usuario], ["Scrollable" => SQLSRV_CURSOR_CLIENT_BUFFERED]);

if (sqlsrv_num_rows($rsCheck) === 0) {
    header("Location: index.php?alter=4"); // Usuario no existe
    exit();
}

$userRow = sqlsrv_fetch_array($rsCheck, SQLSRV_FETCH_ASSOC);
$nEstadoClave = $userRow['nEstadoClave'];
$nFlgEstado = $userRow['nFlgEstado'];
$cPasswordBD = $userRow['cPassword'];
$iCodTrabajador = $userRow['iCodTrabajador'];

$claveValida = false;
$claveComparada = '';

 
    $claveAES = encrypt_password($claveOriginal);
    if ($cPasswordBD === $claveAES) {
        $claveValida = true;
        $claveComparada = $claveAES;
    }
 

if (!$claveValida) {
    header("Location: index.php?alter=4"); // Clave incorrecta
    exit();
}

if ($nFlgEstado != 1) {
    header("Location: index.php?alter=5"); // Usuario inactivo
    exit();
}

// Traer perfil de oficina y jefe
$sqlPerfil = "SELECT TOP 1 TPU.iCodPerfil, TPU.iCodOficina FROM Tra_M_Perfil_Ususario TPU WHERE TPU.iCodTrabajador = ?";
$resPerfil = sqlsrv_query($cnx, [$sqlPerfil], [$iCodTrabajador]);
$rowPerfil = sqlsrv_fetch_array($resPerfil, SQLSRV_FETCH_ASSOC);

// Traer jefe
$sqlJefe = "SELECT iCodTrabajador FROM Tra_M_Perfil_Ususario WHERE iCodPerfil = 3 AND iCodOficina = ?";
$resJefe = sqlsrv_query($cnx, [$sqlJefe], [$rowPerfil['iCodOficina']]);
$rowJefe = sqlsrv_fetch_array($resJefe, SQLSRV_FETCH_ASSOC);

// Iniciar sesión
session_regenerate_id(true);
$fechaActual = date("Y-m-d H:i:s");
$fechaToken = date("Ymd-Gis");

$_SESSION['fUltimoAcceso'] = $userRow["fUltimoAcceso"];
$_SESSION['iCodOficinaLogin'] = $rowPerfil["iCodOficina"];
$_SESSION['iCodPerfilLogin'] = $rowPerfil["iCodPerfil"];
$_SESSION['CODIGO_TRABAJADOR'] = $iCodTrabajador;
$_SESSION['JEFE'] = $rowJefe["iCodTrabajador"] ?? null;
$_SESSION['cCodRef'] = $iCodTrabajador . "-" . $rowPerfil["iCodOficina"] . "-" . $fechaToken;
$_SESSION['cCodOfi'] = $_SESSION['cCodRef'];
$_SESSION['cCodDerivo'] = $_SESSION['cCodRef'];
$_SESSION['siteURL'] = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];

// Actualizar último acceso
$sqlUpd = "UPDATE Tra_M_Trabajadores SET fUltimoAcceso = CONVERT(datetime, ?, 121) WHERE cUsuario = ? AND cPassword = ?";
sqlsrv_query($cnx, $sqlUpd, [$fechaActual, $usuario, $claveComparada]);

// ¿Debe cambiar clave?
if ($userRow['nFlgPasswordMod'] == 1 || $nEstadoClave == 0) {
    $_SESSION['FORZAR_CAMBIO_CLAVE'] = true;
    $_SESSION['USUARIO_CAMBIO_CLAVE'] = $iCodTrabajador;
    header("Location: cambiar_clave.php");
    exit();
}

header("Location: index2.php");
exit();
