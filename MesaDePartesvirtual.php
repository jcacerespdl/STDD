<?php
include 'conexion/conexion.php';
 
if ($_SERVER["REQUEST_METHOD"] === "POST") {
       // Datos del formulario
    $tipoDocumento = $_POST['tipoDocumento'] ?? null;
    $nroDocumento = $_POST['nroDocumento'] ?? null;
    $celular = $_POST['celular'] ?? null;
    $correo = $_POST['correo'] ?? null;
    $apePaterno = $_POST['apePaterno'] ?? null;
    $apeMaterno = $_POST['apeMaterno'] ?? null;
    $nombres = $_POST['nombres'] ?? null;
    $departamento = $_POST['departamento'] ?? null;
    $provincia = $_POST['provincia'] ?? null;
    $distrito = $_POST['distrito'] ?? null;
    $direccion = $_POST['direccion'] ?? null;
    $ruc = $_POST['ruc'] ?? null;
    $razonSocial = $_POST['razonSocial'] ?? null;
    $tdoc_asegurado = $_POST['tdoc_asegurado'] ?? null;
    $ndoc_asegurado = $_POST['ndoc_asegurado'] ?? null;
    $cel_asegurado = $_POST['cel_asegurado'] ?? null;
    $email_asegurado = $_POST['email_asegurado'] ?? null;
    $apePaterno_asegurado = $_POST['apePaterno_asegurado'] ?? null;
    $apeMaterno_asegurado = $_POST['apeMaterno_asegurado'] ?? null;
    $nombres_asegurado = $_POST['nombres_asegurado'] ?? null;
    $asunto = $_POST['asunto'] ?? null;
    $descripcion = $_POST['descripcion'] ?? null;
    $link = $_POST['link'] ?? null;
    $cPassword = substr(str_pad(abs(crc32($nroDocumento)), 5, '0', STR_PAD_LEFT), 0, 5);
    $fechaRegistro = date_create(); // objeto DateTime
    $nombreArchivo = null;
    $archivoValido = false;
    $archivo = null;

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
      $archivo = $_FILES['archivo'];
      $nombreArchivo = basename($archivo['name']);
      $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
      $tipoMime = $archivo['type'];
      $pesoMaximo = 10 * 1024 * 1024;
  
      if ($extension !== 'pdf' || stripos($tipoMime, 'pdf') === false) {
          echo "<script>alert('El archivo debe ser un PDF válido.');</script>";
          $nombreArchivo = null;
      } elseif ($archivo['size'] > $pesoMaximo) {
          echo "<script>alert('El archivo excede los 10MB permitidos.');</script>";
          $nombreArchivo = null;
      } else {
          $archivoValido = true;
      }
  }

    // Usando OUTPUT para recuperar el ID insertado
    $query = "INSERT INTO Tra_M_Tramite (
        nFlgTipoDoc, iCodOficinaRegistro, cAsunto, cObservaciones,  
        cTipoDocumentoSolicitante, cNumeroDocumentoSolicitante, cCelularSolicitante, cCorreoSolicitante, 
        cApePaternoSolicitante, cApeMaternoSolicitante, cNombresSolicitante,
        cDepartamentoSolicitante, cProvinciaSolicitante, cDistritoSolicitante, cDireccionSolicitante, 
        cRUCEntidad, cRazonSocialEntidad,
        cTipoDocumentoAsegurado, cNumeroDocumentoAsegurado, cCelularAsegurado, cCorreoAsegurado,
        cApePaternoAsegurado, cApeMaternoAsegurado, cNombresAsegurado, cLinkArchivo, documentoElectronico,
        fFecRegistro, cPassword, nflgenvio
    ) OUTPUT INSERTED.iCodTramite
    VALUES (1, 236, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,0)";

    $params = [
        $asunto, $descripcion,
        $tipoDocumento, $nroDocumento, $celular, $correo,
        $apePaterno, $apeMaterno, $nombres,
        $departamento, $provincia, $distrito, $direccion,
        $ruc, $razonSocial,
        $tdoc_asegurado, $ndoc_asegurado, $cel_asegurado, $email_asegurado,
        $apePaterno_asegurado, $apeMaterno_asegurado, $nombres_asegurado,
        $link, $nombreArchivo, $fechaRegistro, $cPassword
         
    ];

    echo "<script>console.group('Parámetros enviados al INSERT');</script>";
    foreach ($params as $index => $valor) {
        $val = is_null($valor) ? 'NULL' : addslashes($valor);
        echo "<script>console.log('[$index] = \"$val\"');</script>";
    }
    echo "<script>console.groupEnd();</script>";

    $stmt = sqlsrv_prepare($cnx, $query, $params);

    if (sqlsrv_execute($stmt)) {
        // Obtener el iCodTramite desde OUTPUT
        $iCodTramite = null;
        if (sqlsrv_fetch($stmt)) {
            $iCodTramite = sqlsrv_get_field($stmt, 0);
            $expediente = 'E' . str_pad($iCodTramite, 9, '0', STR_PAD_LEFT);
            $extension = 1;
            echo "<script>console.log('iCodTramite generado: $iCodTramite');</script>";
                    // Generar expediente y extensión
        $expediente = 'E' . str_pad($iCodTramite, 9, '0', STR_PAD_LEFT);
        $extension = 1;

        // Actualizar Tra_M_Tramite con expediente y extensión
        $updateTramite = "UPDATE Tra_M_Tramite SET expediente = ?, extension = ? WHERE iCodTramite = ?";
        sqlsrv_query($cnx, $updateTramite, [$expediente, $extension, $iCodTramite]);
        }

       // Subir archivo si corresponde
       if ($archivoValido && $iCodTramite) {
        $nombreSinEspacios = preg_replace('/\s+/', '_', pathinfo($nombreArchivo, PATHINFO_FILENAME));
        $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
        $archivoFinal = $iCodTramite . '-' . $nombreSinEspacios . '.' . $extension;
        $rutaDestino = __DIR__ . "/cAlmacenArchivos/" . $archivoFinal;

        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            echo "<script>console.log('Archivo subido correctamente a: $archivoFinal');</script>";
        } else {
            echo "<script>alert('No se pudo mover el archivo al servidor.');</script>";
        }
    }

        // Insertar en movimientos
        if ($iCodTramite) {
            $fechaActual = date('Y-m-d H:i:s');
            $expediente = 'E' . str_pad($iCodTramite, 9, '0', STR_PAD_LEFT);
            $movQuery = "INSERT INTO Tra_M_Tramite_Movimientos (
                iCodTramite, iCodTrabajadorRegistro, iCodOficinaOrigen, iCodOficinaDerivar, fFecDerivar,   expediente, nestadomovimiento, extension
            ) VALUES (?, 1456, 236, 46, ?,   ?, 0, 1)";
            $movParams = [$iCodTramite, $fechaActual, $expediente];

            if (sqlsrv_query($cnx, $movQuery, $movParams)) {
              echo "<script>window.location.href = 'MesaDePartes_confirmacion.php?id=$iCodTramite&clave=$cPassword';</script>";
              echo "<script>alert('Trámite registrado pero error al guardar movimiento.');</script>";
            }
        }

            } else {
        echo "<script>alert('Error al registrar el trámite');</script>";
        if (($errors = sqlsrv_errors()) != null) {
            echo "<script>console.group('Errores de SQL Server');</script>";
            foreach ($errors as $error) {
                $msg = "SQLSTATE: {$error['SQLSTATE']} - Code: {$error['code']} - Message: {$error['message']}";
                echo "<script>console.error(" . json_encode($msg) . ");</script>";
            }
            echo "<script>console.groupEnd();</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Mesa de Partes HEVES</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    body {
          font-family: 'Montserrat', sans-serif;
          margin: 0;
          background: #fff;
        }
        .form-wrapper {
          display: flex;
          flex-direction: column;
          gap: 30px;
          max-width: 1200px;
        }
        .row {
          display: flex;
          gap: 20px;
          flex-wrap: wrap;
        }
      .input-container {
          position: relative;
          flex: 1;
          min-width: 250px;
        }

        .input-container.input-dni {
          flex: 0 0 220px;
          min-width: unset;
        }

        .input-container input {
          width: 100%;
          padding: 20px 40px 8px 12px;
          font-size: 15px;
          border: 1px solid #ccc;
          border-radius: 4px;
          background: #fff;
          box-sizing: border-box;
          transition: background-color 0.3s;
        }
        .input-container label {
          position: absolute;
          top: 20px;
          left: 12px;
          font-size: 14px;
          color: #666;
          padding: 0 4px;
          pointer-events: none;
          transform: translateY(-50%);
          transition: all 0.2s ease-in-out;
          background: transparent;
        }
        .input-container label {
          background: #f0f0f0;
        }
        .input-container input:disabled {
          background-color: #f0f0f0;
          cursor: not-allowed;
        }
        .input-container input:enabled + label {
          background: #fff;
        }
        .input-container input:focus + label,
        .input-container input:not(:placeholder-shown) + label {
          top: 0px;
          font-size: 12px;
          color: #333;
        }
        
        .input-container.select-flotante {
          position: relative;
        }

        .input-container.select-flotante select {
          width: 100%;
          padding: 20px 12px 8px;
          font-size: 15px;
          border: 1px solid #ccc;
          border-radius: 4px;
          background: #fff;
          box-sizing: border-box;
          appearance: none;
          -webkit-appearance: none;
          -moz-appearance: none;
          color: #000;
        }
        
        /* Asegura que el label esté en la misma posición que en inputs */
        .input-container.select-flotante label {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: 14px;
            color: #666;
            background-color: #fff;
            padding: 0 4px;
            pointer-events: none;
            transition: 0.2s ease all;
          }

          /* Hace flotar el label al seleccionar o al hacer focus */
          .input-container.select-flotante select:focus + label,
          .input-container.select-flotante select:valid + label {
            top: 0;
            font-size: 12px;
            color: #333;
            transform: translateY(-50%);
          }

          /* Color gris cuando no se ha seleccionado nada */
          .input-container.select-flotante select:required:invalid {
            color: #aaa;
          }

        .material-icons {
          position: absolute;
          right: 10px;
          top: 50%;
          transform: translateY(-50%);
          color: #888;
          pointer-events: none;
        }
        .dropdown {
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background: white;
          border: 1px solid #ccc;
          border-top: none;
          max-height: 150px;
          overflow-y: auto;
          z-index: 100;
          display: none;
        }
        .dropdown div {
          padding: 8px 12px;
          cursor: pointer;
        }
        .dropdown div:hover {
          background-color: #f0f0f0;
        }
        .section { display: none; flex-direction: column; gap: 20px; }
        .radio-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .radio-group label { font-weight: normal; }
        
  </style>
</head>
<body>

<?php include 'header_mesadepartes.php'; ?>

<div style="margin: 40px auto; max-width: 1000px; background: white; border: 1px solid #ccc; border-radius: 10px; padding: 30px;">
  <h2 style="text-align:center;">MESA DE PARTES DIGITAL</h2>
  <h3 style="text-align:center;">HOSPITAL EMERGENCIAS VILLA EL SALVADOR</h3>
  <p style="text-align:center;">
    Puedes enviar tus documentos mediante la MESA DE PARTES DIGITAL del HEVES, las 24 horas del día.<br>
    Los documentos ingresados los sábados, domingos y feriados o cualquier otro día inhábil,
    se consideran recibidos al día siguiente hábil de su envío.
  </p>
  <p style="text-align:center; font-weight:bold; color:#1b53b2;">➤ Antes de iniciar el trámite, haz clic aquí</p>
</div>

  <div style="margin:0 auto; max-width:1000px; background:white; border:1px solid #ccc; border-radius:10px; padding:40px;">

<form class="form-wrapper" method="POST" enctype="multipart/form-data">
  <h3 style="margin-bottom: 0;">Datos del Solicitante</h3>
    <div class="row">
          <div class="input-container select-flotante">
            <select id="tipoDocumento" name="tipoDocumento" required>
              <option value="" disabled selected hidden> </option>
              <option value="DNI">DNI</option>
              <option value="CEX">CARNÉ DE EXTRANJERÍA</option>
              <option value="PAS">PASAPORTE</option>
            </select>
            <label for="tipoDocumento">Tipo de Documento</label>
            <span class="material-icons">description</span>
          </div>          
          <div class="input-container input-dni">
            <input type="text" id="nroDocumento" name="nroDocumento" placeholder=" " maxlength="8" required>
            <label for="nroDocumento">Nro de Documento</label>
          </div>
          <div style="display: flex; align-items: flex-end;">
            <button type="button" id="btnBuscarDNI" style="padding: 12px 16px; background: #364897; color: white; border: none; border-radius: 4px; height: 48px;">
              Buscar
            </button>
          </div>
          <div class="input-container">
            <input type="text" id="celular" name="celular" placeholder=" " autocomplete="off" >
            <label for="celular">Celular</label>
            <span class="material-icons">phone</span>
          </div>
          <div class="input-container">
            <input type="email" id="correo" name="correo" placeholder=" " autocomplete="off" >
            <label for="correo">Correo</label>
            <span class="material-icons">mail</span>
          </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" id="apePaterno" name="apePaterno" placeholder=" " autocomplete="off" >
        <label for="apePaterno">Apellido Paterno</label>
        <span class="material-icons">person</span>
      </div>
      <div class="input-container">
        <input type="text" id="apeMaterno" name="apeMaterno" placeholder=" " autocomplete="off" >
        <label for="apeMaterno">Apellido Materno</label>
        <span class="material-icons">person</span>
      </div>
      <div class="input-container">
        <input type="text" id="nombres" name="nombres" placeholder=" " autocomplete="off" >
        <label for="nombres">Nombres</label>
        <span class="material-icons">person</span>
      </div>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" id="departamento" name="departamento" placeholder=" " autocomplete="off">
        <label for="departamento">Departamento</label>
        <span class="material-icons">search</span>
        <div id="dropdown-departamento" class="dropdown"></div>
      </div>
      <div class="input-container">
        <input type="text" id="provincia" name="provincia" placeholder=" " autocomplete="off" disabled>
        <label for="provincia">Provincia</label>
        <span class="material-icons">search</span>
        <div id="dropdown-provincia" class="dropdown"></div>
      </div>
      <div class="input-container">
        <input type="text" id="distrito" name="distrito" placeholder=" " autocomplete="off" disabled>
        <label for="distrito">Distrito</label>
        <span class="material-icons">search</span>
        <div id="dropdown-distrito" class="dropdown"></div>
      </div>
  </div>

    <div class="row">
      <div class="input-container">
        <input type="text" id="direccion" name="direccion" placeholder=" " autocomplete="off">
        <label for="direccion">Dirección</label>
        <span class="material-icons">home</span>
      </div>
    </div>

    <div class="row">
      <label><strong>¿Representa usted a una entidad?</strong></label>
        <div class="radio-group">
          <label><input type="radio" name="entidad" value="si"> Sí</label>
          <label><input type="radio" name="entidad" value="no"> No</label>
        </div>
    </div>

    <div id="entidadCampos" class="section">
      <h3 style="margin-bottom: 0;">Datos de la Entidad</h3>
        <div class="row">
          <div class="input-container">
            <input type="text" id="ruc" name="ruc" placeholder=" " autocomplete="off">
            <label for="ruc">RUC</label>
            <span class="material-icons">apartment</span>
          </div>
          <div class="input-container">
            <input type="text" id="razonSocial" name="razonSocial" placeholder=" " autocomplete="off">
            <label for="razonSocial">Razón Social</label>
            <span class="material-icons">business</span>
          </div>
      </div>
      <div class="row">
        <label><strong>¿El trámite es de una empresa de seguros?</strong></label>
        <div class="radio-group">
          <label><input type="radio" name="seguros" value="si"> Sí</label>
          <label><input type="radio" name="seguros" value="no"> No</label>
        </div>
      </div>
      <div id="datosAsegurado" class="section">
      <h3 style="margin-bottom: 0;">Datos del Asegurado</h3>
      <div class="row">
          <div class="input-container select-flotante">
                <select id="tdoc_asegurado" name="tdoc_asegurado" >
                  <option value="" disabled selected hidden></option>
                  <option value="DNI">DNI</option>
                  <option value="CEX">CARNÉ DE EXTRANJERÍA</option>
                  <option value="PAS">PASAPORTE</option>
                </select>
                <label for="tdoc_asegurado">Tipo de Documento</label>
                <span class="material-icons">description</span>
          </div>   
          <div class="input-container">
            <input type="text" id="ndoc_asegurado" name="ndoc_asegurado" placeholder=" " autocomplete="off">
            <label for="ndoc_asegurado">Nro. de Documento</label>
            <span class="material-icons">badge</span>
          </div>
          <div class="input-container">
            <input type="text" id="cel_asegurado" name="cel_asegurado" placeholder=" " autocomplete="off">
            <label for="cel_asegurado">Celular</label>
            <span class="material-icons">phone</span>
          </div>
          <div class="input-container">
            <input type="email" id="email_asegurado" name="email_asegurado" placeholder=" " autocomplete="off">
            <label for="email_asegurado">Correo Electrónico</label>
            <span class="material-icons">mail</span>
          </div>
        </div>
        <div class="row">
      <div class="input-container">
        <input type="text" id="apePaterno_asegurado" name="apePaterno_asegurado" placeholder=" " autocomplete="off">
        <label for="apePaterno">Apellido Paterno</label>
        <span class="material-icons">person</span>
      </div>
      <div class="input-container">
        <input type="text" id="apeMaterno_asegurado" name="apeMaterno_asegurado" placeholder=" " autocomplete="off">
        <label for="apeMaterno">Apellido Materno</label>
        <span class="material-icons">person</span>
      </div>
      <div class="input-container">
        <input type="text" id="nombres_asegurado" name="nombres_asegurado" placeholder=" " autocomplete="off">
        <label for="nombres">Nombres</label>
        <span class="material-icons">person</span>
      </div>
    </div>
      </div>
    </div>

    <div id="documentosMedicos" class="section">
      <div class="row">
        <label><strong>¿Solicita documentos médicos?</strong></label>
        <div class="radio-group">
          <label><input type="radio" name="medico" value="si"> Sí</label>
          <label><input type="radio" name="medico" value="no"> No</label>
        </div>
      </div>
      <div id="datosMedicos" class="section">
        <div class="row">
 
          <div class="input-container" style="flex: 1 1 100%;">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
              <span class="material-icons" style="position: static; color: #1b53b2;">description</span>
              <a href="Solicitud_correccion_HC.docx" target="_blank" style="color: #1b53b2; text-decoration: none; font-weight: bold;">
                Descargar Solicitud Corrección Historia Clínica.docx
              </a>
            </div>
          </div>


        </div>
      </div>

      
    </div>
  
  <!-- Sección: Descripción del Trámite o Solicitud -->
    <h3 style="margin-bottom: 0;">Descripción del Trámite / Solicitud</h3>

    <div class="row">
      <div class="input-container">
        <input type="text" id="asunto" name="asunto" placeholder=" " autocomplete="off">
        <label for="asunto">Asunto del Trámite o Solicitud</label>
      </div>
    </div>

<!-- Descripción con mayor altura -->
    <div class="row">
      <div class="input-container">
        <input type="text" id="descripcion" name="descripcion" placeholder=" " autocomplete="off" maxlength="250"
          style="height: 80px; padding-top: 24px; line-height: 1.4;">
        <label for="descripcion">Descripción del Trámite o Solicitud</label>
        <small style="color: #666; display: block; margin-top: 5px;">La descripción debe contener un máximo de 250 caracteres</small>
      </div>
    </div>

    <div class="row" style="flex-direction: column; gap: 10px;">
      <label style="font-weight: bold; font-size: 15px; color: #000;">Archivo adjunto</label>
      <input type="file" id="archivo" name="archivo" accept="application/pdf"
        style="appearance: none; -webkit-appearance: none; background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 6px; outline: none; color: #333;">
      <small style="color: #666;">Peso máximo: 10 MB en total, solo se aceptan formatos pdf. Si tu archivo pesa más del peso máximo ingresa el link de descarga.</small>
    </div>

    <div class="row">
      <div class="input-container">
        <input type="text" id="link" name="link"placeholder=" " autocomplete="off">
        <label for="link">Link</label>
      </div>
    </div>

    <div class="row" style="flex-direction: column; gap: 10px;">
      <label><input type="checkbox" id="politica"> Acepto la política de privacidad</label>
      <label><input type="checkbox" id="veracidad"> Declaro bajo juramento que los datos ingresados en este formulario son verdaderos y están sujetos a lo establecido en los artículos 51 y 67 del TUO de la Ley N° 27444</label>
    </div>

    <div class="row" style="justify-content: center; margin-top: 30px;">
      <button type="submit" disabled style="padding: 12px 24px; background-color: #1b53b2; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">Enviar</button>
    </div>

</form>
</div>

  <script>
    let departamentos = [], provincias = [], distritos = [];
    let departamentoID = null, provinciaID = null;

    const fetchJSON = url => fetch(url).then(res => res.json());

    const renderDropdown = (input, data, dropdownId, callback) => {
      const dropdown = document.getElementById(dropdownId);
      const query = input.value.toLowerCase();

      dropdown.innerHTML = '';

      const filtered = data.filter(d => d.nombre.toLowerCase().includes(query));

      if (filtered.length === 0) {
        dropdown.style.display = 'none';
        return;
      }

      filtered.forEach(item => {
        const div = document.createElement('div');
        div.textContent = item.nombre;
        div.onclick = () => {
          input.value = item.nombre;
          dropdown.style.display = 'none';
          if (callback) callback(item);
        };
        dropdown.appendChild(div);
      });

      dropdown.style.display = 'block';
    };
    
    const closeAllDropdowns = () => {
      document.querySelectorAll('.dropdown').forEach(d => d.style.display = 'none');
    };

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.input-container')) {
        closeAllDropdowns();
      }
    });

    const seccionEntidad = document.getElementById('entidadCampos');
    const seccionAsegurado = document.getElementById('datosAsegurado');
    const seccionMedico = document.getElementById('documentosMedicos');
    const datosMedicos = document.getElementById('datosMedicos');

    document.querySelectorAll('input[name="entidad"]').forEach(radio => {
      radio.addEventListener('change', e => {
        if (e.target.value === 'si') {
          seccionEntidad.style.display = 'flex';
          seccionMedico.style.display = 'none';
          datosMedicos.style.display = 'none';
        } else {
          seccionEntidad.style.display = 'none';
          seccionAsegurado.style.display = 'none';
          seccionMedico.style.display = 'flex';
        }
      });
    });

    document.querySelectorAll('input[name="seguros"]').forEach(radio => {
      radio.addEventListener('change', e => {
        seccionAsegurado.style.display = e.target.value === 'si' ? 'flex' : 'none';
      });
    });

    document.querySelectorAll('input[name="medico"]').forEach(radio => {
      radio.addEventListener('change', e => {
        datosMedicos.style.display = e.target.value === 'si' ? 'flex' : 'none';
      });
    });

    // Habilitar Boton
    const politicaCheckbox = document.getElementById('politica');
    const veracidadCheckbox = document.getElementById('veracidad');
    const submitBtn = document.querySelector('button[type="submit"]');

    const actualizarBoton = () => {
      submitBtn.disabled = !(politicaCheckbox.checked && veracidadCheckbox.checked);
    };

    politicaCheckbox.addEventListener('change', actualizarBoton);
    veracidadCheckbox.addEventListener('change', actualizarBoton);

    
    actualizarBoton();

    $("#btnBuscarDNI").click(function () {
  const dni = $("#nroDocumento").val().trim();

  if (dni.length !== 8 || isNaN(dni)) {
    alert("Ingrese un DNI válido de 8 dígitos.");
    return;
  }

  $.ajax({
    url: "buscar_dni_reniec.php",
    method: "POST",
    data: { dni: dni },
    dataType: "json",
    beforeSend: function () {
      $("#btnBuscarDNI").text("Buscando...").prop("disabled", true);
    },
    success: function (data) {

      console.group('🔍 Respuesta RENIEC');
      console.log(data);
      console.groupEnd();


      if (data.codigoRespuesta !== "0000") {
        alert("RENIEC: " + data.mensajeRespuesta);
      } else {
        $("#apePaterno").val(data.paterno);
        $("#apeMaterno").val(data.materno);
        $("#nombres").val(data.nombres);
        $("#departamento").val(data.nombreDepartamento);
        $("#provincia").val(data.nombreProvincia).prop("disabled", false);
        $("#distrito").val(data.nombreDistrito).prop("disabled", false);
      }
    },
    error: function (xhr, status, error) {
      console.group('🚨 Error AJAX RENIEC');
      console.log('Estado: ', status);
      console.log('Error: ', error);
      console.log('Respuesta completa:', xhr.responseText);
      console.groupEnd();
      alert("Error al consultar RENIEC: " + error);
    },
    complete: function () {
      $("#btnBuscarDNI").text("Buscar").prop("disabled", false);
    }
  });
});

  </script>
</body>
</html>
