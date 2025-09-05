<?php
include("headFlujo.php");
include("conexion/conexion.php");

$iCodTramite = isset($_GET['iCodTramite']) ? intval($_GET['iCodTramite']) : 0;

// ─────────────────────────────────────────────────────────────
// 1) DATOS GENERALES DEL TRÁMITE
// ─────────────────────────────────────────────────────────────
$sqlTramite = "SELECT 
    t.EXPEDIENTE,
    t.cCodificacion,
    t.cAsunto,
    t.fFecRegistro,
    t.cObservaciones,
    t.documentoElectronico,
    t.cCodTipoDoc,
    td.cDescTipoDoc,
    o.cSiglaOficina
FROM Tra_M_Tramite t
LEFT JOIN Tra_M_Tipo_Documento td ON t.cCodTipoDoc = td.cCodTipoDoc
LEFT JOIN Tra_M_Oficinas o ON t.iCodOficinaRegistro = o.iCodOficina
WHERE t.iCodTramite = ?";

$stmtTramite = sqlsrv_query($cnx, $sqlTramite, [$iCodTramite]);
if ($stmtTramite === false) die('Error al consultar Trámite: '.print_r(sqlsrv_errors(), true));
$info = sqlsrv_fetch_array($stmtTramite, SQLSRV_FETCH_ASSOC);
if (!$info) die('No se encontró el trámite con iCodTramite='.$iCodTramite);

// ─────────────────────────────────────────────────────────────
// 2) FLUJO (SELECT con todos los datos + iCodMovimientoDerivo/Rel)
//    Usamos iCodMovimientoDerivo como padre; si NULL, iCodMovimientoRel;
//    si ambos NULL ⇒ raíz (p.ej. tu 207774).
// ─────────────────────────────────────────────────────────────
$sqlFlujo = "
SELECT
    TM.iCodMovimiento,
    TM.iCodTramite,
    TM.iCodTramiteDerivar,
    TM.iCodTramiteRel,
    TM.iCodMovimientoDerivo,
    TM.iCodMovimientoRel,
    TM.cFlgTipoMovimiento,
    TM.nEstadoMovimiento,
    TM.fFecDerivar,
    TM.fFecRecepcion,
    TM.cAsuntoDerivar,
    TM.cObservacionesDerivar,
    TM.iCodIndicacionDerivar,
    I.cIndicacion,
    O1.cSiglaOficina AS OficinaOrigenAbbr,
    O2.cSiglaOficina AS OficinaDestinoAbbr,

    -- Estado como en la bandeja antigua
    CASE
        WHEN TM.fFecRecepcion IS NULL THEN
            CASE
                WHEN TM.cFlgTipoMovimiento = 5 THEN ''
                WHEN TM.nEstadoMovimiento = 7 THEN 'Anulado'
                ELSE 'Pendiente'
            END
        ELSE
            CASE TM.nEstadoMovimiento
                WHEN 1 THEN 'En Proceso'
                WHEN 2 THEN 'Derivado'
                WHEN 3 THEN 'Delegado'
                WHEN 4 THEN 'Respondido'
                WHEN 5 THEN 'Finalizado'
                ELSE 'En Proceso'
            END
    END AS EstadoTexto,

    -- Tramite de la fila (Derivar si existe, si no el propio)
    Tpdf.iCodTramite           AS iCodTramitePDF,
    Tpdf.documentoElectronico  AS DocElectronico,
    Tpdf.descripcion           AS DocDescripcion,
    TDdoc.cDescTipoDoc,
    Tpdf.cCodificacion,
    Tpdf.nFlgTipoDoc,
    Tpdf.nFlgFirma,

    -- Nombre ‘legacy’ cuando no hay documentoElectronico
    CASE
        WHEN Tpdf.documentoElectronico IS NOT NULL AND LTRIM(RTRIM(Tpdf.documentoElectronico)) <> ''
            THEN Tpdf.documentoElectronico
        ELSE
            (CASE WHEN Tpdf.nFlgTipoDoc = 2 THEN 'I_' ELSE 'S_' END) +
            REPLACE(REPLACE(LTRIM(RTRIM(TDdoc.cDescTipoDoc)),' ','_'),'/','-') +
            '_N_' + LTRIM(RTRIM(Tpdf.cCodificacion)) +
            CASE WHEN Tpdf.nFlgFirma = 0 THEN '' ELSE '[FP]' END + '.pdf'
    END AS DocumentNameOldStyle,

    -- Complementarios (cuenta)
    ISNULL(D.CountDigitales, 0) AS ComplementariosCount
