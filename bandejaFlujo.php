<?php
include("headFlujo.php");
include("conexion/conexion.php");

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;
$extension = isset($_GET['extension']) ? intval($_GET['extension']) : 1;

// Obtener datos del trámite original
$sqlTramite = "SELECT 
    t.expediente, t.cCodificacion, t.cAsunto, t.fFecRegistro, t.cObservaciones, t.documentoElectronico, t.cCodTipoDoc,
    td.cDescTipoDoc, td.cCodTipoDoc, o.cSiglaOficina
 FROM Tra_M_Tramite t
 JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
 JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
 WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
$infoInicial = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);

// Fecha de creación de la extensión
$fFecCreacionExt = null;
if ($extension > 1) {
    $stmtExt = sqlsrv_query($cnx,
        "SELECT TOP 1 fFecCreacion FROM Tra_M_Tramite_Extension WHERE iCodTramite = ? AND nro_extension = ?",
        [$iCodTramite, $extension]);
    if ($r = sqlsrv_fetch_array($stmtExt, SQLSRV_FETCH_ASSOC)) {
        $fFecCreacionExt = $r['fFecCreacion'];
    }
}
// Función para obtener información de documentos de un trámite
function obtenerDatosTramite($cnx, $iCodTramite) {
    $doc = ['principal'=>null,'codificacion'=>null,'asunto'=>null,'fecha'=>null,'anexos'=>[]];
    $stmt = sqlsrv_query($cnx, "SELECT t.documentoElectronico, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.fFecRegistro 
        FROM Tra_M_Tramite t 
        JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc 
        WHERE t.iCodTramite = ?", [$iCodTramite]);
    if ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $doc['principal'] = $r['documentoElectronico'];
        $doc['codificacion'] = "{$r['cDescTipoDoc']} Nº " . str_pad($r['cCodificacion'], 5, '0', STR_PAD_LEFT);
        $doc['asunto'] = $r['cAsunto'];
        $doc['fecha'] = $r['fFecRegistro'] ? $r['fFecRegistro']->format("d/m/Y H:i:s") : '';
        $doc['tipo'] = $r['cCodTipoDoc'];
    }
    $stmtAnexos = sqlsrv_query($cnx, "SELECT cDescripcion FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ?", [$iCodTramite]);
    while ($r = sqlsrv_fetch_array($stmtAnexos, SQLSRV_FETCH_ASSOC)) {
        $doc['anexos'][] = $r['cDescripcion'];
    }
    return $doc;
}
// === CONSULTA MOVIMIENTOS CON CONDICIÓN PARA MOSTRAR EXTENSIÓN 1 SI ES ANTERIOR ===
$parametrosMov = [$iCodTramite];
// Obtener solo los movimientos de esta extensión
$sqlMov = "SELECT 
    M.iCodTramiteDerivar, 
    M.iCodMovimiento,
    M.iCodMovimientoDerivo,
     ISNULL(M.fFecDerivar, GETDATE()) AS fFecDerivar,
     ISNULL(M.fFecRecepcion, GETDATE()) AS fFecRecepcion,
    M.cAsuntoDerivar, 
    M.cObservacionesDerivar, 
    M.cPrioridadDerivar, 
    M.nEstadoMovimiento,
    M.fFecDelegado,
    M.fFecDelegadoRecepcion,
    M.iCodTrabajadorDelegado,
    M.iCodIndicacionDelegado,
    M.cObservacionesDelegado,
 -- JOINS
    O1.cNomOficina AS OficinaOrigen,
     O1.cSiglaOficina AS OficinaOrigenAbbr,
    O2.cNomOficina AS OficinaDestino,
     O2.cSiglaOficina AS OficinaDestinoAbbr,
    T.fase,
     -- Nombre del jefe destino
    (SELECT TOP 1 T2.cNombresTrabajador + ' ' + T2.cApellidosTrabajador 
     FROM Tra_M_Perfil_ususario PU
     INNER JOIN Tra_M_Trabajadores T2 ON PU.iCodTrabajador = T2.iCodTrabajador
     WHERE PU.iCodOficina = M.iCodOficinaDerivar AND PU.iCodPerfil = 3
     ORDER BY T2.iCodTrabajador ASC) AS JefeDestino,
         -- Nombre del delegado
    (SELECT TOP 1 T3.cNombresTrabajador + ' ' + T3.cApellidosTrabajador 
     FROM Tra_M_Trabajadores T3 WHERE T3.iCodTrabajador = M.iCodTrabajadorDelegado) AS NombreDelegado,
    -- Indicacion textual
    (SELECT I.cIndicacion FROM Tra_M_Indicaciones I WHERE I.iCodIndicacion = M.iCodIndicacionDelegado) AS cIndicacionDelegado
 FROM Tra_M_Tramite_Movimientos M
 LEFT JOIN Tra_M_Oficinas O1 ON M.iCodOficinaOrigen = O1.iCodOficina
 LEFT JOIN Tra_M_Oficinas O2 ON M.iCodOficinaDerivar = O2.iCodOficina
 LEFT JOIN Tra_M_Tramite T ON T.iCodTramite = M.iCodTramiteDerivar
 WHERE M.iCodTramite = ?";
 // Condicional para mostrar movimientos anteriores de extensión 1 si aplica
