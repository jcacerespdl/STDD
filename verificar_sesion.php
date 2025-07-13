<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'activa' => isset($_SESSION['CODIGO_TRABAJADOR'])
]);
