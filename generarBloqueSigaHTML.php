<?php
include_once("conexion/conexion.php");
header("Content-Type: text/html; charset=utf-8");

$iCodTramite = $_POST['iCodTramite'] ?? null;
if (!$iCodTramite) {
    http_response_code(400);
    echo "Código de trámite no válido.";
    exit;
}

$sigaItems = [];

$sql = "SELECT pedido_siga, codigo_item, cantidad, stock, consumo_promedio, meses_consumo, situacion
        FROM Tra_M_Tramite_SIGA_Pedido
        WHERE iCodTramite = ?";
$stmt = sqlsrv_query($cnx, $sql, [$iCodTramite]);

if (!$stmt) {
    echo '<p>Error al consultar ítems SIGA.</p>';
    exit;
}

global $sigaConn;
include_once("conexion/conexion.php"); // para $sigaConn también

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $pedidoSiga = $row['pedido_siga'];
    $codigoItem = $row['codigo_item'];
    $nombreItem = 'N.A.';

    if ($pedidoSiga) {
        $sqlOrden = "SELECT EXP_SIGA, NRO_ORDEN, TIPO_BIEN
                     FROM SIG_ORDEN_ADQUISICION
                     WHERE ANO_EJE = 2025 AND EXP_SIGA = ?";
        $stmtOrden = sqlsrv_query($sigaConn, $sqlOrden, [$pedidoSiga]);

        if ($stmtOrden) {
            while ($orden = sqlsrv_fetch_array($stmtOrden, SQLSRV_FETCH_ASSOC)) {
                $sqlItems = "SELECT GRUPO_BIEN, CLASE_BIEN, FAMILIA_BIEN, ITEM_BIEN
                             FROM SIG_ORDEN_ITEM
                             WHERE ANO_EJE = 2025 AND NRO_ORDEN = ? AND TIPO_BIEN = ?";
                $stmtItems = sqlsrv_query($sigaConn, $sqlItems, [$orden['NRO_ORDEN'], $orden['TIPO_BIEN']]);

                if ($stmtItems) {
                    while ($item = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                        $sqlCat = "SELECT CODIGO_ITEM, NOMBRE_ITEM
                                   FROM CATALOGO_BIEN_SERV
                                   WHERE GRUPO_BIEN = ? AND CLASE_BIEN = ? AND FAMILIA_BIEN = ? AND ITEM_BIEN = ? AND TIPO_BIEN = ?";
                        $stmtCat = sqlsrv_query($sigaConn, $sqlCat, [
                            $item['GRUPO_BIEN'],
                            $item['CLASE_BIEN'],
                            $item['FAMILIA_BIEN'],
                            $item['ITEM_BIEN'],
                            $orden['TIPO_BIEN']
                        ]);

                        if ($stmtCat) {
                            while ($cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                                if ($cat['CODIGO_ITEM'] === $codigoItem) {
                                    $nombreItem = $cat['NOMBRE_ITEM'];
                                    break 3; // salir de los 3 bucles anidados
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        // Ítem sin pedido SIGA → buscar por código
        $sqlCat = "SELECT NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?";
        $stmtCat = sqlsrv_query($sigaConn, $sqlCat, [$codigoItem]);
        if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
            $nombreItem = $cat['NOMBRE_ITEM'];
        }
    }

    $sigaItems[] = [
        'codigo_item' => $codigoItem,
        'nombre_item' => $nombreItem,
        'cantidad' => $row['cantidad'],
        'stock' => $row['stock'],
        'consumo_promedio' => $row['consumo_promedio'],
        'meses_consumo' => $row['meses_consumo'],
        'situacion' => $row['situacion']
    ];
}

// Generar bloque HTML
$bloque = '<p style="font-size: 10px;">Me dirijo a Ud. para saludarlo cordialmente y en atención al asunto hacerle llegar </p>';

if (empty($sigaItems)) {
    $bloque .= '<p><em>No se encontraron ítems SIGA.</em></p>';
    echo $bloque;
    exit;
}

$bloque .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-size: 8px; width: 100%; margin-top: 10px;">';
$bloque .= '<thead style="background-color: #f2f2f2; font-weight: bold;">
    <tr>
         <th style="text-align: center; font-weight: bold; width: 4%;">N°</th>
         <th style="text-align: center; font-weight: bold; width: 16%;">CÓDIGO SIGA</th>
         <th style="text-align: center; font-weight: bold; width: 20%;">ITEMS</th>
         <th style="text-align: center; font-weight: bold; width: 11%;">CANTIDAD</th>
         <th style="text-align: center; font-weight: bold; width: 9%;">STOCK</th>
         <th style="text-align: center; font-weight: bold;">CONSUMO PROMEDIO</th>
         <th style="text-align: center; font-weight: bold; width: 15%;">MESES DE CONSUMO</th>
         <th style="text-align: center; font-weight: bold; width: 15%;">SITUACIÓN</th>
    </tr>
</thead><tbody>';
$n = 1;
foreach ($sigaItems as $item) {
    $bloque .= '<tr>';
    $bloque .= '<td style="text-align: center; width: 4%;">' . $n++ . '</td>';
    $bloque .= '<td style="text-align: center; width: 16%;">' . htmlspecialchars($item['codigo_item']) . '</td>';
    $bloque .= '<td style="text-align: center; width: 20%;">' . htmlspecialchars($item['nombre_item']) . '</td>';
    $bloque .= '<td style="text-align: center; width: 11%;">' . (int)$item['cantidad'] . '</td>';
    $bloque .= '<td style="text-align: center; width: 9%;">' . (int)$item['stock'] . '</td>';
    $bloque .= '<td style="text-align: center;">' . (float)$item['consumo_promedio'] . '</td>';
    $bloque .= '<td style="text-align: center; width: 15%;">' . (float)$item['meses_consumo'] . '</td>';
    $bloque .= '<td style="text-align: center; width: 15%;">' . htmlspecialchars($item['situacion']) . '</td>';
    $bloque .= '</tr>';
}

$bloque .= '</tbody></table>';
echo $bloque;
