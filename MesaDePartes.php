<?php
include 'conexion/conexion.php';
include 'head.php';

session_start();
$iCodTrabajadorRegistro = $_SESSION['CODIGO_TRABAJADOR'] ?? null;
$iCodOficinaRegistro = 236; // oficina Mesa de Partes

// Tipos de documento para Mesa de Partes (nFlgEntrada = 1)
$sqlTiposDoc = "SELECT cCodTipoDoc, cDescTipoDoc FROM Tra_M_Tipo_Documento WHERE nFlgEntrada = 1 ORDER BY cDescTipoDoc ASC";
$resultTiposDoc = sqlsrv_query($cnx, $sqlTiposDoc);
$tiposDocumentoEntrada = [];
while ($row = sqlsrv_fetch_array($resultTiposDoc, SQLSRV_FETCH_ASSOC)) {
    $tiposDocumentoEntrada[] = $row;
}
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
<div style="margin: 130px auto 0 auto; max-width:1200px; background:white; border:1px solid #ccc; border-radius:10px; padding:40px;">
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
          <button type="button" id="btnBuscarDNI" class="btn-primary" style="height: 48px;">
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
          <div style="display: flex; align-items: flex-end;">
          <button type="button" id="btnBuscarRUC" class="btn-primary" style="height: 48px;">
          Buscar RUC
        </button>
          </div>
          <div class="input-container">
            <input type="text" id="razonSocial" name="razonSocial" placeholder=" " autocomplete="off">
            <label for="razonSocial">Razón Social</label>
            <span class="material-icons">business</span>
          </div>
        </div>
         
        <div class="row">
          <div class="input-container">
            <input type="text" id="estado" readonly placeholder=" " />
            <label for="estado">Estado</label>
          </div>
          <div class="input-container">
            <input type="text" id="condicion" readonly placeholder=" " />
            <label for="condicion">Condición</label>
          </div>
        </div>

        <div class="row">
          <div class="input-container" style="flex: 1 1 100%;">
            <input type="text" id="direccionEntidad" readonly placeholder=" " />
            <label for="direccionEntidad">Dirección</label>
          </div>
        </div>

        <div class="row">
          <div class="input-container">
            <input type="text" id="departamentoEntidad" readonly placeholder=" " />
            <label for="departamentoEntidad">Departamento</label>
          </div>
          <div class="input-container">
            <input type="text" id="provinciaEntidad" readonly placeholder=" " />
            <label for="provinciaEntidad">Provincia</label>
          </div>
          <div class="input-container">
            <input type="text" id="distritoEntidad" readonly placeholder=" " />
            <label for="distritoEntidad">Distrito</label>
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
    <h3 style="margin-bottom: 0;">Descripción del Trámite / Observaciones</h3>

    <div class="row">

  <!-- Tipo de Documento (cCodTipoDoc) dinámico -->
  <div class="input-container select-flotante">
    <select id="tipoDocumentoOficial" name="tipoDocumentoOficial" required>
      <option value="" disabled selected hidden></option>
      <?php foreach ($tiposDocumentoEntrada as $tipo): ?>
        <option value="<?= $tipo['cCodTipoDoc'] ?>"><?= htmlentities($tipo['cDescTipoDoc']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="tipoDocumentoOficial">Tipo de Documento</label>
  </div>

  <!-- Tipo de Registro (iCodTipoRegistro) -->
  <div class="input-container select-flotante">
    <select id="tipoRegistro" name="tipoRegistro" required>
      <option value="" disabled hidden></option>
      <option value="1">Registro vía Correo</option>
      <option value="2" selected>Registro vía Presencial</option>
      <option value="3">Registro vía Mesa de Partes Virtual</option>
      <option value="4">Registro vía PIDE</option>
    </select>
    <label for="tipoRegistro">Tipo de Registro</label>
  </div>

  <!-- Número de Folios (nNumFolio) -->
  <div class="input-container">
    <input type="number" id="nNumFolio" name="nNumFolio" placeholder=" " min="1" value="1" required>
    <label for="nNumFolio">N° de Folios</label>
  </div>

</div>

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
        <label for="descripcion">Descripción del Trámite / Observaciones </label>
        <small style="color: #666; display: block; margin-top: 5px;">La descripción debe contener un máximo de 250 caracteres</small>
      </div>
    </div>

    <div class="row" style="flex-direction: column; gap: 10px;">
      <label style="font-weight: bold; font-size: 15px; color: #000;">Archivo Principal</label>
      <input type="file" id="archivo" name="archivo" accept="application/pdf"
        style="appearance: none; -webkit-appearance: none; background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 6px; outline: none; color: #333;">
      <small style="color: #666;">Peso máximo: 10 MB en total, solo se aceptan formatos pdf. Si tu archivo pesa más del peso máximo ingresa el link de descarga.</small>
    </div>

    <!-- <div class="row">
      <div class="input-container">
        <input type="text" id="link" name="link"placeholder=" " autocomplete="off">
        <label for="link">Link</label>
      </div>
    </div> -->


    <div class="row" style="flex-direction: column; gap: 10px;">
      <label style="font-weight: bold; font-size: 15px; color: #000;">Archivos Complementarios  </label>
      <input type="file" id="complementarios" name="complementarios[]" multiple accept="application/pdf"
        style="appearance: none; background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 6px; outline: none; color: #333;">
      <small style="color: #666;">Puede subir varios documentos complementarios en PDF (máximo 10 MB cada uno).</small>
    </div>

    

     <!-- CAMPO EXTRA DESTINOS -->
     <h3>Búsqueda de Oficinas</h3>
            
        <div class="form-row">
          <div class="input-container oficina-ancha" style="position: relative;">
            <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off" required>
            <label for="nombreOficinaInput">Nombre de Oficina</label>
            <input type="hidden" id="oficinasDestino" name="oficinasDestino">
            <div id="sugerenciasOficinas" class="sugerencias-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: white; border: 1px solid #ccc; max-height: 150px; overflow-y: auto;"></div>
          </div>

            <div class="input-container">
                <input type="text" id="jefeOficina" name="jefeOficina" placeholder=" " readonly>
                <label for="jefeOficina">Jefe</label>
            </div>

            <div class="input-container select-flotante">
                 <select id="indicacion" name="indicacion" required>
                      <option value="" disabled hidden></option>
                      <?php foreach($indicaciones as $ind) { ?>
                          <option value="<?= $ind['iCodIndicacion'] ?>" <?= $ind['iCodIndicacion'] == 2 ? 'selected' : '' ?>>
                              <?= trim($ind['cIndicacion']) ?>
                          </option>
                      <?php } ?>
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
                   
            <button type="button" class="btn-primary" style="min-width: 100px;" onclick="agregarDestino()">Agregar</button>
          </div>

            <!-- Tabla destinos con el mismo diseño que SIGA -->
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
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            </div>

    <div class="row" style="justify-content: center; margin-top: 30px;">
    <button type="button" class="btn-primary" onclick="guardarTramite()">
  Enviar
</button>
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

 
    const submitBtn = document.querySelector('button[type="submit"]');

 

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

$("#btnBuscarRUC").click(function () {
  const ruc = $("#ruc").val().trim();

  if (ruc.length !== 11 || isNaN(ruc)) {
    alert("Ingrese un RUC válido de 11 dígitos.");
    return;
  }

  const token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImpjYWNlcmVzcGRsQGdtYWlsLmNvbSJ9.gpDrSd1yt7M4u7jF24GPvUEE-8WhwckytYwkzTeVSwA";
  const url = `https://dniruc.apisperu.com/api/v1/ruc/${ruc}?token=${token}`;

  $("#btnBuscarRUC").text("Buscando...").prop("disabled", true);

  fetch(url)
    .then(res => res.json())
    .then(data => {
      console.group("🔍 Respuesta RUC");
      console.log(data);
      console.groupEnd();

      if (!data || data.razonSocial === undefined) {
        alert("No se encontró información del RUC.");
        return;
      }

      $("#razonSocial").val(data.razonSocial ?? '');
      $("#estado").val(data.estado ?? '');
      $("#condicion").val(data.condicion ?? '');
      $("#direccionEntidad").val(data.direccion ?? '');
      $("#departamentoEntidad").val(data.departamento ?? '');
      $("#provinciaEntidad").val(data.provincia ?? '');
      $("#distritoEntidad").val(data.distrito ?? '');
    })
    .catch(error => {
      console.error("Error al consultar RUC:", error);
      alert("Error al consultar RUC.");
    })
    .finally(() => {
      $("#btnBuscarRUC").text("Buscar RUC").prop("disabled", false);
    });
});

const oficinasAgregadas = new Set(); // Conjunto que guarda las oficinas ya agregadas como destino (para evitar duplicados)
        const jefesPorOficina = <?= json_encode($jefes, JSON_UNESCAPED_UNICODE) ?>;

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
       

         // Validar campos
        if (!oficinaId || !indicacionValue || !prioridadValue) {
            alert("Por favor, complete todos los campos.");
            return;
        }

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
            <input type="hidden" name="destinos[]" value="${oficinaId}_${jefeId}_${indicacionValue}_${prioridadText}"/>
            <td>${oficinaNombre}</td>
            <td>${jefe}</td>
            <td>${indicacionText}</td>
            <td>${prioridadText}</td>
            
            <td><button type="button" class="btn-secondary" onclick="eliminarDestino(this, '${oficinaId}')">Eliminar</button></td>`;

        // Limpiar campos del formulario
        document.getElementById("nombreOficinaInput").value = '';
        document.getElementById("oficinasDestino").value = '';
        document.getElementById("jefeOficina").value = '';
        document.getElementById("jefeOficina").dataset.jefeid = '';
        document.getElementById("indicacion").value = "2"; // cuando se seleccione nuevamente poner la indicacion 2 por defecto
        document.getElementById("prioridad").selectedIndex = 1;
 
    }

    function eliminarDestino(btn, oficinaId) {
        oficinasAgregadas.delete(oficinaId);
        btn.parentElement.parentElement.remove();
    }
    
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

// Mostrar todas al hacer focus si está vacío
$('#nombreOficinaInput').on('focus', function () {
  if ($(this).val().trim() === '') {
    mostrarSugerenciasOficinas('');
  }
});

// Buscar dinámico mientras escribe
$('#nombreOficinaInput').on('input', function () {
  const texto = $(this).val().trim();
  if (texto.length >= 1) {
    mostrarSugerenciasOficinas(texto);
  } else {
    $('#sugerenciasOficinas').hide();
  }
});

// Ocultar sugerencias al hacer clic fuera
$(document).on('click', function (e) {
  if (!$(e.target).closest('#nombreOficinaInput, #sugerenciasOficinas').length) {
    $('#sugerenciasOficinas').hide();
  }
});

async function guardarTramite() {
  const form = document.querySelector("form");
  const formData = new FormData(form);

  // Validación: debe haber al menos un destino
  const destinosInputs = document.querySelectorAll('input[name="destinos[]"]');
  if (destinosInputs.length === 0) {
    alert("Debe agregar al menos una oficina de destino.");
    return;
  }

  // Validación del archivo PDF
  const archivo = document.getElementById("archivo").files[0];
  if (archivo) {
    const maxSize = 10 * 1024 * 1024;
    const ext = archivo.name.split('.').pop().toLowerCase();
    if (ext !== 'pdf') {
      alert("Solo se permiten archivos PDF.");
      return;
    }
    if (archivo.size > maxSize) {
      alert("El archivo excede el límite de 10MB.");
      return;
    }
  }

  // Agregar manualmente todos los destinos al FormData
  destinosInputs.forEach(input => {
    formData.append("destinos[]", input.value);
  });

  // Validación: complementarios deben ser PDF y menor a 10MB cada uno
  const complementarios = document.getElementById("complementarios").files;
  for (let i = 0; i < complementarios.length; i++) {
    const archivo = complementarios[i];
    const ext = archivo.name.split('.').pop().toLowerCase();
    if (ext !== 'pdf') {
      alert(`El archivo ${archivo.name} no es un PDF.`);
      return;
    }
    if (archivo.size > 10 * 1024 * 1024) {
      alert(`El archivo ${archivo.name} supera los 10 MB.`);
      return;
    }
  }


  try {
    const response = await fetch("registroMesaDePartes.php", {
      method: "POST",
      body: formData,
    });

    const text = await response.text();
    console.log("Respuesta cruda:", text);

    const data = JSON.parse(text); // Validación por si viene como texto
    if (data.status !== "success" || !data.iCodTramite || !data.clave) {
      alert("Error al registrar el trámite: " + (data.message || "Error desconocido"));
      return;
    }

    // Redirigir a la página de confirmación
    window.location.href = `MesaDePartes_confirmacion.php?id=${data.iCodTramite}&clave=${data.clave}`;
    
  } catch (err) {
    console.error("Error general:", err);
    alert("Ocurrió un problema al registrar el trámite.");
  }
}


  </script>
</body>
</html>
