<?php
session_start();
?>
<html lang='en'>
  <head>
    <title>SISTEMA DE TRAMITE DOCUMENTARIO</title>
    <meta http-equiv="Expires" content="0"> 
    <meta http-equiv="Last-Modified" content="0">     
    <meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta charset='UTF-8'>
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="cInterfaseUsuario_SITD/images/favicon.ico" type="image/x-icon">    
    <style>
    body {
      margin: 0;
      font-family: 'Roboto', Arial, sans-serif;
      background-color: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      overflow: hidden;
    }
    .container {
      width: 90%;
      max-width: 1120px;
      height: 72.5%;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    .container img {
      max-width: 300px;
      margin-bottom: 20px;
    }
    .container h1 {
      font-size: 24px;
      margin-bottom: 20px;
      color: #333;
    }

    .container h6 {
        color: #005a86;
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 20px;
      }

      .container select,
      .container button {
        width: 150%;
        max-width: 1000px;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 4px;
        font-size: 16px;
      }

      .container select {
        background: #F6AE2D;
        color: #fff;
        border: 1px solid #ccc;
      }
      .container button {
        /* width: 120%;
        max-width: 800px;
        padding: 10px; */
        background-color: #005a86;
        color: white;
        border: none;
        /* border-radius: 4px; */
        cursor: pointer;
      }
      .container button:hover {
        background-color: #004568;
      }

      /* Centrando el formulario y sus elementos */ 
form { 
display: flex; 
flex-direction: column; 
align-items: center; 
width: 100%; 
} 
form select, 
form button { 
width: 100%; 
max-width: 500px; /* Ajusta el tamaño para que no desborde */ 
padding: 10px; 
margin-bottom: 20px; 
border-radius: 4px; 
font-size: 16px; 
} 
form select { 
background: #F6AE2D; 
color: #fff; 
border: 1px solid #ccc; 
text-align: center; 
} 
form button { 
background-color: #005a86; 
color: white; 
border: none; 
cursor: pointer;
} 
form button:hover { 
background-color: #004568; 
} 

    .shape {
      position: absolute;
      border-radius: 50%;
    }
    .shape.shape-top-left {
      width: 400px;
      height: 400px;
      background-color: #5797ad;
      top: -200px;
      left: -200px;
      z-index: -1;
    }
    .shape.shape-bottom-right {
      width: 300px;
      height: 300px;
      background-color: #005a86;
      bottom: -150px;
      right: -150px;
      z-index: -1;
    }
  </style>
  </head>        
  <body>
  <div class="shape shape-top-left"></div>
  <div class="shape shape-bottom-right"></div>
  <div class="container">
  <img src="./img/logo.jpeg" alt="Logo Institucional">
  <!-- <img src="cInterfaseUsuario_SITD/images/logo.png" alt="Logo Institucional"> -->
        <h1>Seleccionar Perfil</h1>
        <form method="POST" action="login_next.php" name="Datos">
            <input type="hidden" value="<?php echo $_SESSION['CODIGO_TRABAJADOR'];?>" name="id_usuario">
            <?
            include_once("conexion/conexion.php");                            
            $sqlDoc = "SELECT cTipoDocIdentidad FROM Tra_M_Trabajadores
                       WHERE iCodTrabajador = '$_SESSION[CODIGO_TRABAJADOR]'";
			      $rsDoc = sqlsrv_query($cnx,$sqlDoc,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
			      $RsDoc = sqlsrv_fetch_array($rsDoc);
            if ($RsDoc[cTipoDocIdentidad] == 1 ) 
            {
                $Sr = "Sr(a):";
            }
            else 
            {
                $Sr = "";
            }
            $sqlUsr = "SELECT * FROM Tra_M_Trabajadores WHERE iCodTrabajador='$_SESSION[CODIGO_TRABAJADOR]'"; 
			      $rsUsr = sqlsrv_query($cnx,$sqlUsr,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
			      $RsUsr  = sqlsrv_fetch_array($rsUsr);
            echo "<h6>" . $Sr . " " . $RsUsr['cNombresTrabajador'] . " " . $RsUsr['cApellidosTrabajador'] . "</h6>";

            ?>
            <select name='perfil' class="form-control form-control-sm" style="background: #F6AE2D;color: #fff;margin-top: 10px; margin-bottom: 10px;"> 
            <?
                $sqlTem="SELECT iCodPerfilUsuario,(select cSiglaOficina from Tra_M_Oficinas where iCodOficina=o.iCodOficina) as sigla,
                    (select cDescPerfil from Tra_M_Perfil where iCodPerfil=o.iCodPerfil) as iCodPerfil,
                    (select cNomOficina from Tra_M_Oficinas where iCodOficina=o.iCodOficina) as iCodOficina 
                    from Tra_M_Perfil_Ususario o inner join Tra_M_Oficinas a on o.iCodOficina=a.iCodOficina
                    where iCodTrabajador='".$_SESSION['CODIGO_TRABAJADOR']."' AND a.iFlgEstado='1'
                    ORDER BY sigla, iCodOficina, iCodPerfil";
				$rsTem = sqlsrv_query($cnx,$sqlTem,array(),array( "Scrollable" => SQLSRV_CURSOR_KEYSET ));
				while ($RsTem=sqlsrv_fetch_array($rsTem))
                {
                    echo "<option value='".$RsTem["iCodPerfilUsuario"]."'>".$RsTem["sigla"].' | '.$RsTem["iCodOficina"]." | ".$RsTem["iCodPerfil"]."  </option>";
                }
				sqlsrv_free_stmt($rsTem);
                ?>                         
            </select>
            <button name="Submit" type="submit" onClick="loguear()" >Aceptar</button> 
        </form>
        <?
        switch ($_GET["alter"]) {
          case 2:
            $observacion = "<br><div class='alert alert-info' role='alert'><b>SALIDA: </b>Ud. ha salido correctamente del sistema.</div>";
          break;
          case 3:
            $observacion = "<br><div class='alert alert-danger' role='alert'><b>ERROR</b> - datos vacios<br>\"ingrese correctamente\"</div>";
          break;
          case 4:
            $observacion = "<br><div class='alert alert-danger' role='alert'>ERROR... <b>Clave incorrecta</b> o <br>es <b>Cuenta Incorrecta</b></div>";
          break;
          case 5:
            $observacion = "<br><div class='alert alert-danger' role='alert'>ERROR... <b>Usuario no autorizado</b></div>";            
            break;
        };
        echo $observacion;
        ?>
    </div>
    <div style="position: absolute;  bottom: 8px;  left: 16px;  font-size: 18px;">
    </div>
    <script language="JavaScript" type="text/javascript">
        function loguear() {
       
            document.Datos.submit();

        }
        if (document.Datos) {
            document.Datos.perfil.focus();
        }
    </script>   
    </body>
</html>