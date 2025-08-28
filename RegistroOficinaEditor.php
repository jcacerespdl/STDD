<?php
include("head.php");
include_once("conexion/conexion.php");
session_start();
global $cnx, $sigaConn;

// Validar sesión
if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
    header("Location: index.php?error=No tiene una sesión activa.");
    exit();
}

$iCodTramite = $_GET['iCodTramite'] ?? null;
if (!$iCodTramite) {
    die("Error: Código de trámite no proporcionado.");
}

//----** Restricciones por tipo de documento : INICIO
// Solo 68 puede 110; solo 46 puede 111
$iCodOficina = (int)($_SESSION['iCodOficinaLogin'] ?? 0);

if ($iCodOficina === 68) {
    // Admin (68): ver todo menos 111
    $whereExtra = "AND cCodTipoDoc <> 111";
} elseif ($iCodOficina === 46) {
    // Dirección/Admin (46): ver todo menos 110
    $whereExtra = "AND cCodTipoDoc <> 110";
} else {
    // Otras oficinas: ocultar 110 y 111
    $whereExtra = "AND cCodTipoDoc NOT IN (110,111)";
}

$sqlTiposDoc = "
  SELECT cCodTipoDoc, cDescTipoDoc
  FROM Tra_M_Tipo_Documento
  WHERE nFlgInterno = 1
    $whereExtra
  ORDER BY cDescTipoDoc ASC
";
$resultTiposDoc = sqlsrv_query($cnx, $sqlTiposDoc);
$tiposDoc = [];
while ($row = sqlsrv_fetch_array($resultTiposDoc, SQLSRV_FETCH_ASSOC)) {
  $tiposDoc[] = $row;
}

// // Tipos de documentos
//         $sqlTiposDoc = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
//         $resultTiposDoc = sqlsrv_query($cnx, $sqlTiposDoc);
//         $tiposDoc = [];
//         while ($row = sqlsrv_fetch_array($resultTiposDoc, SQLSRV_FETCH_ASSOC)) {
//             $tiposDoc[] = $row;
//         }
//----** Restricciones por tipo de documento : FIN

// Oficinas
$sqlOficinas = "SELECT iCodOficina, cNomOficina, cSiglaOficina  FROM Tra_M_Oficinas";
$resultOficinas = sqlsrv_query($cnx, $sqlOficinas);
$oficinas = [];
while ($row = sqlsrv_fetch_array($resultOficinas, SQLSRV_FETCH_ASSOC)) {
    $oficinas[] = $row;
}

// Jefes
$sqlJefes = "SELECT t.iCodOficina, t.iCodTrabajador, tr.cNombresTrabajador, tr.cApellidosTrabajador 
        FROM Tra_M_Perfil_Ususario t 
        JOIN Tra_M_Trabajadores tr ON t.iCodTrabajador = tr.iCodTrabajador
        WHERE t.iCodPerfil = 3";
$resultJefes = sqlsrv_query($cnx, $sqlJefes);
$jefes = [];
while ($row = sqlsrv_fetch_array($resultJefes, SQLSRV_FETCH_ASSOC)) {
    $jefes[$row['iCodOficina']] = [
      "name" => $row['cNombresTrabajador'] . " " . $row['cApellidosTrabajador'], 
      "id" => $row['iCodTrabajador']
    ];
}

// Indicaciones
$sqlIndicaciones = "SELECT iCodIndicacion, cIndicacion FROM Tra_M_Indicaciones";
$resultIndicaciones = sqlsrv_query($cnx, $sqlIndicaciones);
$indicaciones = [];
while ($row = sqlsrv_fetch_array($resultIndicaciones, SQLSRV_FETCH_ASSOC)) {
    $indicaciones[] = $row;
}
// Obtener datos básicos del trámite y tipo de documento
$sqlTramite = "SELECT t.cCodTipoDoc, td.cDescTipoDoc, t.cCodificacion, t.cAsunto, t.cObservaciones, 
                       t.nNumFolio, t.nFlgFirma, t.documentoElectronico, t.descripcion ,t.cTipoBien, t.nTienePedidoSiga
               FROM Tra_M_Tramite t 
               JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
               WHERE t.iCodTramite = ?";
$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
if (!$stmtTramite) {
    error_log("Error SQL al obtener datos del tramite: " . print_r(sqlsrv_errors(), true));
    die("Error SQL: " . print_r(sqlsrv_errors(), true));
}
$tramite = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
if (!$tramite) {
    error_log("Tramite no encontrado para iCodTramite = $iCodTramite");
    die("Error: Trámite no encontrado.");
}

//obtener firmantes de doc principal
$sqlFirmantesPrincipal = "SELECT COUNT(*) AS total FROM Tra_M_Tramite_Firma 
                          WHERE iCodTramite = ? AND iCodDigital IS NULL";
$stmtFirmantesPrincipal = sqlsrv_query($cnx, $sqlFirmantesPrincipal, [$iCodTramite]);
$rowFirmantes = sqlsrv_fetch_array($stmtFirmantesPrincipal, SQLSRV_FETCH_ASSOC);
$hayFirmantesPrincipal = $rowFirmantes['total'] > 0;

$descripcionHTML = $tramite['descripcion'] ?? "";
$documentoElectronico = $tramite['documentoElectronico'] ?? null;
$tipoBienBD = isset($tramite['cTipoBien']) ? trim($tramite['cTipoBien']) : '';

// =====
// INICIO QUERY Obtener ítems SIGA si es tipo de documento 109
// =====
$sigaItems = [];
$manualItems = [];

if ((string)$tramite['cCodTipoDoc'] === '109' || (string)$tramite['cCodTipoDoc'] === '108') {
  $sqlPedidos = "SELECT 
      pedido_siga, codigo_item, cantidad, 
      stock, consumo_promedio, meses_consumo, situacion 
    FROM Tra_M_Tramite_SIGA_Pedido 
    WHERE iCodTramite = ?";
  $stmtPedidos = sqlsrv_query($cnx, $sqlPedidos, [$iCodTramite]);

  if ($stmtPedidos) {
    while ($pedido = sqlsrv_fetch_array($stmtPedidos, SQLSRV_FETCH_ASSOC)) {
      $pedidoSiga = $pedido['pedido_siga'];
      $codigoItem = $pedido['codigo_item'];
      $cantidad = $pedido['cantidad'];

      if ($pedidoSiga) {
        // Obtener datos SIGA reales
        $sqlCat = "SELECT CODIGO_ITEM, NOMBRE_ITEM FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?";
        $stmtCat = sqlsrv_query($sigaConn, $sqlCat, [$codigoItem]);

        if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
          $sigaItems[] = [
            "pedido_siga" => $pedidoSiga,
            "CODIGO_ITEM" => $cat['CODIGO_ITEM'],
            "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'],
            "CANTIDAD" => intval($cantidad)
          ];
        }
      } else {
        // Ítem manual sin pedido SIGA
        $sqlCat = "SELECT CODIGO_ITEM, NOMBRE_ITEM, TIPO_BIEN FROM CATALOGO_BIEN_SERV WHERE CODIGO_ITEM = ?";
        $stmtCat = sqlsrv_query($sigaConn, $sqlCat, [$codigoItem]);

        if ($stmtCat && $cat = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
          $manualItems[] = [
            "CODIGO_ITEM" => $codigoItem,
            "NOMBRE_ITEM" => $cat['NOMBRE_ITEM'],
            "TIPO_BIEN" => $cat['TIPO_BIEN'],
            "CANTIDAD" => intval($cantidad),
            "stock" => $pedido['stock'] ?? '',
            "consumo_promedio" => $pedido['consumo_promedio'] ?? '',
            "meses_consumo" => $pedido['meses_consumo'] ?? '',
            "situacion" => $pedido['situacion'] ?? ''
          ];
        }
      }
    }
  }
}
// =====
// FIN QUERY Obtener ítems SIGA si es tipo de documento 109
// =====

// Obtener destinos (todos los movimientos originales del trámite)
 $sqlMov = "SELECT 
 tm.iCodOficinaDerivar, tm.iCodTrabajadorDerivar, tm.iCodIndicacionDerivar, tm.cPrioridadDerivar,
  tm.cFlgTipoMovimiento,
                  o.cNomOficina, t.cNombresTrabajador, t.cApellidosTrabajador, i.cIndicacion
           FROM Tra_M_Tramite_Movimientos tm
           LEFT JOIN Tra_M_Oficinas o ON tm.iCodOficinaDerivar = o.iCodOficina
           LEFT JOIN Tra_M_Trabajadores t ON tm.iCodTrabajadorDerivar = t.iCodTrabajador
           LEFT JOIN Tra_M_Indicaciones i ON tm.iCodIndicacionDerivar = i.iCodIndicacion
           WHERE tm.iCodTramite = ?";
$resultMov = sqlsrv_query($cnx, $sqlMov, [$iCodTramite]);
$destinos = [];
if ($resultMov) {
    while ($row = sqlsrv_fetch_array($resultMov, SQLSRV_FETCH_ASSOC)) {
        $destinos[] = $row;
    }
}