if ($extension > 1 && $fFecCreacionExt instanceof DateTime) {
    $sqlMov .= " AND (M.extension = ? OR (M.extension = 1 AND ISNULL(M.fFecDerivar, GETDATE()) < ?))";
    $parametrosMov[] = $extension;
    $parametrosMov[] = $fFecCreacionExt;
} else {
    $sqlMov .= " AND M.extension = ?";
    $parametrosMov[] = $extension;
}
$sqlMov .= " ORDER BY ISNULL(M.fFecDerivar, GETDATE()) ASC";
$stmtMov = sqlsrv_query($cnx, $sqlMov, $parametrosMov);
$movimientos = [];
while ($row = sqlsrv_fetch_array($stmtMov, SQLSRV_FETCH_ASSOC)) {
    $movimientos[] = $row;
}
$movimientos = array_reverse($movimientos);
$ultimaClave = count($movimientos) - 1;

// Función para obtener detalles de documentos
 
// === NUEVO: Buscar TODOS los iCodTramite derivados desde el trámite base ===
$iCodTramitesBuscar = [$iCodTramite];

$sqlDerivados = "SELECT iCodTramiteDerivar FROM Tra_M_Tramite_Movimientos WHERE iCodTramite = ?";
$stmtDerivados = sqlsrv_query($cnx, $sqlDerivados, [$iCodTramite]);
while ($row = sqlsrv_fetch_array($stmtDerivados, SQLSRV_FETCH_ASSOC)) {
    if (!empty($row['iCodTramiteDerivar'])) {
        $iCodTramitesBuscar[] = $row['iCodTramiteDerivar'];
    }
}


// === NUEVO: Recorremos todos esos trámites buscando ítems SIGA (con o sin pedido_siga) ===
$itemsSIGA = [];

