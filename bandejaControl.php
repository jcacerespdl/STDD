<?php
include("head.php");
include("conexion/conexion.php");

session_start();
$iCodTrabajador = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodPerfil     = $_SESSION['ID_PERFIL'] ?? null;

// Esta vista debería ser accesible solo por Dirección (asumiendo perfil 1 = Dirección)
if ($iCodPerfil != 1) {
    echo "<p>No tiene permisos para acceder a esta bandeja.</p>";
    exit;
}

// Capturar filtros de búsqueda
$filtroExpediente = $_GET['expediente'] ?? '';
$filtroExtension  = $_GET['extension'] ?? '';
$filtroAsunto     = $_GET['asunto'] ?? '';
$filtroDesde      = $_GET['desde'] ?? '';
$filtroHasta      = $_GET['hasta'] ?? '';

// Cargar opciones de tipos de documento
$tipoDocQuery = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgInterno = 1 ORDER BY cDescTipoDoc ASC";
$tipoDocResult = sqlsrv_query($cnx, $tipoDocQuery);

// Cargar opciones de oficinas
$oficinasQuery = "SELECT iCodOficina, cNomOficina FROM Tra_M_Oficinas ORDER BY cNomOficina ASC";
$oficinasResult = sqlsrv_query($cnx, $oficinasQuery);
// Consulta general sin filtrar por oficina ni estado
$sql = "
    SELECT 
        M1.iCodMovimiento,
        M1.nEstadoMovimiento,
        M1.fFecRecepcion,
        M1.iCodTrabajadorDelegado,
        M1.iCodIndicacionDelegado,
        M1.cObservacionesDelegado,
        M1.fFecDelegado,

        ISNULL(TD.expediente, T.expediente) AS expediente,
        M1.extension AS extensionMovimiento,
        ISNULL(TD.extension, T.extension) AS extensionTramite,
        ISNULL(TD.iCodTramite, T.iCodTramite) AS iCodTramite,
        T.iCodTramite AS iCodTramitePadre,
        ISNULL(TD.cCodificacion, T.cCodificacion) AS cCodificacion,
        ISNULL(TD.cAsunto, T.cAsunto) AS cAsunto,
        ISNULL(TD.fFecRegistro, T.fFecRegistro) AS fFecRegistro,
        ISNULL(TD.documentoElectronico, T.documentoElectronico) AS documentoElectronico,

        O1.cNomOficina AS OficinaOrigen,
        O2.cNomOficina AS OficinaDestino
    FROM Tra_M_Tramite_Movimientos M1
    INNER JOIN Tra_M_Tramite T ON T.iCodTramite = M1.iCodTramite
    LEFT JOIN Tra_M_Tramite TD ON TD.iCodTramite = M1.iCodTramiteDerivar
    INNER JOIN Tra_M_Oficinas O1 ON O1.iCodOficina = M1.iCodOficinaOrigen
    INNER JOIN Tra_M_Oficinas O2 ON O2.iCodOficina = M1.iCodOficinaDerivar
    WHERE 
        T.nFlgEnvio = 1
    ORDER BY ISNULL(TD.fFecRegistro, T.fFecRegistro) DESC
";

// Ejecutar consulta
$stmt = sqlsrv_query($cnx, $sql);
$tramites = [];

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $tramites[] = $row;
    }
} else {
    echo "<p>Error al ejecutar la consulta.</p>";
    exit;
}