// Obtener documentos complementarios generales
$complementariosGenerales = [];
$sqlComps = "
SELECT iCodDigital, cDescripcion, cTipoComplementario 
FROM Tra_M_Tramite_Digitales 
WHERE iCodTramite = ? AND pedido_siga IS NULL
";
$resComps = sqlsrv_query($cnx, $sqlComps, [$iCodTramite]);
if ($resComps) {
    while ($comp = sqlsrv_fetch_array($resComps, SQLSRV_FETCH_ASSOC)) {
        $complementariosGenerales[] =  [  
            'archivo' => $comp['cDescripcion'],
            'tipo' => $comp['cTipoComplementario']   
        ];
    }
}

 // Obtener complementarios por pedido_siga
$complementariosPorPedido = [];
$sqlPorPedido = "SELECT pedido_siga, cDescripcion, cTipoComplementario  FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND pedido_siga IS NOT NULL";
$resPedido = sqlsrv_query($cnx, $sqlPorPedido, [$iCodTramite]);
if ($resPedido) {
    while ($row = sqlsrv_fetch_array($resPedido, SQLSRV_FETCH_ASSOC)) {
        $complementariosPorPedido[$row['pedido_siga']][] = $row;
    }
}
// Obtener datos de perfil
$iCodOficinaLogin = $_SESSION['iCodOficinaLogin'] ?? null;
$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodPerfilLogin = $_SESSION['iCodPerfilLogin'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editor del Trámite</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="js/tinymce/tinymce.min.js"></script>
    <link rel="stylesheet" href="css/estilos.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
     <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
        }
        thead {
            background: #f5f5f5;
        }
        a.btn-primary, a.material-icons {
            text-decoration: none;
        }
        .material-icons {
            vertical-align: middle;
        }
        button.btn-primary:disabled {
                background-color: #ccc !important;
                color: #666 !important;
                cursor: not-allowed !important;
                pointer-events: none;
            }
            a.nombre-archivo {
                color: inherit;
                text-decoration: none;
                font-weight: normal;
            }
/* para quitar spinners */
            input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type=number] {
  -moz-appearance: textfield;
}
/* FIN para quitar spinners */
/* INICIO codigo para tostada */
#alert-toast {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%); /* centrado perfecto */
  background-color: #28a745;
  color: white;
  padding: 16px 24px;
  border-radius: 10px;
  font-family: 'Segoe UI', sans-serif;
  font-size: 15px;
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  animation: fadeInOut 3s forwards;
  display: none;
}
@keyframes fadeInOut {
  0%   {opacity: 0; transform: translate(-50%, -40%);}
  10%  {opacity: 1; transform: translate(-50%, -50%);}
  90%  {opacity: 1;}
  100% {opacity: 0; transform: translate(-50%, -60%);}
}
#alert-toast.success  { background-color: #28a745; } /* verde */
#alert-toast.error    { background-color: #dc3545; } /* rojo */
#alert-toast.warning  { background-color: #ffc107; color: #212529; } /* amarillo */
#alert-toast.info     { background-color: #17a2b8; } /* celeste */
/* FIN codigo para tostada */
    </style>
</head>
<body>
<div class="container" style="margin-top: 125px;">
    <form id="formularioEditorCabecera" method="POST">
    <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
 
        <div class="form-card">
            <h2>Redacción del Documento</h2>
            <div class="form-row">
                 <div class="input-container select-flotante">
                    <select id="tipoDocumento" name="tipoDocumento" required>
                    <option value="" disabled hidden></option>
                    <?php foreach ($tiposDoc as $tipo): ?>
                        <option value="<?= $tipo['cCodTipoDoc'] ?>" <?= $tramite['cCodTipoDoc'] === $tipo['cCodTipoDoc'] ? 'selected' : '' ?>>
                        <?= $tipo['cDescTipoDoc'] ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                    <label for="tipoDocumento">Tipo de Documento</label>
                </div>

                <div class="input-container">
                    <input type="text" id="correlativo" name="correlativo" class="form-control" value="<?= $tramite['cCodificacion'] ?>" readonly>
                    <label for="correlativo">Correlativo</label>
                </div>
                </div>

  <!-- ==== -->
  <!-- INICIO: GRUPO REQUERIMIENTO -->
  <!-- ==== -->

<div id="grupoRequerimiento" style="margin-top: 25px;">

  <!-- Fila de selección tipo de bien y ¿tiene pedido SIGA? -->
  <div class="form-row">
    <div class="input-container select-flotante">
      <select id="tipoBien" name="tipoBien" required>
        <option value="" disabled <?= $tipoBienBD === '' ? 'selected' : '' ?> hidden></option>
        <option value="B" <?= $tipoBienBD === 'B' ? 'selected' : '' ?>>Bien</option>
        <option value="S" <?= $tipoBienBD === 'S' ? 'selected' : '' ?>>Servicio</option>
      </select>
      <label for="tipoBien">Tipo de Requerimiento</label>
    </div>

    <div class="input-container select-flotante">
      <select id="pedidoSiga" name="pedidoSiga" required>
        <option value="" disabled hidden></option>
        <option value="1" <?= $tramite['nTienePedidoSiga'] == 1 ? 'selected' : '' ?>>Sí</option>
        <option value="0" <?= $tramite['nTienePedidoSiga'] == 0 ? 'selected' : '' ?>>No</option>
      </select>
      <label for="pedidoSiga">¿Tiene Pedido SIGA?</label>
    </div>
  </div>

  <!-- === BLOQUE: CON PEDIDO SIGA === -->
  <?php if ((int)$tramite['nTienePedidoSiga'] == 1): ?>
  <div id="seccionPedidoSiga" style="margin-top: 15px;">
    <div class="form-row">
      <div class="input-container">
        <input type="text" id="nroPedidoSIGA" placeholder=" " autocomplete="off">
        <label for="nroPedidoSIGA">N° Pedido SIGA</label>
      </div>
      <div class="input-container">
        <button type="button" id="buscarSigaBtn" class="btn-primary">Buscar Pedido SIGA</button>
      </div>
      <div class="input-container">
        <button type="button" id="agregarPedidoBtn" class="btn-secondary">Agregar Pedido SIGA</button>
      </div>
    </div>

    <!-- Resultados búsqueda -->
    <div class="form-row" id="resultadoBusqueda" style="display: none; margin-top: 10px;">
      <div class="input-container" style="width: 100%; overflow-x: auto;">
        <h3>Ítems SIGA Búsqueda</h3>
        <table id="tablaSiga" style="width: 100%; font-size: 14px;">
          <thead style="background: #f5f5f5;">
            <tr>
              <th>PEDIDO SIGA</th>
              <th>CÓDIGO ITEM</th>
              <th>NOMBRE ITEM</th>
              <th>CANTIDAD SOLICITADA</th>
               
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Ítems SIGA Agregados -->
<div class="form-row" id="resultadoAgregado" style="margin-top: 10px;">
  <div class="input-container" style="width: 100%; overflow-x: auto;">
    <h3>Ítems SIGA Agregados</h3>
    <table id="tablaSigaAgregados" style="width: 100%; font-size: 14px;">
      <thead style="background: #f5f5f5;">
        <tr>
          <th>PEDIDO SIGA</th>
          <th>CÓDIGO ITEM</th>
          <th>NOMBRE ITEM</th>
          <th>CANTIDAD</th>
          <th>ACCIONES</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $agrupados = [];
        foreach ($sigaItems as $item) {
            $key = $item['pedido_siga'] ?? 'N.A.';
            if ($key !== 'N.A.') {
                $agrupados[$key][] = $item;
            }
        }

        foreach ($agrupados as $pedidoSiga => $items):
          $rowspan = count($items);
          foreach ($items as $idx => $item):
              $clave = "{$pedidoSiga}_{$item['CODIGO_ITEM']}";
        ?>
          <tr data-clave="<?= $clave ?>" data-pedido="<?= $pedidoSiga ?>     ">
            <?php if ($idx === 0): ?>
              <td rowspan="<?= $rowspan ?>"><?= $pedidoSiga ?></td>
            <?php endif; ?>
            <td><?= $item['CODIGO_ITEM'] ?></td>
            <td><?= $item['NOMBRE_ITEM'] ?></td>
            <td style="padding: 4px;">
              <input type="number" min="1"
                value="<?= intval($item['CANTIDAD']) ?>"
                class="cantidad-input"
                data-cod="<?= $item['CODIGO_ITEM'] ?>"
                style="width: 100px; text-align: right;">
            </td>
            <?php if ($idx === 0): ?>
              <td rowspan="<?= $rowspan ?>" style="padding: 4px;">
                <button type="button" class="btn-secondary  "
                onclick="eliminarPedidoSiga('<?= $pedidoSiga ?>_<?= $tipoBienBD ?>')">
                  Eliminar
                </button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?> <!-- ✅ cierre del bloque CON pedido SIGA -->

  <!-- === BLOQUE: SIN PEDIDO SIGA === -->
  <?php if ((int)$tramite['nTienePedidoSiga'] == 0): ?>