if ((int)$extension === 1) {
    $sqlDerivados = "SELECT iCodTramiteDerivar 
                     FROM Tra_M_Tramite_Movimientos 
                     WHERE iCodTramite = ? AND extension = 1 AND iCodTramiteDerivar IS NOT NULL";
    $stmtDerivados = sqlsrv_query($cnx, $sqlDerivados, [$iCodTramite]);

    $tramitesBusqueda = [];
    while ($r = sqlsrv_fetch_array($stmtDerivados, SQLSRV_FETCH_ASSOC)) {
        $tramitesBusqueda[] = $r['iCodTramiteDerivar'];
    }

    if (empty($tramitesBusqueda)) {
        $tramitesBusqueda[] = $iCodTramite;
    }

    foreach ($tramitesBusqueda as $tramiteSIGA) {
        $sqlSIGA = "SELECT pedido_siga, codigo_item, cantidad 
                    FROM Tra_M_Tramite_SIGA_Pedido 
                    WHERE iCodTramite = ?";
        $stmtSIGA = sqlsrv_query($cnx, $sqlSIGA, [$tramiteSIGA]);

        while ($pedido = sqlsrv_fetch_array($stmtSIGA, SQLSRV_FETCH_ASSOC)) {
            $pedidoSiga = $pedido['pedido_siga'];
            $codigoItem = $pedido['codigo_item'];
            $cantidad = $pedido['cantidad'];

            if ($pedidoSiga) {
                $stmtOrden = sqlsrv_query($sigaConn,
                    "SELECT NRO_ORDEN, TIPO_BIEN, PROVEEDOR, MES_CALEND, CONCEPTO, TOTAL_FACT_SOLES, FECHA_REG
                     FROM SIG_ORDEN_ADQUISICION WHERE ANO_EJE = 2025 AND EXP_SIGA = ?", [$pedidoSiga]);

                if ($stmtOrden) {
                    while ($orden = sqlsrv_fetch_array($stmtOrden, SQLSRV_FETCH_ASSOC)) {
                        $stmtItems = sqlsrv_query($sigaConn,
                            "SELECT GRUPO_BIEN, CLASE_BIEN, FAMILIA_BIEN, ITEM_BIEN
                             FROM SIG_ORDEN_ITEM WHERE ANO_EJE = 2025 AND NRO_ORDEN = ? AND TIPO_BIEN = ?",
                            [$orden['NRO_ORDEN'], $orden['TIPO_BIEN']]);

                        while ($item = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                            $stmtCat = sqlsrv_query($sigaConn,
                                "SELECT CODIGO_ITEM, NOMBRE_ITEM FROM CATALOGO_BIEN_SERV
                                 WHERE GRUPO_BIEN = ? AND CLASE_BIEN = ? AND FAMILIA_BIEN = ? AND ITEM_BIEN = ? AND TIPO_BIEN = ?",
                                [$item['GRUPO_BIEN'], $item['CLASE_BIEN'], $item['FAMILIA_BIEN'], $item['ITEM_BIEN'], $orden['TIPO_BIEN']]);

                            while ($cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                                $itemsSIGA[] = [
                                    "pedido_siga" => $pedidoSiga,
                                    "NRO_ORDEN" => $orden['NRO_ORDEN'] ?? 'N.A.',
                                    "TIPO_BIEN" => $orden['TIPO_BIEN'] ?? 'N.A.',
                                    "PROVEEDOR" => $orden['PROVEEDOR'] ?? 'N.A.',
                                    "MES" => $orden['MES_CALEND'] ?? 'N.A.',
                                    "CONCEPTO" => $orden['CONCEPTO'] ?? 'N.A.',
                                    "TOTAL" => $orden['TOTAL_FACT_SOLES'] ?? 'N.A.',
                                    "FECHA" => ($orden['FECHA_REG'] instanceof DateTime) ? $orden['FECHA_REG']->format('d/m/Y') : 'N.A.',
                                    "CODIGO_ITEM" => $cat['CODIGO_ITEM'],
                                    "CANTIDAD" => $cantidad,
                                    "NOMBRE_ITEM" => $cat['NOMBRE_ITEM']
                                ];
                            }
                        }
                    }
                }
            } else {
                $stmtCat = sqlsrv_query($sigaConn,
                    "SELECT NOMBRE_ITEM, TIPO_BIEN FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?",
                    [$codigoItem]);

                if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                    $itemsSIGA[] = [
                        "pedido_siga" => "N.A.",
                        "NRO_ORDEN" => "N.A.",
                        "TIPO_BIEN" => $cat['TIPO_BIEN'] ?? 'N.A.',
                        "PROVEEDOR" => "N.A.",
                        "MES" => "N.A.",
                        "CONCEPTO" => "N.A.",
                        "TOTAL" => "N.A.",
                        "FECHA" => "N.A.",
                        "CODIGO_ITEM" => $codigoItem,
                        "CANTIDAD" => $cantidad,
                        "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'] ?? 'N.A.'
                    ];
                }
            }
        }
    }
} else {

$sqlSIGA = "SELECT P.pedido_siga, P.codigo_item, P.cantidad 
            FROM Tra_M_Tramite_Extension E
            JOIN Tra_M_Tramite_SIGA_Pedido P ON E.iCodTramiteSIGAPedido = P.iCodTramiteSIGAPedido
            WHERE E.iCodTramite = ? AND E.nro_extension = ?";
$stmtSIGA = sqlsrv_query($cnx, $sqlSIGA, [$iCodTramite, $extension]);

while ($pedido = sqlsrv_fetch_array($stmtSIGA, SQLSRV_FETCH_ASSOC)) {
    $pedidoSiga = $pedido['pedido_siga'];
    $codigoItem = $pedido['codigo_item'];
    $cantidad = $pedido['cantidad'];

    if ($pedidoSiga) {
        $stmtOrden = sqlsrv_query($sigaConn,
            "SELECT NRO_ORDEN, TIPO_BIEN, PROVEEDOR, MES_CALEND, CONCEPTO, TOTAL_FACT_SOLES, FECHA_REG
             FROM SIG_ORDEN_ADQUISICION WHERE ANO_EJE = 2025 AND EXP_SIGA = ?", [$pedidoSiga]);

        if ($stmtOrden) {
            while ($orden = sqlsrv_fetch_array($stmtOrden, SQLSRV_FETCH_ASSOC)) {
                $stmtItems = sqlsrv_query($sigaConn,
                    "SELECT GRUPO_BIEN, CLASE_BIEN, FAMILIA_BIEN, ITEM_BIEN
                     FROM SIG_ORDEN_ITEM WHERE ANO_EJE = 2025 AND NRO_ORDEN = ? AND TIPO_BIEN = ?",
                    [$orden['NRO_ORDEN'], $orden['TIPO_BIEN']]);

                while ($item = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                    $stmtCat = sqlsrv_query($sigaConn,
                        "SELECT CODIGO_ITEM, NOMBRE_ITEM FROM CATALOGO_BIEN_SERV
                         WHERE GRUPO_BIEN = ? AND CLASE_BIEN = ? AND FAMILIA_BIEN = ? AND ITEM_BIEN = ? AND TIPO_BIEN = ?",
                        [$item['GRUPO_BIEN'], $item['CLASE_BIEN'], $item['FAMILIA_BIEN'], $item['ITEM_BIEN'], $orden['TIPO_BIEN']]);

                    while ($cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                        $itemsSIGA[] = [
                            "pedido_siga" => $pedidoSiga,
                            "NRO_ORDEN" => $orden['NRO_ORDEN'] ?? 'N.A.',
                            "TIPO_BIEN" => $orden['TIPO_BIEN'] ?? 'N.A.',
                            "PROVEEDOR" => $orden['PROVEEDOR'] ?? 'N.A.',
                            "MES" => $orden['MES_CALEND'] ?? 'N.A.',
                            "CONCEPTO" => $orden['CONCEPTO'] ?? 'N.A.',
                            "TOTAL" => $orden['TOTAL_FACT_SOLES'] ?? 'N.A.',
                            "FECHA" => ($orden['FECHA_REG'] instanceof DateTime) ? $orden['FECHA_REG']->format('d/m/Y') : 'N.A.',
                            "CODIGO_ITEM" => $cat['CODIGO_ITEM'],
                            "CANTIDAD" => $cantidad,
                            "NOMBRE_ITEM" => $cat['NOMBRE_ITEM']
                        ];
                    }
                }
            }
        }
    } else {
        $stmtCat = sqlsrv_query($sigaConn,
            "SELECT NOMBRE_ITEM, TIPO_BIEN FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?",
            [$codigoItem]);

        if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
            $itemsSIGA[] = [
                "pedido_siga" => "N.A.",
                "NRO_ORDEN" => "N.A.",
                "TIPO_BIEN" => $cat['TIPO_BIEN'] ?? 'N.A.',
                "PROVEEDOR" => "N.A.",
                "MES" => "N.A.",
                "CONCEPTO" => "N.A.",
                "TOTAL" => "N.A.",
                "FECHA" => "N.A.",
                "CODIGO_ITEM" => $codigoItem,
                "CANTIDAD" => $cantidad,
                "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'] ?? 'N.A.'
            ];
        }
    }
}
}
    