FROM Tra_M_Tramite_Movimientos TM
INNER JOIN Tra_M_Tramite T
    ON T.iCodTramite = TM.iCodTramite
LEFT JOIN Tra_M_Oficinas O1
    ON O1.iCodOficina = TM.iCodOficinaOrigen
LEFT JOIN Tra_M_Oficinas O2
    ON O2.iCodOficina = TM.iCodOficinaDerivar
LEFT JOIN Tra_M_Indicaciones I
    ON I.iCodIndicacion = TM.iCodIndicacionDerivar

-- Tramite ‘de la fila’
OUTER APPLY (
    SELECT TOP (1) *
    FROM Tra_M_Tramite Tp
    WHERE Tp.iCodTramite = ISNULL(TM.iCodTramiteDerivar, TM.iCodTramite)
) AS Tpdf

LEFT JOIN Tra_M_Tipo_Documento TDdoc
    ON TDdoc.cCodTipoDoc = Tpdf.cCodTipoDoc

-- Complementarios del tramite ‘de la fila’
OUTER APPLY (
    SELECT COUNT(*) AS CountDigitales
    FROM Tra_M_Tramite_Digitales Dg
    WHERE Dg.iCodTramite = ISNULL(TM.iCodTramiteDerivar, TM.iCodTramite)
) AS D

WHERE (TM.iCodTramite = ? OR TM.iCodTramiteRel = ?)
ORDER BY TM.iCodMovimiento ASC";   

$stmtFlujo = sqlsrv_query($cnx, $sqlFlujo, [$iCodTramite, $iCodTramite]);
if ($stmtFlujo === false) die('Error al consultar Flujo: '.print_r(sqlsrv_errors(), true));