<div id="busquedaItemSinPedido" style="margin-top: 20px;">
 
  <!-- Buscadores -->
  <div class="form-row" style="display: flex; gap: 12px;">
    <div style="display: flex; flex: 1.9; gap: 8px;">
      <div class="input-container select-flotante" style="flex: 1.5;">
        <input type="text" id="buscarItemCodigo" name="buscarItemCodigo" placeholder=" ">
        <label for="buscarItemCodigo">Código de Ítem</label>
      </div>
      <div class="input-container" style="flex: 0.4;">
        <button type="button" id="buscarItemBtn" class="btn-primary" style="width: 75%;">Buscar Catálogo</button>
      </div>
    </div>

    <div class="input-container select-flotante" style="flex: 1.85; position: relative;">
      <input type="text" id="buscarItemTextoNombre" name="buscarItemTextoNombre" placeholder=" " autocomplete="off">
      <label for="buscarItemTextoNombre">Nombre de Ítem</label>
      <div id="sugerenciasItemsNombre" class="sugerencias-dropdown"></div>
    </div>
  </div>

  <!-- Resultados de búsqueda -->
  <div class="form-row">
              <h4>Ítems Catálogo Búsqueda</h4>
              <table id="tablaItemsEncontrados" style="width: 100%; font-size: 14px; margin-top: 10px;">
                <thead style="background: #f5f5f5;">
                  <tr>
                    <th>Código</th><th>Nombre</th> <th>Cantidad</th><th>Acción</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

  <!-- Ítems agregados manualmente desde BD -->
  <div class="form-row" style="margin-top: 35px;">
    <h4 style="margin-bottom: 10px;">Ítems Catálogo Agregados</h4>
    <table id="tablaItemsSinPedido" style="width: 100%; font-size: 14px;">
      <thead style="background: #f5f5f5;">
        <tr>
          <th>Código</th>
          <th>Nombre</th>
          <th>Cantidad</th>
          <th>Stock</th>
          <th>Consumo Promedio</th>
          <th>Meses de Consumo</th>
          <th>Situación</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($manualItems as $item): ?>
          <?php
            $meses = (!empty($item['consumo_promedio']) && $item['consumo_promedio'] != 0)
              ? round($item['stock'] / $item['consumo_promedio'], 2) : '';
          ?>
          <tr data-cod="<?= $item['CODIGO_ITEM'] ?>">
            <td><?= $item['CODIGO_ITEM'] ?></td>
            <td><?= $item['NOMBRE_ITEM'] ?></td>
            <td><input type="number" min="1" value="<?= $item['CANTIDAD'] ?>" class="cantidad-input" data-cod="<?= $item['CODIGO_ITEM'] ?>" style="width: 70px;"></td>
            <td><input type="number" min="0" value="<?= $item['stock'] ?>" class="stock-input" data-cod="<?= $item['CODIGO_ITEM'] ?>" style="width: 70px;"></td>
            <td><input type="number" min="0" value="<?= $item['consumo_promedio'] ?>" class="consumo-input" data-cod="<?= $item['CODIGO_ITEM'] ?>" style="width: 70px;"></td>
            <td><input type="number" min="0" value="<?= $meses ?>" class="meses-input" data-cod="<?= $item['CODIGO_ITEM'] ?>" style="width: 70px;" readonly></td>
            <td>
              <select class="situacion-input" data-cod="<?= $item['CODIGO_ITEM'] ?>" style="width: 130px;">
                <?php
                  $situaciones = ['Desabastecido', 'Sub Stock', 'Norma Stock', 'Sobre Stock'];
                  foreach ($situaciones as $sit) {
                    $selected = ($item['situacion'] ?? '') === $sit ? 'selected' : '';
                    echo "<option value=\"$sit\" $selected>$sit</option>";
                  }
                ?>
              </select>
            </td>
            <td>
              <button type="button" class="btn-secondary eliminar-item"
                      data-cod="<?= $item['CODIGO_ITEM'] ?>"
                      data-pedido="N.A."
                      data-tramite="<?= $iCodTramite ?>">
                Eliminar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
</div>
  <!-- ==== -->
