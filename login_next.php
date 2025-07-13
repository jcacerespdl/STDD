<?php session_start();

    include_once("conexion/conexion.php");
    $id_usuario =   $_POST['id_usuario'];
    $perfil     =   $_POST['perfil'];

        $sqlJefe="SELECT * FROM Tra_M_Perfil_Ususario WHERE iCodPerfilUsuario='".$perfil."'";
 		$rsJefe = sqlsrv_query($cnx,$sqlJefe,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
 		$RsJefe=sqlsrv_fetch_array($rsJefe);

        $FechaActual=date("Y-m-d")." ".date("G:i:s");
        $Fecha=date("Ymd-Gis");

        $_SESSION['iCodOficinaLogin']=$RsJefe["iCodOficina"];
        $_SESSION['iCodPerfilLogin']=$RsJefe["iCodPerfil"];

        // Guardar descripción del perfil en sesión
        $sqlPerfilNombre = "SELECT cDescPerfil FROM Tra_M_Perfil WHERE iCodPerfil = ?";
        $stmtPerfil = sqlsrv_query($cnx, $sqlPerfilNombre, [$RsJefe["iCodPerfil"]]);
        if ($stmtPerfil && $rowPerfil = sqlsrv_fetch_array($stmtPerfil, SQLSRV_FETCH_ASSOC)) {
            $_SESSION['cDescPerfil'] = $rowPerfil['cDescPerfil'];
        }
         // Fin de Guardar descripción del perfil en sesión

        $_SESSION['cCodRef']=$_SESSION['CODIGO_TRABAJADOR']."-".$RsJefe["iCodOficina"]."-".$Fecha;
        $_SESSION['cCodOfi']=$_SESSION['CODIGO_TRABAJADOR']."-".$RsJefe["iCodOficina"]."-".$Fecha;
        $_SESSION['cCodDerivo']=$_SESSION['CODIGO_TRABAJADOR']."-".$RsJefe["iCodOficina"]."-".$Fecha;

        // $sql_actualiza="update Tra_M_Trabajadores set iCodOficina='".$RsJefe["iCodOficina"]."' where iCodTrabajador='".$RsJefe['iCodTrabajador']."'";
 		// sqlsrv_query($cnx,$sql_actualiza,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
    
        // Redirección según perfil
        if ($_SESSION['iCodPerfilLogin'] == 3) {
            header("Location: bandejaFirma.php");
        } elseif ($_SESSION['iCodPerfilLogin'] == 4) {
            header("Location: bandejaProfesional.php");
        } elseif ($_SESSION['iCodPerfilLogin'] == 19) {
            header("Location: bandejaPendientes.php");
        } else {
            header("Location: main.php"); // Fallback si el perfil no coincide
        }
        exit;
?>