// Cargamos todos los movimientos
$rows = [];
while ($r = sqlsrv_fetch_array($stmtFlujo, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

// ─────────────────────────────────────────────────────────────
// 3) ARMADO DEL ÁRBOL (ramas plegables)
//    Regla de parentesco:
//      parentId = iCodMovimientoDerivo ? iCodMovimientoDerivo : iCodMovimientoRel
//    Si parentId NULL → raíz (p.ej. 176006 y 207774 del ejemplo).
// ─────────────────────────────────────────────────────────────
$nodes = [];
$children = [];
$roots = [];

// índice por id
foreach ($rows as $r) {
    $id = (int)$r['iCodMovimiento'];
    $nodes[$id] = $r;
    $children[$id] = []; // inicializa
}

// segundo pase: enlazamos
foreach ($nodes as $id => $n) {
    $parentId = null;
    if (!empty($n['iCodMovimientoDerivo'])) {
        $parentId = (int)$n['iCodMovimientoDerivo'];
    } elseif (!empty($n['iCodMovimientoRel'])) {
        $parentId = (int)$n['iCodMovimientoRel'];
    }

    if ($parentId !== null && isset($nodes[$parentId])) {
        $children[$parentId][] = $id;
    } else {
        // raíz (sin padre en el set: inicio o referencia)
        $roots[] = $id;
    }
}

// ─────────────────────────────────────────────────────────────
// 4) ÍTEMS SIGA (opcional) → No mostrar sección si no hay datos
// ─────────────────────────────────────────────────────────────
$itemsSIGA = [];
$expediente = $info['EXPEDIENTE'] ?? null;
if (!empty($expediente) && $expediente !== '1') {
    $sqlSIGA = "SELECT pedido_siga, codigo_item, cantidad, [extension]
                FROM Tra_M_Tramite_SIGA_Pedido
                WHERE EXPEDIENTE = ?";
    $stmtSIGA = sqlsrv_query($cnx, $sqlSIGA, [$expediente]);
    if ($stmtSIGA) {
        while ($p = sqlsrv_fetch_array($stmtSIGA, SQLSRV_FETCH_ASSOC)) {
            $itemsSIGA[] = $p; // si tienes $sigaConn puedes enriquecer como antes
        }
    }
}

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return ($d instanceof DateTimeInterface) ? $d->format('d/m/Y H:i:s') : '—'; }

function renderDocPrincipal($row){
    $principal = trim((string)($row['DocElectronico'] ?? ''));
    $calc      = $row['DocumentNameOldStyle'] ?? '';
    $docFile   = $principal !== '' ? $principal : $calc;

    if ($docFile === '') {
        return '<span class="badge">Sin documento principal</span>';
    }
    return '<a href="https://tramite.heves.gob.pe/STD/cDocumentosFirmados/'.urlencode($docFile).'" class="chip-adjunto" target="_blank" title="'.h($docFile).'">
              <span class="material-icons chip-icon">picture_as_pdf</span>
              <span class="chip-text">'.h($docFile).'</span>
            </a>';
}

function renderComplementarios($cnx, $row){
    $cnt   = (int)($row['ComplementariosCount'] ?? 0);
    $tid   = (int)($row['iCodTramitePDF'] ?? 0);

    if ($cnt === 0) return '<span class="badge">Ninguno</span>';

    if ($cnt === 1) {
        $s = sqlsrv_query($cnx, "SELECT TOP(1) cNombreNuevo, cNombreOriginal, iCodTramite 
                                 FROM Tra_M_Tramite_Digitales 
                                 WHERE iCodTramite = ?", [$tid]);
        if ($s && ($uno = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC))) {
            $nombre = $uno['cNombreNuevo'] ?: ($uno['iCodTramite'].'-'.preg_replace("/\ /","_",trim($uno["cNombreOriginal"])));
            return '<a href="https://tramite.heves.gob.pe/STD/cAlmacenArchivos/'.urlencode($nombre).'" class="chip-adjunto" target="_blank" title="'.h($nombre).'">
                      <span class="material-icons chip-icon chip-doc">article</span>
                      <span class="chip-text">'.h($nombre).'</span>
                    </a>';
        }
        return '<span class="badge">1 archivo</span>';
    }

    return '<a href="detalleComplementarios.php?iCodTramite='.$tid.'" class="chip-adjunto" title="Documentos Complementarios">
              <span class="material-icons chip-icon chip-doc">article</span>
              <span class="chip-text">'.$cnt.' archivos</span>
            </a>';
}

// Render recursivo de ramas
function renderNodo($id, $nodes, $children, $nivel = 0){
    $n = $nodes[$id];

    // Etiquetas de relación
    $esReferencia = empty($n['iCodMovimientoDerivo']) && empty($n['iCodMovimientoRel']);
    $badge = $esReferencia ? ' <span class="badge">Referencia</span>' : (
              !empty($n['iCodMovimientoDerivo']) ? '' : ' <span class="badge">Relacionado</span>'
            );

    // SUMMARY (línea compacta)
    echo "<details".($nivel===0?' open':'').">";
    echo "<summary style='font-weight:600; padding-left:".($nivel*18)."px'>";
    echo h($n['OficinaOrigenAbbr'])." → ".h($n['OficinaDestinoAbbr'])." — ";
    echo h($n['cAsuntoDerivar']).$badge." ";
    echo "<small style='color:#555;margin-left:8px'>#".(int)$n['iCodMovimiento']."</small>";
    echo "</summary>";

    // CUERPO DEL NODO
    echo "<div class='node-body'>";
      echo "<div class='row-flex'>";
        echo "<div class='kv'><b>Fecha Derivación:</b> ".fmtDate($n['fFecDerivar'] ?? null)."</div>";
        echo "<div class='kv'><b>Fecha Recepción:</b> ".fmtDate($n['fFecRecepcion'] ?? null)."</div>";
        echo "<div class='kv'><b>Estado:</b> ".h($n['EstadoTexto'])."</div>";
      echo "</div>";

      if (!empty($n['cIndicacion'])) {
        echo "<div class='kv'><b>Indicación:</b> ".h($n['cIndicacion'])."</div>";
      }
      if (!empty($n['cObservacionesDerivar'])) {
        echo "<div class='kv'><b>Observaciones:</b> ".h($n['cObservacionesDerivar'])."</div>";
      }

      // Documentos
      echo "<div class='row-flex'>";
        echo "<div class='kv'><b>Documento principal:</b> ".renderDocPrincipal($n)."</div>";
        echo "<div class='kv'><b>Complementarios:</b> ".renderComplementarios($GLOBALS['cnx'], $n)."</div>";
        
      echo "</div>";

      // Hijos
      if (!empty($children[$id])) {
        foreach ($children[$id] as $hijoId) {
          renderNodo($hijoId, $nodes, $children, $nivel+1);
        }
      }
    echo "</div>"; // node-body
    echo "</details>";
}

?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.chip-adjunto{display:inline-flex;align-items:center;background:#fff;border-radius:999px;padding:6px 12px;margin:4px 6px 4px 0;font-size:13px;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border:1px solid #dadce0;color:#000;text-decoration:none}
.chip-adjunto:hover{background:#f1f3f4;text-decoration:none}
.material-icons.chip-icon{font-size:18px;margin-right:8px;color:#d93025}
.material-icons.chip-doc{color:#1a73e8}
.chip-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;max-width:200px}
.detail-content{background:#fff;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:1.25rem;box-shadow:0 2px 6px rgba(0,0,0,.05);font-family:'Segoe UI',sans-serif;overflow:hidden}
.detail-header{background:#f7f9fc;padding:12px 16px;font-weight:600;font-size:15px;border-bottom:1px solid #eee}
.detail-body{padding:1rem;font-size:14px;color:#333;line-height:1.6}
.kv b{display:inline-block;min-width:160px;color:#111}
h3{font-size:18px;color:#0c2d5d;margin:1.25rem 0 .75rem;border-left:4px solid #0072CE;padding-left:10px}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;background:#eef2ff;color:#173b7a;margin-left:6px}
.row-flex{display:flex;gap:18px;flex-wrap:wrap}
.row-flex > .kv{flex:1 1 260px}
.node-body{padding:10px 16px;border-top:1px solid #eee;margin-bottom:6px}
summary{cursor:pointer;padding:8px 12px}
details{border:1px solid #eee;border-radius:8px;margin:8px 0}
details > summary:hover{background:#f8fafc}
</style>

<h3>DETALLES DEL EXPEDIENTE:  </h3>
<div class="detail-content">
  <div class="detail-header">DATOS GENERALES</div>
  <div class="detail-body">
    <div class="row-flex">
      <div class="kv"><b>Expediente:</b> <?= h($info['EXPEDIENTE'] ?? '') ?></div>
      <div class="kv"><b>Tipo de Documento:</b> <?= h($info['cDescTipoDoc'] ?? '') ?></div>
      <div class="kv"><b>Codificación:</b> <?= h($info['cCodificacion'] ?? '') ?></div>
    </div>
    <div class="row-flex">
      <div class="kv"><b>Asunto:</b> <?= h($info['cAsunto'] ?? '') ?></div>
      <div class="kv"><b>Fecha Registro:</b> <?= ($info['fFecRegistro'] instanceof DateTimeInterface ? $info['fFecRegistro']->format('d/m/Y H:i:s') : '—') ?></div>
    </div>
    <div class="kv"><b>Observaciones:</b> <?= h($info['cObservaciones'] ?? '') ?></div>
    <div class="kv" style="margin-top:8px;"><b>Doc. Principal:</b>
      <?php if (!empty($info['documentoElectronico'])): ?>
        <a href="https://tramite.heves.gob.pe/STD/cDocumentosFirmados/<?= urlencode($info['documentoElectronico']) ?>" class="chip-adjunto" target="_blank" title="<?= h($info['documentoElectronico']) ?>">
          <span class="material-icons chip-icon">picture_as_pdf</span>
          <span class="chip-text"><?= h($info['documentoElectronico']) ?></span>
        </a>
      <?php else: ?>
        <span class="badge">No disponible</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($itemsSIGA)): ?>
  <h3>DETALLES DEL REQUERIMIENTO (SIGA)</h3>
  <div class="detail-content">
    <div class="detail-header">ÍTEMS SIGA</div>
    <div class="detail-body">
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <thead style="background:#f5f5f5">
          <tr>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">PEDIDO SIGA</th>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">EXTENSIÓN</th>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">TIPO BIEN</th>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">CÓDIGO ITEM</th>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">NOMBRE ITEM</th>
            <th style="padding:8px;border-bottom:1px solid #e6e6e6;text-align:left">CANTIDAD</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($itemsSIGA as $it): ?>
            <tr>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['pedido_siga']) ?></td>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['extension']) ?></td>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['TIPO_BIEN'] ?? '') ?></td>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['codigo_item']) ?></td>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['NOMBRE_ITEM'] ?? '') ?></td>
              <td style="padding:8px;border-bottom:1px solid #eee"><?= h($it['cantidad']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<h3>FLUJO DEL EXPEDIENTE  </h3>
<div class="detail-content">
  <div class="detail-header">Movimientos</div>
  <div class="detail-body">
    <?php
    if (empty($roots)) {
      echo "<em>No hay movimientos para este expediente.</em>";
    } else {
      foreach ($roots as $rootId) {
        renderNodo($rootId, $nodes, $children, 0);
      }
    }
    ?>
  </div>
</div>