<!-- FIN: GRUPO REQUERIMIENTO -->
  <!-- ==== -->
                <div class="form-row">
                <div class="input-container" style="flex: 1; position: relative;">
                    <textarea name="asunto" id="asunto" class="form-textarea relleno" required><?= htmlspecialchars($tramite['cAsunto']) ?></textarea>
                    <label for="asunto">Asunto</label>
                </div>
                <div class="input-container" style="flex: 1; position: relative;">
                    <textarea name="observaciones" id="observaciones" class="form-textarea relleno"><?= htmlspecialchars($tramite['cObservaciones']) ?></textarea>
                    <label for="observaciones">Observaciones</label>
                </div>
                </div>

                <div class="form-row">
                <div class="input-container" style="flex: 0.49; position: relative;">
                    <input type="number" id="folios" name="folios" class="form-control" value="<?= intval($tramite['nNumFolio'] ?? 1) ?>" min="1">
                    <label for="folios">Folios</label>
                </div>
                </div>
    
                     
                <!-- Búsqueda de oficinas -->
            <h3>Búsqueda de Oficinas</h3>
            <div class="form-row">
            <div class="input-container oficina-ancha" style="position: relative;">
                <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off" required>
                <label for="nombreOficinaInput">Nombre de Oficina</label>
                <input type="hidden" id="oficinasDestino" name="oficinasDestino">
                <div id="sugerenciasOficinas" class="sugerencias-dropdown"></div>
            </div>

            <div class="input-container">
                <input type="text" id="jefeOficina" name="jefeOficina" placeholder=" " readonly>
                <label for="jefeOficina">Jefe</label>
            </div>

      <div class="input-container select-flotante">
        <select id="indicacion" name="indicacion" required>
          <option value="" disabled hidden></option>
          <?php foreach($indicaciones as $ind): ?>
            <option value="<?= $ind['iCodIndicacion'] ?>" <?= $ind['iCodIndicacion'] == 2 ? 'selected' : '' ?>>
              <?= trim($ind['cIndicacion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label for="indicacion">Indicación</label>
      </div>

      <div class="input-container select-flotante prioridad-reducida">
        <select id="prioridad" name="prioridad" required>
          <option value="" disabled selected hidden></option>
          <option value="1">Baja</option>
          <option value="2" selected>Media</option>
          <option value="3">Alta</option>
        </select>
        <label for="prioridad">Prioridad</label>
      </div>

      <label style="margin-left: 10px; align-self: center;">
        <input type="checkbox" id="copiaCheck"> Copia
      </label>

      <button type="button" class="btn-primary" style="padding: 0.75rem 1.5rem; min-width: 100px;" onclick="agregarDestino()">Agregar</button>
    </div>

    <!-- Tabla destinos -->
    <div class="form-row" id="tablaDestinos" style="margin-top: 20px;">
      <div class="input-container" style="width: 100%; overflow-x: auto;">
        <h3 style="margin-top: 0;">Destinos</h3>
        <table id="tablaDestinos" style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <thead style="background: #f5f5f5;">
            <tr>
              <th>Oficina</th>
              <th>Jefe</th>
              <th>Indicación</th>
              <th>Prioridad</th>
              <th>Copia</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($destinos as $d): ?>
              <?php
                $ofId = $d['iCodOficinaDerivar'];
                $trId = $d['iCodTrabajadorDerivar'];
                $indId = $d['iCodIndicacionDerivar'];
                $prior = $d['cPrioridadDerivar'];
                $copia = $d['cFlgTipoMovimiento'] == '4' ? '1' : '0';
                $value = "{$ofId}_{$trId}_{$indId}_{$prior}_{$copia}";
              ?>
              <tr>
                <input type="hidden" name="destinos[]" value="<?= $value ?>">
                <td><?= $d['cNomOficina'] ?></td>
                <td><?= $d['cNombresTrabajador'] . ' ' . $d['cApellidosTrabajador'] ?></td>
                <td><?= $d['cIndicacion'] ?></td>
                <td><?= $prior ?></td>
                <td><input type="checkbox" <?= $copia === '1' ? 'checked' : '' ?> disabled></td>
                <td>
                  <button type="button" class="btn-secondary" onclick="eliminarDestino(this, '<?= $ofId ?>')">Eliminar</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Botón corregir -->
    <div class="form-group" style="display: flex; justify-content: flex-start; margin-top: 20px;">
      <button type="button" class="btn-primary" onclick="guardarCabeceraGenerar()">Guardar Cambios</button>
    </div>
  </div>
         </form>

         <div class="form-card" id="cardReferencias" style="margin-top: 25px;">
    <h3>Referencias</h3>

    <div class="form-row" style="margin-bottom: 10px;">
        <button type="button" class="btn-primary" onclick="abrirPopupReferencias()">
            <i class="material-icons">link</i> Agregar Referencia
        </button>
    </div>

    <div class="form-row" id="tablaReferenciasAgregadas">
        <!-- Aquí se cargan las referencias agregadas -->
        <?php
            $_GET['iCodTramite'] = $iCodTramite;
            include("listarReferenciasAgregadas.php");
        ?>
    </div>
</div>
 
    <!-- Radios para elegir el modo -->
<div class="form-row" style="margin-top: 25px;">
    <label><input type="radio" name="modoDocumento" value="generar" checked onchange="cambiarModoDocumento()"> Generar Documento Principal</label>
    <label style="margin-left: 30px;"><input type="radio" name="modoDocumento" value="adjuntar" onchange="cambiarModoDocumento()"> Adjuntar Documento Principal</label>
</div>

<!-- Formulario para documento generado con TinyMCE -->
<form id="formularioEditor" method="POST">
            <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">   
            <div id="contenedorEditor">
            <textarea id="descripcion" name="descripcion"><?= htmlspecialchars($descripcionHTML) ?></textarea>
        </div>
        <div class="form-row" style="margin-top: 15px; display: flex; gap: 10px;">
        <button type="submit" id="guardarBtn" class="btn-primary">
            <i class="material-icons">save</i> Guardar
        </button>

        <a id="descargarBtn"
        class="btn-primary"
        target="_blank"
        style="text-decoration: none; background-color: #ccc; color: #666; cursor: not-allowed; pointer-events: none;">
        <i class="material-icons">download</i> Descargar
        </a>
        <button type="button" onclick="abrirPopupFirmantesPrincipal(<?= $iCodTramite ?>)" class="btn-primary">
            <i class="material-icons">group_add</i> Solicitar Vistos Buenos
        </button>
        <?php if ($iCodPerfilLogin == 3): ?>
          <?php if ($hayFirmantesPrincipal): ?>
              <button type="button" class="btn-primary" disabled title="No disponible: documento con firmantes asignados">
                  <i class="material-icons">edit_document</i> Firmar
              </button>
            <?php else: ?>
              <button type="button" id="btnFirmarPrincipal" class="btn-primary" 
                      <?= $documentoElectronico ? '' : 'disabled' ?>>
                  <i class="material-icons">edit_document</i> Firmar
              </button>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ((string)$tramite['cCodTipoDoc'] === '108' || (string)$tramite['cCodTipoDoc'] === '109'): ?>
            <button type="button" id="btnInsertarSiga" class="btn btn-secondary" style="margin-left: 10px;">
                <i class="material-icons">addchart</i> Insertar data SIGA
            </button>
        <?php endif; ?>
    </div>
</form>

<!-- Formulario para documento adjunto -->
<div id="contenedorAdjunto" style="display: none; margin-top: 25px;">
    <form id="formAdjuntoPrincipal" enctype="multipart/form-data">
        <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
        <input type="file" name="archivoPrincipal" accept="application/pdf" required>
        <small>Solo PDF de hasta 20 MB.</small><br>
        <button type="submit" class="btn-primary" style="margin-top: 10px;">
            <i class="material-icons">upload</i> Subir Documento Principal
        </button>
    </form>
    <a href="generarPlantillaWord.php?iCodTramite=<?= $iCodTramite ?>" class="btn-primary" target="_blank" style="margin-top: 10px; display: inline-block;">
        <i class="material-icons">description</i> Descargar Plantilla Word
    </a>
</div>
            </form>

            <div class="form-row" style="margin-top: 40px;">
    <div class="input-container" style="width: 100%;">
        <h2>Complementarios</h2>
        <?php
        $complementariosTotales = [];

        foreach ($complementariosGenerales as $doc) {
            $complementariosTotales[] = [
                'archivo' => $doc['archivo'],
                'pedido_siga' => null ,
                'tipo' => $doc['tipo']
            ];
        }

        foreach ($complementariosPorPedido as $pedido => $docs) {
            foreach ($docs as $doc) {
                $complementariosTotales[] = [
                    'archivo' => $doc['cDescripcion'],
                    'pedido_siga' => $doc['pedido_siga'],
                    'tipo' => $doc['cTipoComplementario'] ?? null
                ];
            }
        }
        ?>

        <?php if (!empty($complementariosTotales)): ?>
        <table>
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Pedido SIGA</th>
                    <th>Tipo de Documento</th>
                    <th>Acciones</th>
                    <!-- <th>Seleccionar</th> -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complementariosTotales as $doc): ?>
                <tr>
                    <td>
                            <a href="cAlmacenArchivos/<?= urlencode($doc['archivo']) ?>" target="_blank" class="nombre-archivo">
                            <i class="material-icons" title="<?= htmlspecialchars((string)$doc['archivo']) ?>">picture_as_pdf</i>
                            <?= htmlspecialchars($doc['archivo']) ?>
                        </a>
                        </td>
            <td><?= $doc['pedido_siga'] !== null ? htmlspecialchars($doc['pedido_siga']) : 'N.A.' ?></td>
            <td>
                <?php
                    $mapaTipo = [
                        1 => 'Pedido SIGA',
                        2 => 'TDR o ETT',
                        3 => 'Solicitud Crédito',
                        4 => 'Aprobación Crédito',
                        5 => 'Orden de Servicio',
                        0 => 'Ninguno',
                        null => 'Ninguno'
                    ];
                    echo $mapaTipo[$doc['tipo']] ?? 'Ninguno';
                ?>
            </td>
            <td>
                <a href="eliminarComplementario.php?iCodTramite=<?= $iCodTramite ?>&archivo=<?= urlencode($doc['archivo']) ?>" 
                   onclick="return confirm('¿Desea eliminar este archivo?')" 
                   style="color: var(--secondary);">
                           <i class="material-icons" title="Eliminar archivo">delete</i>
                </a>

                 <!-- Botón firmantes individuales -->
                 <?php
                $sqlGetDigital = "SELECT iCodDigital FROM Tra_M_Tramite_Digitales WHERE iCodTramite = ? AND cDescripcion = ?";
                $stmtGetDigital = sqlsrv_query($cnx, $sqlGetDigital, [$iCodTramite, $doc['archivo']]);
                $iCodDigital = null;
                if ($rowDigital = sqlsrv_fetch_array($stmtGetDigital, SQLSRV_FETCH_ASSOC)) {
                    $iCodDigital = $rowDigital['iCodDigital'];
                }
                if ($iCodDigital): ?>
                    <a href="#" onclick="abrirPopupFirmantes(<?= $iCodTramite ?>, <?= $iCodDigital ?>, '<?= htmlspecialchars($doc['archivo']) ?>')" style="color: var(--primary);">
                        <i class="material-icons" title="Solicitar Firmas">person_add</i>
                    </a>
                     
                <?php endif; ?>
            </td>
            <!-- <td>
                <input type="checkbox" class="chk-complementario" value="<?= htmlspecialchars($doc['archivo']) ?>">
            </td> -->
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <!-- <button type="button" id="btnFirmantesMultiples" class="btn-primary" disabled>
            <i class="material-icons">group_add</i> Asignar Firmantes a Complementarios
        </button> -->
        <?php else: ?>
            <p>No hay complementarios registrados.</p>
        <?php endif; ?>

        <form id="formSubirComplementarios" action="subirComplementarioMASIVO.php" method="POST" enctype="multipart/form-data" autocomplete="off"  style="margin-top: 10px;">
            <input type="hidden" name="iCodTramite" value="<?= $iCodTramite ?>">
            <input type="file" id="inputArchivos" name="archivos[]" multiple onchange="mostrarTipoComplementarios(this.files)" accept="application/pdf">
            <button type="submit" class="btn-primary" id="btnSubirComplementarios" disabled  style="margin-top: 8px;">
                <i class="material-icons">upload</i> Subir Complementarios
            </button>
            <button type="button" id="btnEnviar" class="btn-primary" style="margin-top: 10px;">
                <i class="material-icons">send</i> Generar Trámite
            </button>
        </form>
    </div>
</div>
    </div>
    </div>
    <div id="addComponent"></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="./scripts/jquery.blockUI.js"></script>
<script>
          if (typeof tinymce === "undefined") {
        console.error("❌ TinyMCE no se ha cargado. Revisa la ruta del archivo JS.");
    } else {
        console.log("✅ TinyMCE cargado correctamente:", tinymce);
    }
        console.log("iCodTrabajador:", <?= json_encode($iCodTrabajador) ?>);
        console.log("iCodOficina:", <?= json_encode($iCodOficinaLogin) ?>);
        console.log("iCodPerfil (3=Jefe, 19=Asistente, 4=Profesional):", <?= json_encode($iCodPerfilLogin) ?>);
         console.log("firmantes", <?= json_encode($hayFirmantesPrincipal) ?>);
        
        console.log("iCodTramite:", <?= json_encode($iCodTramite) ?>);
        console.log("Destinos:", <?= json_encode($destinos) ?>);
        
          tinymce.init({
                selector: '#descripcion',
                height: 500,
                language: 'es',
                menubar: 'insert table',  // importante que incluya 'table'
                plugins: 'table image link lists autolink paste',
                toolbar: 'undo redo | styleselect | bold italic underline forecolor backcolor | ' +
                        'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
                        'link image table | removeformat',
                fontsize_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt',
                image_title: true,
                automatic_uploads: true,
                file_picker_types: 'image',
                file_picker_callback: function(cb, value, meta) {
                  if (meta.filetype === 'image') {
                    const input = document.createElement('input');
                    input.setAttribute('type', 'file');
                    input.setAttribute('accept', 'image/*');
                    input.onchange = function () {
                      const file = this.files[0];
                      const reader = new FileReader();
                      reader.onload = function () {
                        cb(reader.result, { title: file.name });
                      };
                      reader.readAsDataURL(file);
                    };
                    input.click();
                  }
                }
              });
       

        // Alternar entre editor y adjunto
        function cambiarModoDocumento() {
            const modo = document.querySelector('input[name="modoDocumento"]:checked').value;
            document.getElementById('contenedorEditor').style.display = (modo === 'generar') ? 'block' : 'none';
    document.getElementById('contenedorAdjunto').style.display = (modo === 'adjuntar') ? 'block' : 'none';
    document.getElementById('guardarBtn').disabled = (modo === 'adjuntar');
}

// Guardar y generar PDF
document.getElementById('formularioEditor').addEventListener('submit', function(e) {
            e.preventDefault();
            tinymce.triggerSave();
              // ✅ Añadir tipoDocumento manualmente desde otro formulario
            const tipoDocumentoValor = document.getElementById('tipoDocumento').value;
            const formData = new FormData(this);
            formData.append('tipoDocumento', tipoDocumentoValor); // ← AQUÍ VA
            const guardarBtn = document.getElementById('guardarBtn');
            guardarBtn.disabled = true;
            guardarBtn.innerHTML = 'Guardando...';

            fetch('actualizarDescripcion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    return fetch('exportarTramitePDF.php', {
                        method: 'POST',
                        body: formData
                    });
                } else {
                    throw new Error(res.message);
                }
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success' && res.filename) {
                    documento = res.filename;
                    const btnDescargar = document.getElementById('descargarBtn');

                        btnDescargar.href = `https://tramite.heves.gob.pe/STDD_marchablanca/cDocumentosFirmados/${res.filename}`;
                        btnDescargar.style.backgroundColor = '';
                        btnDescargar.style.color = '';
                        btnDescargar.style.cursor = '';
                        btnDescargar.style.pointerEvents = '';
                      // Habilita el botón de firmar SOLO si existe y estaba deshabilitado
                        const btnFirmar = document.getElementById("btnFirmarPrincipal");
                        if (btnFirmar && btnFirmar.disabled) {
                            btnFirmar.disabled = false;
                        }
                        showToast('success','Guardado correctamente. PDF actualizado.');
                } else {
                    throw new Error(res.message || 'No se pudo generar el PDF');
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
            })
            .finally(() => {
                guardarBtn.disabled = false;
                guardarBtn.innerHTML = '<i class="material-icons">save</i> Guardar';
            });
        });
