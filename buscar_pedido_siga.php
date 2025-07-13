<?php
// Conexión a base de datos SIGA
$sigaConn = sqlsrv_connect("192.168.32.135", array(
    "Database" => "SIGA_1670",
    "Uid" => "fapaza",
    "PWD" => "2780Fach",
    "CharacterSet" => "UTF-8"
));

header('Content-Type: application/json');

$response = ["status" => "error", "datos" => []];

// Obtener el parámetro del expediente SIGA (numérico)
$expediente = isset($_GET['expediente']) ? intval($_GET['expediente']) : 0;

// Log de consola para depuración
error_log("🔍 Recibido expediente SIGA: $expediente");

if ($expediente > 0 && $sigaConn) {
    // 1. Buscar orden de adquisición
    $sqlOrden = "SELECT NRO_ORDEN, TIPO_BIEN, PROVEEDOR, MES_CALEND, CONCEPTO, TOTAL_FACT_SOLES, FECHA_REG
                 FROM SIG_ORDEN_ADQUISICION
                 WHERE ANO_EJE = 2025 AND EXP_SIGA = ?";
    $stmtOrden = sqlsrv_query($sigaConn, $sqlOrden, [$expediente]);

    if ($stmtOrden && ($orden = sqlsrv_fetch_array($stmtOrden, SQLSRV_FETCH_ASSOC))) {
        $nro_orden = $orden['NRO_ORDEN'];
        $tipo_bien = $orden['TIPO_BIEN'];
        $proveedor_id = $orden['PROVEEDOR'];

        // Obtener nombre del proveedor
        $sqlProveedor = "SELECT NOMBRE_PROV FROM SIG_CONTRATISTAS WHERE PROVEEDOR = ?";
        $stmtProv = sqlsrv_query($sigaConn, $sqlProveedor, [$proveedor_id]);
        $nombreProv = null;
        if ($stmtProv && ($prov = sqlsrv_fetch_array($stmtProv, SQLSRV_FETCH_ASSOC))) {
            $nombreProv = $prov['NOMBRE_PROV'];
        }

        error_log("✅ Orden encontrada: NRO_ORDEN=$nro_orden, TIPO_BIEN=$tipo_bien");

        // 2. Buscar ítems asociados a la orden
        $sqlItems = "SELECT GRUPO_BIEN, CLASE_BIEN, FAMILIA_BIEN, ITEM_BIEN
                     FROM SIG_ORDEN_ITEM
                     WHERE ANO_EJE = 2025 AND NRO_ORDEN = ? AND TIPO_BIEN = ?";
        $stmtItems = sqlsrv_query($sigaConn, $sqlItems, [$nro_orden, $tipo_bien]);

        if ($stmtItems) {
            while ($item = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                // 3. Buscar descripción del ítem
                $sqlCat = "SELECT NOMBRE_ITEM, CODIGO_ITEM 
                           FROM CATALOGO_BIEN_SERV
                           WHERE GRUPO_BIEN = ? AND CLASE_BIEN = ? AND FAMILIA_BIEN = ? AND ITEM_BIEN = ? AND TIPO_BIEN = ?";
                $paramsCat = [
                    $item['GRUPO_BIEN'],
                    $item['CLASE_BIEN'],
                    $item['FAMILIA_BIEN'],
                    $item['ITEM_BIEN'],
                    $tipo_bien
                ];

                $stmtCat = sqlsrv_query($sigaConn, $sqlCat, $paramsCat);

                if ($stmtCat) {
                    while ($cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                        // Unir los tres niveles de datos
                        $response['datos'][] = array_merge(
                            $orden, 
                            $item, 
                            $cat,
                            ["NOM_PROVEEDOR" => $nombreProv]
                        );
                    }
                } else {
                    error_log("❌ Error al consultar CATALOGO_BIEN_SERV: " . print_r(sqlsrv_errors(), true));
                }
            }
        } else {
            error_log("❌ Error al consultar SIG_ORDEN_ITEM: " . print_r(sqlsrv_errors(), true));
        }

        if (!empty($response['datos'])) {
            $response['status'] = 'success';
        } else {
            error_log("⚠️ No se encontraron ítems para la orden $nro_orden");
        }
    } else {
        error_log("⚠️ No se encontró orden para expediente SIGA: $expediente");
    }
} else {
    error_log("❌ Conexión fallida o expediente inválido");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