?>

<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
 
<!-- Estilos visuales Gmail-like -->
<style>
.chip-adjunto {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    border-radius: 999px;
    padding: 6px 12px;
    margin: 4px 6px 4px 0;
    font-size: 13px;
    font-family: 'Segoe UI', sans-serif;
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    border: 1px solid #dadce0;
    transition: background 0.2s;
    text-decoration: none;
    color: black;
}
.chip-adjunto:hover {
    background-color: #e8eaed;
    text-decoration: none;
}
.material-icons.chip-icon {
    font-size: 18px;
    margin-right: 8px;
    vertical-align: middle;
    color: #d93025;
}
.material-icons.chip-doc {
    color: #1a73e8;
}
.chip-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    max-width: 180px;
}
</style>

<!-- DATOS GENERALES -->
<h3>DETALLE DE MOVIMIENTOS</h3>
<details class="detail-content" open>
<summary>DATOS GENERALES</summary>
<div style="padding: 1rem;">
    <div><b>Expediente:</b> <?= htmlspecialchars($infoInicial['expediente']) ?></div>
    <div><b>Extensión:</b> <?= $extension ?></div>
    <!-- <div><b>Fecha de creación de la extensión:</b> 
        <?php if ($extension > 1 && $infoExtension && $infoExtension['fFecCreacion'] instanceof DateTime): ?>
            <?= $infoExtension['fFecCreacion']->format("d/m/Y H:i:s") ?>
        <?php else: ?>
            <?= $infoInicial['fFecRegistro']->format("d/m/Y H:i:s") ?>
        <?php endif; ?>
    </div> -->
    <div><b>Tipo de Documento:</b> <?= $infoInicial['cDescTipoDoc'] ?></div>
    <div><b>Asunto:</b> <?= $infoInicial['cAsunto'] ?></div>
    <div><b>Fecha Registro:</b> <?= $infoInicial['fFecRegistro']->format("d/m/Y H:i:s") ?></div>
    <div><b>Observaciones:</b> <?= $infoInicial['cObservaciones'] ?></div>
    <div><b>Doc. Principal:</b> 
    <?php if (!empty($infoInicial['documentoElectronico'])): ?>
        <a href="./cDocumentosFirmados/<?= urlencode($infoInicial['documentoElectronico']) ?>" class="chip-adjunto" target="_blank" title="<?= htmlspecialchars($infoInicial['documentoElectronico']) ?>">
        <span class="material-icons chip-icon">picture_as_pdf</span>
        <span class="chip-text"><?= htmlspecialchars($infoInicial['documentoElectronico']) ?></span>
        </a>
    <?php else: ?>
        <span>No disponible</span>
    <?php endif; ?>
    </div>

    <?php if (!empty($itemsSIGA)): ?>
    <div style="margin-top: 1rem;">
        <h3>Ítems SIGA Asociados</h3>
        <table style="width:100%; border-collapse: collapse; font-size: 14px;">
            <thead style="background:#f5f5f5;">
                <tr>
                    <th>PEDIDO SIGA</th><th>N° ORDEN</th><th>TIPO BIEN</th><th>PROVEEDOR</th><th>MES</th>
                    <th>CONCEPTO</th><th>TOTAL</th><th>FECHA</th><th>CÓDIGO ITEM</th><th>NOMBRE ITEM</th><th>CANTIDAD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itemsSIGA as $item): ?>
                <tr>
                    <td><?= $item['pedido_siga'] ?></td>
                    <td><?= $item['NRO_ORDEN'] ?></td>
                    <td><?= $item['TIPO_BIEN'] === 'S' ? 'SERVICIO' : 'BIEN' ?></td>
                    <td><?= $item['PROVEEDOR'] ?></td>
                    <td><?= $item['MES'] ?></td>
                    <td><?= $item['CONCEPTO'] ?></td>
                    <td><?= $item['TOTAL'] ?></td>
                    <td><?= $item['FECHA'] ?></td>
                    <td><?= $item['CODIGO_ITEM'] ?></td>
                    <td><?= $item['NOMBRE_ITEM'] ?></td>
                    <td><?= $item['CANTIDAD'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
// === FUNCIONES PARA ÁRBOL DE MOVIMIENTOS ===
function construirArbolMovimientos($movimientos) {
    $movPorId = [];
    foreach ($movimientos as $mov) {
        $mov['hijos'] = [];
        $movPorId[$mov['iCodMovimiento']] = $mov;
    }

    $arbol = [];
    foreach ($movPorId as $id => &$mov) {
        $padreId = $mov['iCodMovimientoDerivo'] ?? null;
        if ($padreId && isset($movPorId[$padreId])) {
            $movPorId[$padreId]['hijos'][] = &$mov;
        } else {
            $arbol[] = &$mov;
        }
    }

    return $arbol;
}

function renderizarMovimiento($mov, $nivel = 0) {
    $doc = obtenerDatosTramite($GLOBALS['cnx'], $mov["iCodTramiteDerivar"] ?: $GLOBALS['iCodTramite']);
    $sangria = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $nivel); // 4 espacios por nivel

    echo "<div style='margin-top: 10px;'>";
        echo "<details>";
        echo "<summary style='font-weight:bold; cursor:pointer; padding-left: " . ($nivel * 20) . "px;'>{$mov['OficinaOrigenAbbr']} → {$mov['OficinaDestinoAbbr']} - {$doc['codificacion']}</summary>";
    
    echo "<div style='padding-left: 1rem;'>";

    echo "<div><b>Asunto:</b> " . htmlspecialchars($doc['asunto']) . "</div>";
    echo "<div><b>Prioridad:</b> " . htmlspecialchars($mov['cPrioridadDerivar']) . "</div>";

    if (!empty($mov['fase'])) {
        $fases = [
            0 => "No Corresponde",
            1 => "Indagación",
            2 => "Validación",
            3 => "Reformulación",
            4 => "Disponibilidad Presupuestal",
            5 => "Notificación"
        ];
        echo "<div><b>Fase:</b> " . ($fases[$mov['fase']] ?? 'No definida') . "</div>";
    }

    echo "<div><b>Fecha de Envío:</b> " . ($mov['fFecDerivar'] instanceof DateTime ? $mov['fFecDerivar']->format("d/m/Y H:i:s") : 'N/A') . "</div>";
    echo "<div><b>Fecha de Recepción:</b> " . ($mov['fFecRecepcion'] instanceof DateTime ? $mov['fFecRecepcion']->format("d/m/Y H:i:s") : '—') . "</div>";    echo "<div><b>Dirigido a:</b> " . htmlspecialchars($mov["JefeDestino"]) . "</div>";
    $estadoTexto = 'Enviado';
if ($mov['nEstadoMovimiento'] == 0) {
    $estadoTexto = 'Sin aceptar';
} elseif ($mov['nEstadoMovimiento'] == 1) {
    $estadoTexto = 'Recibido';
} elseif ($mov['nEstadoMovimiento'] == 3) {
    $estadoTexto = 'Delegado';
} elseif ($mov['nEstadoMovimiento'] == 5) {
    $estadoTexto = 'Finalizado';
}
    echo "<div><b>Estado:</b> " . $estadoTexto . "</div>";

    if (!empty($mov['iCodTrabajadorDelegado']) || !empty($mov['iCodIndicacionDelegado']) || !empty($mov['cObservacionesDelegado'])) {
        echo "<hr>";
        echo "<div><b>Delegado a:</b> " . htmlspecialchars($mov["NombreDelegado"] ?? 'N/A') . "</div>";
        if (!empty($mov["cIndicacionDelegado"])) {
            echo "<div><b>Indicación / Fase:</b> " . htmlspecialchars($mov["cIndicacionDelegado"]) . "</div>";
        }
        if (!empty($mov["cObservacionesDelegado"])) {
            echo "<div><b>Observaciones:</b> " . htmlspecialchars($mov["cObservacionesDelegado"]) . "</div>";
        }
        if (!empty($mov["fFecDelegado"]) && $mov["fFecDelegado"] instanceof DateTime) {
            echo "<div><b>Fecha de Delegación:</b> " . $mov["fFecDelegado"]->format("d/m/Y H:i:s") . "</div>";
        }
        if (!empty($mov["fFecDelegadoRecepción"]) && $mov["fFecDelegadoRecepción"] instanceof DateTime) {
            echo "<div><b>Recepción Delegación:</b> " . $mov["fFecDelegadoRecepción"]->format("d/m/Y H:i:s") . "</div>";
        }
    }

    echo "<div><b>Documento principal:</b><br>";
    if ($doc['tipo'] === '97') {
        // No mostrar nada
    } elseif (!empty($doc['principal'])) {
        echo "<a href='./cDocumentosFirmados/" . urlencode($doc['principal']) . "' class='chip-adjunto' target='_blank'>";
        echo "<span class='material-icons chip-icon'>picture_as_pdf</span><span class='chip-text'>" . htmlspecialchars($doc['principal']) . "</span></a>";
    } else {
        echo "<span style='color:#888;'>No hay documento principal</span>";
    }
    echo "</div>";

    echo "<div><b>Documentos complementarios:</b><br>";
    if (!empty($doc['anexos'])) {
        foreach ($doc['anexos'] as $anexo) {
            echo "<a href='./cAlmacenArchivos/" . urlencode($anexo) . "' class='chip-adjunto' target='_blank'>";
            echo "<span class='material-icons chip-icon chip-doc'>article</span><span class='chip-text'>" . htmlspecialchars($anexo) . "</span></a> ";
        }
    } else {
        echo "<span style='color: #888;'>No hay documentos complementarios</span>";
    }
    echo "</div>";

    echo "</div>"; // cierre detalles internos
    echo "</details>";

    foreach ($mov['hijos'] as $hijo) {
        renderizarMovimiento($hijo, $nivel + 1);
    }

    echo "</div>";
}

// Construir árbol e imprimir
$estructuraJerarquica = construirArbolMovimientos($movimientos);
?>

<!-- FLUJO DEL EXPEDIENTE -->
<details class="detail-content" open>
<summary>FLUJO DEL EXPEDIENTE</summary>
<div style="padding: 1rem;">
    <?php foreach ($estructuraJerarquica as $mov): ?>
        <?php renderizarMovimiento($mov); ?>
    <?php endforeach; ?>
</div>
</details>

</div>
</details>