//// FIN DE  Guardar y generar PDF
// boton enviar
        document.getElementById('btnEnviar').addEventListener('click', async function() {
    const iCodTramite = <?= json_encode($iCodTramite) ?>;
    if (!confirm("¿Está seguro que desea enviar el documento? ")) return;

    try {
        const res = await fetch('enviar_Tramite.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ iCodTramite })
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert("Documento enviado correctamente.");
            window.location.href = 'bandejaEnviados.php';
        } else {
            alert("Error: " + data.message);
        }
    } catch (err) {
        console.error(err);
        alert("Ocurrió un error al enviar el documento.");
    }
});

document.getElementById('formSubirComplementarios').addEventListener('submit', function(e) {
        setTimeout(() => {
            document.getElementById('inputArchivos').value = '';
        }, 500); // esperar que el submit se complete
    });


     // Habilitar botón masivo si hay al menos un checkbox
    const chkBoxes = document.querySelectorAll('.chk-complementario');
    const btnFirmantesMultiples = document.getElementById('btnFirmantesMultiples');

    chkBoxes.forEach(chk => {
        chk.addEventListener('change', () => {
            const algunoMarcado = Array.from(chkBoxes).some(cb => cb.checked);
            btnFirmantesMultiples.disabled = !algunoMarcado;
        });
    });

    if (btnFirmantesMultiples && chkBoxes.length > 0) {
    chkBoxes.forEach(chk => {
        chk.addEventListener('change', () => {
            const algunoMarcado = Array.from(chkBoxes).some(cb => cb.checked);
            btnFirmantesMultiples.disabled = !algunoMarcado;
        });
    });

    btnFirmantesMultiples.addEventListener('click', () => {
        const seleccionados = Array.from(chkBoxes)
            .filter(cb => cb.checked)
            .map(cb => encodeURIComponent(cb.value)); // contiene cDescripcion

        if (!seleccionados.length) return;

        const url = `registroTrabajadoresFirmaCompleMultiple.php?iCodTramite=<?= $iCodTramite ?>&archivos=${seleccionados.join(',')}`;
        window.open(url, '_blank', 'width=1250,height=550,scrollbars=yes,resizable=yes');
    });
    }

    function abrirPopupFirmantes(iCodTramite, iCodDigital, nombreArchivo) {
    const url = `registroTrabajadoresFirmaComplementario.php?iCodTramite=${iCodTramite}&iCodDigital=${iCodDigital}`;
    const win = window.open(url, `Firmantes - ${nombreArchivo}`, 'width=1250,height=600,resizable=yes,scrollbars=yes');
    if (!win || win.closed || typeof win.closed == 'undefined') {
        alert("Por favor, habilite las ventanas emergentes en su navegador.");
    }
}

function abrirPopupFirmantesPrincipal(iCodTramite) {
    const url = `registroTrabajadoresFirmaPrincipal.php?iCodTramite=${iCodTramite}`;
    const win = window.open(url, `Firmantes Principal`, 'width=1250,height=600,resizable=yes,scrollbars=yes');
    if (!win || win.closed || typeof win.closed == 'undefined') {
        alert("Por favor, habilite las ventanas emergentes en su navegador.");
    }
}
const iCodTrabajador = <?= (int) $_SESSION['CODIGO_TRABAJADOR'] ?>;
let documento = <?= json_encode($documentoElectronico) ?>;
console.log("🧾 Documento principal:", documento);
if (documento) {
    const btnDescargar = document.getElementById('descargarBtn');
    btnDescargar.href = `https://tramite.heves.gob.pe/STDD_marchablanca/cDocumentosFirmados/${documento}`;
    btnDescargar.style.backgroundColor = '';
    btnDescargar.style.color = '';
    btnDescargar.style.cursor = '';
    btnDescargar.style.pointerEvents = '';
}
var jqFirmaPeru = jQuery.noConflict(true);

function signatureInit() {
    jqFirmaPeru.blockUI({ message: '<h1>Iniciando firma...</h1>' });
}

function signatureOk() {
    alert(" Documento firmado correctamente");
    location.reload();
}

function signatureCancel() {
    alert("Firma cancelada.");
}

const btnFirmar = document.getElementById("btnFirmarPrincipal");
if (btnFirmar) {
    btnFirmar.addEventListener("click", function () {
        if (!documento) {
            alert("El documento aún no ha sido generado.");
            return;
        }

        const formData = new URLSearchParams();
        formData.append("documento", documento);
        formData.append("iCodTramite", <?= json_encode($iCodTramite) ?>);

        fetch("comprimirPrincipalFirma.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log("✅ ZIP generado:", data.archivo);
                const nombreZip = data.archivo.replace('.7z', '');
                const zipSinExtension = nombreZip.replace('.7z', '');
                const param_url = `https://tramite.heves.gob.pe/STDD_marchablanca/getFpParamsPrincipal.php?nombreZip=${zipSinExtension}&iCodTramite=${<?= json_encode($iCodTramite) ?>}`; 

                const paramPrev = {
                    param_url: param_url,
                    param_token: "123456",
                    document_extension: "pdf"
                };
                const param = btoa(JSON.stringify(paramPrev));
                const port = "48596";  

                startSignature(port, param);
            } else {
                alert("Error al generar ZIP: " + (data.error || "Desconocido"));
            }
        })
        .catch(err => {
            console.error("Error en firma:", err);
            alert("Error en firma.");
        });
    });
}


function abrirTipoComplementario(iCodTramite, iCodDigital) {
    const url = `registroEspecialComplementario.php?iCodTramite=${iCodTramite}&iCodDigital=${iCodDigital}`;
    const win = window.open(url, 'TipoComplementario', 'width=800,height=500,resizable=yes,scrollbars=yes');
    if (!win || win.closed || typeof win.closed == 'undefined') {
        alert("Por favor, habilite las ventanas emergentes.");
    }
}

document.getElementById('formAdjuntoPrincipal').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
        const res = await fetch('subirAdjuntoPrincipal.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.status === 'success') {
            documento = result.filename;

            const btnDescargar = document.getElementById('descargarBtn');
            btnDescargar.href = `https://tramite.heves.gob.pe/STDD_marchablanca/cDocumentosFirmados/${result.filename}`;
            btnDescargar.style.backgroundColor = '';
            btnDescargar.style.color = '';
            btnDescargar.style.cursor = '';
            btnDescargar.style.pointerEvents = '';
            
              // Habilita el botón de firmar SOLO si existe y estaba deshabilitado
        const btnFirmar = document.getElementById("btnFirmarPrincipal");
        if (btnFirmar && btnFirmar.disabled) {
            btnFirmar.disabled = false;
        }


            alert(" Documento subido correctamente.");
        } else {
            alert(" Error al subir documento: " + result.message);
        }
    } catch (err) {
        alert(" Error en la conexión: " + err.message);
    }
});

const inputArchivos = document.getElementById('inputArchivos');
const btnSubirComplementarios = document.getElementById('btnSubirComplementarios');

function validarArchivoSeleccionado() {
    btnSubirComplementarios.disabled = inputArchivos.files.length === 0;
}

inputArchivos.addEventListener('change', validarArchivoSeleccionado);
window.addEventListener('load', validarArchivoSeleccionado);

function actualizarBotonFirmarPrincipal() {
    const btnFirmar = document.getElementById("btnFirmarPrincipal");
    if (!btnFirmar) return;

    fetch('verificarFirmantesPrincipal.php?iCodTramite=<?= $iCodTramite ?>')
        .then(res => res.json())
        .then(data => {
            if (data.hayFirmantes) {
                btnFirmar.disabled = true;
                btnFirmar.title = "No disponible: documento con firmantes asignados";
            } else {
                btnFirmar.disabled = !documento;
                btnFirmar.title = "";
            }
        })
        .catch(err => {
            console.error("Error al verificar firmantes:", err);
        });
}

///////PARA PODER EDITAR EL ENCABEZADO EN LA REDACCCION
function agregarDestino() {
        const oficinaId = document.getElementById("oficinasDestino").value;
        const oficinaNombre = document.getElementById("nombreOficinaInput").value;
        const jefe = document.getElementById("jefeOficina").value;
        const jefeId = document.getElementById("jefeOficina").dataset.jefeid;
        const indicacionSelect = document.getElementById("indicacion");
        const indicacionValue = indicacionSelect.value;
        const indicacionText = indicacionSelect.options[indicacionSelect.selectedIndex].text;
        const prioridadSelect = document.getElementById("prioridad");
        const prioridadValue = prioridadSelect.value;
        const prioridadText = prioridadSelect.options[prioridadSelect.selectedIndex].text;
        const esCopia = document.getElementById("copiaCheck").checked ? 1 : 0;
        
         // Validar campos
        if (!oficinaId || !indicacionValue || !prioridadValue) {
            alert("Por favor, complete todos los campos.");
            return;
        }
        console.log("➕ Agregando destino:", {
                oficinaId,
                jefeId,
                indicacionValue,
                prioridadValue,
                esCopia
            });
        // Prevenir duplicado
        if (oficinasAgregadas.has(oficinaId)) {
            alert("Esta oficina ya ha sido agregada.");
            return;
        }

        oficinasAgregadas.add(oficinaId);

        const table = document.getElementById("tablaDestinos").getElementsByTagName('tbody')[0];
        const row = table.insertRow();

          // Agregar fila con los datos
        row.innerHTML = `
            <input type="hidden" name="destinos[]" value="${oficinaId}_${jefeId}_${indicacionValue}_${prioridadText}_${esCopia}"/>
            <td>${oficinaNombre}</td>
            <td>${jefe}</td>
            <td>${indicacionText}</td>
            <td>${prioridadText}</td>
            <td>
            <input type="checkbox" ${esCopia ? 'checked' : ''} disabled>
            </td>
            <td><button type="button" class="btn-secondary" onclick="eliminarDestino(this, '${oficinaId}')">Eliminar</button></td>`;

        // Limpiar campos del formulario
        document.getElementById("nombreOficinaInput").value = '';
        document.getElementById("oficinasDestino").value = '';
        document.getElementById("jefeOficina").value = '';
        document.getElementById("jefeOficina").dataset.jefeid = '';
        document.getElementById("indicacion").value = "2"; // cuando se seleccione nuevamente poner la indicacion 2 por defecto
        document.getElementById("prioridad").selectedIndex = 1;
        document.getElementById("copiaCheck").checked = false;
    }

       function eliminarDestino(btn, oficinaId) {
                oficinasAgregadas.delete(oficinaId);
                btn.parentElement.parentElement.remove();
            }
            async function guardarCabeceraGenerar() {
            const form = document.getElementById("formularioEditorCabecera");
            const formData = new FormData(form);
                // Mostrar todos los destinos que se van a enviar
                for (let pair of formData.entries()) {
                        if (pair[0] === 'destinos[]') {
                            console.log("📦 Enviando destino:", pair[1]);
                        }
                    }
            try {
                const res = await fetch('actualizarCabeceraGenerar.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    alert("Cambios guardados correctamente");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            } catch (err) {
                alert("Error en la solicitud");
                console.error(" Error en guardarCabeceraGenerar:", err);

            }
        }


        const oficinasAgregadas = new Set(); // evitar duplicados
        const oficinas = <?= json_encode($oficinas, JSON_UNESCAPED_UNICODE) ?>;
        const jefes = <?= json_encode($jefes, JSON_UNESCAPED_UNICODE) ?>;

        function mostrarSugerenciasOficinas(filtro = "") {
        const contenedor = $('#sugerenciasOficinas');
        contenedor.empty();

        const filtroLower = filtro.toLowerCase();
        const resultados = oficinas.filter(ofi =>
            ofi.cNomOficina.toLowerCase().includes(filtroLower) ||
            ofi.cSiglaOficina.toLowerCase().includes(filtroLower)
        );

        if (resultados.length === 0) {
            contenedor.append('<div class="sugerencia-item" style="color:#888">Sin resultados</div>');
        }

        resultados.forEach(ofi => {
            const textoCompleto = `${ofi.cNomOficina} - ${ofi.cSiglaOficina}`;
            const item = $('<div class="sugerencia-item">').text(textoCompleto);
            item.on('click', function () {
            $('#nombreOficinaInput').val(textoCompleto);
            $('#oficinasDestino').val(ofi.iCodOficina);

            const jefe = jefes[ofi.iCodOficina];
            $('#jefeOficina')
                .val(jefe ? jefe.name : '')
                .attr('data-jefeid', jefe ? jefe.id : '');

            contenedor.hide();
            });
            contenedor.append(item);
        });

        contenedor.show();
        }

        $('#nombreOficinaInput').on('focus', function () {
        if ($(this).val().trim() === '') {
            mostrarSugerenciasOficinas('');
        }
        });

        $('#nombreOficinaInput').on('input', function () {
        const texto = $(this).val().trim();
        if (texto.length >= 1) {
            mostrarSugerenciasOficinas(texto);
        } else {
            $('#sugerenciasOficinas').hide();
        }
        });

        $(document).on('click', function (e) {
        if (!$(e.target).closest('#nombreOficinaInput, #sugerenciasOficinas').length) {
            $('#sugerenciasOficinas').hide();
        }
        });


        //Obtener correlativo
        window.onload = function () {
                const selectTipoDoc = document.getElementById('tipoDocumento');
                if (!selectTipoDoc) {
                    console.error("❌ No se encontró el select con id 'tipoDocumento'");
                    return;
                }

                selectTipoDoc.addEventListener('change', function () {
                    const tipoDoc = this.value;
                    const oficina = <?= $_SESSION['iCodOficinaLogin'] ?>;
                    const anio = new Date().getFullYear();

                    console.log("✅ Enviando tipoDocumento:", tipoDoc);

                    fetch('obtenerCorrelativo.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ tipoDocumento: tipoDoc, oficina: oficina, anio: anio })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('correlativo').value = data.correlativo;
                        } else {
                            alert('Error al obtener correlativo: ' + (data.message || ''));
                        }
                    })
                    .catch(error => {
                        console.error('Error en correlativo:', error);
                    });
                });
            };

            //REFERENCIAS
                function abrirPopupReferencias() {
                    const iCodTramite = <?= (int)$iCodTramite ?>;
                    const url = `registroOficinaEditor_referencias.php?iCodTramite=${iCodTramite}`;
                    const win = window.open(url, 'Seleccionar Referencias', 'width=950,height=600,resizable=yes,scrollbars=yes');
                    if (!win || win.closed || typeof win.closed === 'undefined') {
                        alert("Por favor, habilite las ventanas emergentes.");
                    }
                }

                function eliminarReferencia(iCodRelacionado) {
                    if (!confirm("¿Desea eliminar esta referencia?")) return;

                    const iCodTramite = <?= (int)$iCodTramite ?>;
                    fetch("insertarReferencia.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: `iCodTramite=${iCodTramite}&iCodRelacionado=${iCodRelacionado}&accion=eliminar`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === "success") {
                            alert("Referencia eliminada.");
                            cargarReferenciasAgregadas();
                        } else {
                            alert("Error: " + data.message);
                        }
                    });
                }

                function cargarReferenciasAgregadas() {
                    const iCodTramite = <?= (int)$iCodTramite ?>;
                    fetch("listarReferenciasAgregadas.php?iCodTramite=" + iCodTramite)
                    .then(res => res.text())
                    .then(html => {
                        document.getElementById("tablaReferenciasAgregadas").innerHTML = html;
                    });
                }

            //FIN REFERENCIAS

 // ==========================
// 🔁 INICIO: JS PARA SIGA EN EDITOR
// ==========================

// No usamos itemsManual, todo se guarda en BD
let itemsSeleccionados = {}; // Objeto para controlar ítems CON pedido SIGA (por clave único: pedido_codigo)
const iCodTramite = <?= (int)$iCodTramite ?>;
// 🔍 2A. Buscar por código
$('#buscarItemBtn').on('click', function () {
  const tipo = $('#tipoBien').val();
  const codigo = $('#buscarItemCodigo').val().trim();
  if (!tipo || !codigo) return alert("Buscar por código requiere tipo y valor válido.");

  $.get('buscar_item.php', { tipo: tipo, q: codigo }, function (res) {
    if (!res.length) return alert("No se encontró el ítem.");
    renderizarItemsCatalogo(res);
  }, 'json');
});

// 🔍 2B. Buscar por nombre con autosugerencias
$('#buscarItemTextoNombre').on('input', function () {
  const texto = $(this).val().trim();
  const tipo = $('#tipoBien').val();

  if (!texto || texto.length < 3) {
    $('#sugerenciasItemsNombre').hide();
    return;
  }

  $.get('buscar_item_nombre.php', { q: texto }, function (res) {
    $('#buscarItemTextoNombre').data('sugerencias', res); // ✅ CORREGIDO: asignar aquí
    const contenedor = $('#sugerenciasItemsNombre');
    contenedor.empty().show();

    const tipoEsperado = tipo === 'B' ? 'BIEN' : tipo === 'S' ? 'SERVICIO' : null;

    res.forEach(item => {
      if (item.TIPO_BIEN === tipo) {
        const opcion = $(`<div class="sugerencia-item">${item.NOMBRE_ITEM}</div>`);
        opcion.on('click', function () {
          $('#buscarItemTextoNombre').val(item.NOMBRE_ITEM);
          $('#sugerenciasItemsNombre').hide();
          renderizarItemsCatalogo([item]);
        });
        contenedor.append(opcion);
      }
    });

    if (contenedor.children().length === 0) {
      contenedor.append('<div class="sugerencia-item" style="color: #888;">Sin coincidencias</div>');
    }
  }, 'json');
});

// 🔽 Ocultar dropdown si se hace clic fuera
$(document).on('click', function (e) {
  if (!$(e.target).closest('#buscarItemTextoNombre, #sugerenciasItemsNombre').length) {
    $('#sugerenciasItemsNombre').hide();
  }
});

// 🔄 3. Renderizar resultados del catálogo (tablaItemsEncontrados)
function renderizarItemsCatalogo(items) {
  const filas = items.map(item => {
    const cod = item.CODIGO_ITEM;
    const nombre = item.NOMBRE_ITEM;

    return `
      <tr data-cod="${cod}">
        <td>${cod}</td>
        <td>${nombre}</td>
        <td><input type="number" min="1" name="cantidad_${cod}" style="width: 70px;"></td>
        <td>
          <button type="button" onclick="agregarItemManual('${cod}', '${nombre}')">Agregar</button>
        </td>
      </tr>
    `;
  }).join('');

  $('#tablaItemsEncontrados tbody').html(filas);
}


// ➕ 4. Agregar ítem manual a tabla y guardar en BD
function agregarItemManual(codigo, nombre) {
  const cantidad = parseInt($(`input[name="cantidad_${codigo}"]`).val()) || 0;
  if (cantidad <= 0) return alert(" Cantidad inválida");

  const stock = parseFloat($(`input[name="stock_${codigo}"]`).val()) || 0;
  const consumo = parseFloat($(`input[name="consumo_${codigo}"]`).val()) || 0;
  const meses = consumo > 0 ? (stock / consumo).toFixed(2) : 0;
  const situacion = $(`select[name="situacion_${codigo}"]`).val() || '';

  // Verifica duplicado
  if ($(`#tablaItemsSinPedido tbody tr[data-cod="${codigo}"]`).length > 0) {
    return alert("Item Ya fue agregado.");
  }

  const bodyData = `iCodTramite=${iCodTramite}&codigoItem=${encodeURIComponent(codigo)}&nuevaCantidad=${cantidad}` +
                   `&stock=${stock}&consumo=${consumo}&meses=${meses}&situacion=${encodeURIComponent(situacion)}`;

  fetch('guardarItemManual.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: bodyData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'inserted' || data.status === 'updated') {
      // Añadir fila con clases correctas
      $('#tablaItemsSinPedido tbody').append(`
        <tr data-cod="${codigo}">
          <td>${codigo}</td>
          <td>${nombre}</td>
          <td><input type="number" min="1" value="${cantidad}" class="cantidad-input" data-cod="${codigo}" style="width: 70px;"></td>
          <td><input type="number" min="0" value="${stock}" class="stock-input" data-cod="${codigo}" style="width: 70px;"></td>
          <td><input type="number" min="0" value="${consumo}" class="consumo-input" data-cod="${codigo}" style="width: 70px;"></td>
          <td><input type="number" min="0" value="${meses}" class="meses-input" data-cod="${codigo}" style="width: 70px;" readonly></td>
          <td>
            <select class="situacion-input" data-cod="${codigo}" style="width: 130px;">
              <option value="">-</option>
              <option value="Desabastecido" ${situacion === 'Desabastecido' ? 'selected' : ''}>Desabastecido</option>
              <option value="Sub Stock" ${situacion === 'Sub Stock' ? 'selected' : ''}>Sub Stock</option>
              <option value="Norma Stock" ${situacion === 'Norma Stock' ? 'selected' : ''}>Norma Stock</option>
              <option value="Sobre Stock" ${situacion === 'Sobre Stock' ? 'selected' : ''}>Sobre Stock</option>
            </select>
          </td>
          <td>
            <button type="button" class="btn-secondary eliminar-item"
              data-cod="${codigo}" data-pedido="N.A." data-tramite="${iCodTramite}">
              Eliminar
            </button>
          </td>
        </tr>
      `);
      alert(" Ítem agregado correctamente.");
    } else {
      alert("Meses de consumo calculado: " + data.message);
    }
  })
  .catch(err => alert(" Error de red: " + err));
}

// ACTUALIZAR LOS NUEVOS CAMPOS
function actualizarCampoManual(campo, valor, codigo) {
  fetch('actualizarCampoItemManual.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&codigoItem=${codigo}&campo=${campo}&valor=${encodeURIComponent(valor)}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status !== 'ok') {
       alert("Revise los datos: " + data.message);
    }
  });
}

$(document).on('change', '.cantidad-input', function () {
  actualizarCampoManual('cantidad', $(this).val(), $(this).data('cod'));
});
$(document).on('change', '.stock-input', function () {
  const cod = $(this).data('cod');
  const stockVal = parseFloat($(this).val()) || 0;
  actualizarCampoManual('stock', stockVal, cod);

  const consumoVal = parseFloat($(`.consumo-input[data-cod="${cod}"]`).val()) || 0;
  const meses = consumoVal > 0 ? (stockVal / consumoVal).toFixed(2) : 0;
  $(`.meses-input[data-cod="${cod}"]`).val(meses);
  actualizarCampoManual('meses_consumo', meses, cod);
});
$(document).on('change', '.consumo-input', function () {
  const cod = $(this).data('cod');
  const consumoVal = parseFloat($(this).val()) || 0;
  actualizarCampoManual('consumo_promedio', consumoVal, cod);

  const stockVal = parseFloat($(`.stock-input[data-cod="${cod}"]`).val()) || 0;
  const meses = consumoVal > 0 ? (stockVal / consumoVal).toFixed(2) : 0;
  $(`.meses-input[data-cod="${cod}"]`).val(meses);
  actualizarCampoManual('meses_consumo', meses, cod);
});
// .meses-input es solo de lectura
$(document).on('change', '.situacion-input', function () {
  actualizarCampoManual('situacion', $(this).val(), $(this).data('cod'));
});


// FIN ACTUALIZAR NUEVOS CAMPOS

// ✏️ 5. Escuchar cambios de cantidad en inputs de tabla
$(document).on('change', '.cantidad-input', function () {
  const nuevaCantidad = parseInt($(this).val());
  const codItem = $(this).data('cod');

  if (isNaN(nuevaCantidad) || nuevaCantidad < 1) {
    alert('Cantidad inválida. Debe ser mayor a 0.');
    return;
  }

  fetch('guardarItemManual.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&codigoItem=${encodeURIComponent(codItem)}&nuevaCantidad=${nuevaCantidad}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'inserted') {
      alert(' Ítem agregado correctamente');
    } else if (data.status === 'updated') {
      console.log('Cantidad actualizada');
    } else {
      alert(' Error: ' + data.message);
    }
  })
  .catch(err => alert('Error de red: ' + err));
});
// 🔄 6A. Al cambiar tipo de requerimiento (Bien o Servicio)
$('#tipoBien').on('change', function () {
  const nuevoTipo = $(this).val();

  fetch('eliminarItemsSigaPorTipo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&tipoNuevo=${nuevoTipo}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      $('#tablaSiga tbody, #tablaSigaAgregados tbody, #tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();
      $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');
    }
  });
});

// 🔄 6B. Al cambiar ¿Tiene pedido SIGA?
$('#pedidoSiga').on('change', function () {
  const modo = $(this).val() === '1' ? 'sin' : 'con';

  fetch('eliminarItemsSigaPorPedido.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&modo=${modo}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      $('#tablaSiga tbody, #tablaSigaAgregados tbody, #tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();
      $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');

      if (modo === 'con') {
        $('#seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado').hide();
        $('#busquedaItemSinPedido').show();
      } else {
        $('#seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado').show();
        $('#busquedaItemSinPedido').hide();
      }
    }
  });
});

// 🔄 6C. Al cambiar tipo de documento (por ejemplo, de 109 a otro)
let tipoAnterior = $('#tipoDocumento').val(); // inicial
$('#tipoDocumento').on('change', function () {
  const tipo = $(this).val();

  if (tipo === '109' || tipo === '108') {
    $('#grupoRequerimiento').show();
  } else {
    if (confirm('⚠️ Al cambiar de tipo se eliminarán todos los ítems SIGA. ¿Desea continuar?')) {
      fetch('eliminarItemsSigaTramite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `iCodTramite=${iCodTramite}`
      })
      .then(res => res.json())
      .then(() => {
        $('#grupoRequerimiento, #seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado, #busquedaItemSinPedido').hide();
        $('#tipoBien, #pedidoSiga').val('');
        $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');
        $('#tablaSiga tbody, #tablaSigaAgregados tbody, #tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();
        tipoAnterior = tipo; // actualizar si aceptó
      });
    } else {
      $('#tipoDocumento').val(tipoAnterior); // 🔁 volver al tipo anterior dinámicamente
    }
  }
});
// 🔍 Evaluar tipo de documento al cargar (caso al pasar desde registroOficina)
$(document).ready(function () {
  const tipoInicial = $('#tipoDocumento').val();
  if (tipoInicial === '109' || tipoInicial === '108') {
    $('#grupoRequerimiento').show();

    const tienePedido = $('#pedidoSiga').val();
    if (tienePedido === '1') {
      $('#seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado').show();
      $('#busquedaItemSinPedido').hide();
    } else if (tienePedido === '0') {
      $('#seccionPedidoSiga, #resultadoBusqueda').hide();
      $('#resultadoAgregado, #busquedaItemSinPedido').show();
    }

  } else {
    $('#grupoRequerimiento').hide(); // Seguridad extra
  }
});


//ELIMINAR ITEM EN ESPECIFICO
$(document).on('click', '.eliminar-item', function () {
  const cod = $(this).data('cod') || '*';
  const pedido = $(this).data('pedido');
  const tramite = $(this).data('tramite');

  if (!confirm("¿Deseas eliminar este ítem?")) return;

  fetch('eliminarItemManual.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${tramite}&codigoItem=${cod}&pedidoSiga=${pedido}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'deleted') {
      $(`tr[data-clave$="${cod}"]`).remove();
      alert(" Ítem eliminado correctamente.");
    } else {
      alert("No se pudo eliminar: " + data.message);
    }
  })
  .catch(err => alert("❌ Error al eliminar: " + err));
});
// FIN DE ELIMINAR ITEM EN ESPECIFICO
 
let pedidosSigaData = {};  
$('#buscarSigaBtn').on('click', function () {
  const nro = $('#nroPedidoSIGA').val().trim();
  const tipoBien = $('#tipoBien').val();

  if (!nro || !tipoBien) return alert("Debe ingresar N° Pedido y tipo de bien.");

  $.get('buscar_pedido_siga.php', { nro_pedido: nro, tipo_bien: tipoBien }, function (res) {
    if (res.status === 'success') {
      const rows = res.datos.map(item => `
        <tr>
          <td>${item.NRO_PEDIDO}</td>
          <td>${item.CODIGO_ITEM}</td>
          <td>${item.NOMBRE_ITEM}</td>
          <td>${item.CANT_SOLICITADA}</td>
        </tr>`).join('');

      $('#tablaSiga tbody').html(rows);
      pedidosSigaData[`${nro}_${tipoBien}`] = res.datos;
      $('#resultadoBusqueda').show();
    } else {
      alert("No se encontraron ítems para ese pedido SIGA.");
    }
  }, 'json');
});

$('#agregarPedidoBtn').on('click', function () {
  const nro = $('#nroPedidoSIGA').val().trim();
  const tipoBien = $('#tipoBien').val();
  const clave = `${nro}_${tipoBien}`;
  const datos = pedidosSigaData[clave];

  if (!datos || datos.length === 0) return alert("Primero debe buscar un pedido SIGA válido.");
  if ($(`#tablaSigaAgregados tr[data-pedido='${clave}']`).length > 0) {
    return alert("Este pedido SIGA ya fue agregado.");
  }

  const totalItems = datos.length;
  let filas = '';

  datos.forEach((item, idx) => {
    const cantidadEntera = parseInt(item.CANT_SOLICITADA.split('.')[0]); // corta todo lo decimal

    // Llamada a guardarItemPedidoSiga.php
    fetch('guardarItemPedidoSiga.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `iCodTramite=${iCodTramite}&pedidoSiga=${nro}&codigoItem=${item.CODIGO_ITEM}&cantidad=${cantidadEntera}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.status !== 'inserted' && data.status !== 'updated') {
        alert("❌ Error al guardar ítem SIGA: " + data.message);
      }
    });

    const hidden = `<input type="hidden" name="pedidosSiga[]" value="${nro}_${tipoBien}_${item.CODIGO_ITEM}_${cantidadEntera}">`;

    filas += `
      <tr data-pedido="${clave}">
        ${idx === 0 ? `<td rowspan="${totalItems}">${nro}</td>` : ''}
        <td>${item.CODIGO_ITEM}</td>
        <td>${item.NOMBRE_ITEM}</td>
        <td>${cantidadEntera}</td>  
        ${idx === 0 ? `<td rowspan="${totalItems}"><button type="button" class="btn-secondary" onclick="eliminarPedidoSiga('${clave}')">Eliminar</button></td>` : ''}
        ${hidden}
      </tr>
    `;
  });

  $('#tablaSigaAgregados tbody').append(filas);
  $('#tablaSiga tbody').empty();
  $('#nroPedidoSIGA').val('');
});


function eliminarPedidoSiga(clave) {
  const [pedidoSiga] = clave.split('_');

  if (!confirm("¿Deseas eliminar este pedido SIGA completo y todos sus ítems?")) return;

  fetch('eliminarPedidoSigaTramite.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `iCodTramite=${iCodTramite}&pedidoSiga=${pedidoSiga}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'deleted') {
      $(`#tablaSigaAgregados tr[data-pedido='${pedidoSiga}']`).remove();
      delete pedidosSigaData[clave];
      alert("🗑️ Pedido eliminado correctamente.");
    } else {
      alert("⚠️ No se pudo eliminar: " + data.message);
    }
  })
  .catch(err => alert("❌ Error de red: " + err));
}

// ==========================
// 🔁 FIN: JS PARA SIGA EN EDITOR
// ==========================


////JS PARA AGREGAR ITEMS SIGA AL PDF 
document.addEventListener("DOMContentLoaded", function () {
    const btnSiga = document.getElementById("btnInsertarSiga");
    if (btnSiga) {
        btnSiga.addEventListener("click", function () {
            const iCodTramite = <?= (int)$iCodTramite ?>;

            fetch('generarBloqueSigaHTML.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'iCodTramite=' + iCodTramite
            })
            .then(response => response.text())
            .then(html => {
                tinymce.get('descripcion').execCommand('mceInsertContent', false, html);
                alert("Contenido SIGA insertado en el documento.");
            })
            .catch(err => {
                alert("Error al insertar data SIGA: " + err.message);
            });
        });
    }
});
////FIN JS PARA AGREGAR ITEMS SIGA AL PDF 

// INICIO JS PARA TOSTADAS

function showToast(type, msg) {
  const toast = document.getElementById('alert-toast');
  toast.className = ''; // limpia clases previas
  toast.classList.add(type); // aplica clase tipo
  toast.textContent = msg;
  toast.style.display = 'block';
  toast.style.animation = 'none';
  void toast.offsetWidth; // reinicia animación
  toast.style.animation = 'fadeInOut 2s forwards';
  setTimeout(() => {
    toast.style.display = 'none';
  }, 2000);
}



// FIN JS PARA TOSTADAS
    </script>
    <script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>
    <div id="alert-toast" style="display: none;"></div>
</body>
</html>