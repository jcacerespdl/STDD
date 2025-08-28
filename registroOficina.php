<?php
    include("head.php"); 
    include_once("conexion/conexion.php");   
    global $cnx;

    if (!isset($_SESSION['CODIGO_TRABAJADOR'])) {
        header("Location: index.php?error=No tiene una sesión activa.");   
        exit();
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
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registro de Oficinas</title>
        <script type="module" src="https://unpkg.com/@material/web/all.js"></script>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
        <style>
        h3 {
        margin-bottom: 12px;
        }
         
        </style>
    </head>
    <body>  
        <!-- Contenido principal (Registro de Oficina) -->
<div class="container" style="margin-top: 125px;">
  <!-- Formulario -->
  <form method="POST" id="formularioRegistro">
  <input type="hidden" name="iCodTramite" id="iCodTramite">

  <div class="form-card">
    <h2>Redacción del Encabezado</h2>
      <div class="form-row">
        <div class="input-container select-flotante">                        
          <select id="tipoDocumento" name="tipoDocumento" required>
            <option value="" disabled selected hidden></option>
            <?php foreach ($tiposDoc as $tipo) { ?>
            <option value="<?= $tipo['cCodTipoDoc'] ?>"><?= $tipo['cDescTipoDoc'] ?></option>
            <?php } ?>
          </select>  
          <label for="tipoDocumento">Tipo de Documento</label>  
        </div>

        <div class="input-container">
          <input type="text" id="correlativo" name="correlativo" class="form-control" placeholder="" readonly required>
          <label for="correlativo">Correlativo:</label>
        </div>
      </div>
      
   <!-- INICIO: GRUPO REQUERIMIENTO -->
      <div id="grupoRequerimiento" style="display:none; margin-top: 20px;">
      <!-- Tipo de requerimiento y ¿tiene pedido SIGA? -->
      <div class="form-row">
            <!-- Tipo de Requerimiento -->
          <div class="input-container select-flotante">
            <select id="tipoBien" name="tipoBien" required>
              <option value="" disabled selected hidden></option>
              <option value="B">Bien</option>
              <option value="S">Servicio</option>
            </select>
            <label for="tipoBien">Tipo de Requerimiento</label>
          </div>

            <!-- ¿Tiene Pedido SIGA? -->
          <div class="input-container select-flotante">
            <select id="pedidoSiga" name="pedidoSiga" required>
              <option value="" disabled selected hidden></option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
            <label for="pedidoSiga">¿Tiene Pedido SIGA?</label>
          </div>
        </div>

      <!-- Sección: Buscar por pedido SIGA -->
      <div id="seccionPedidoSiga" style="display:none; margin-top: 10px;">
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

            <!-- Resultados búsqueda SIGA -->
              <div class="form-row" id="resultadoBusqueda" style="display: none; margin-top: 10px;">
                <div class="input-container" style="width: 100%; overflow-x: auto;">
                  <h3>Ítems SIGA Búsqueda</h3>
                  <table id="tablaSiga" style="width: 100%; border-collapse: collapse; font-size: 14px;">
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
               <!-- FIN Resultados búsqueda SIGA -->

              <!-- Ítems agregados -->
              <div class="form-row" id="resultadoAgregado" style="margin-top: 10px;">
                <div class="input-container" style="width: 100%; overflow-x: auto;">
                  <h3>Ítems SIGA Agregados</h3>
                  <table id="tablaSigaAgregados" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead style="background: #f5f5f5;">
                      <tr>
                        <th>PEDIDO SIGA</th>
                        <th>CÓDIGO ITEM</th>
                        <th>NOMBRE ITEM</th>
                        <th>CANTIDAD SOLICITADA</th>
                        <th>ACCIONES</th>
                      </tr>
                    </thead>
                    <tbody>                      
                    </tbody>
                  </table>
                </div>
              </div>
              </div>

            <!-- Sección: Buscar sin pedido SIGA -->
            <div id="busquedaItemSinPedido" style="display:none; margin-top: 20px;">
 
              <!-- Fila de búsqueda -->
              <div class="form-row" style="display: flex; gap: 12px;">
                <!-- Buscar por CÓDIGO_ITEM -->
                <div style="display: flex; flex: 1.9; gap: 8px;">
                <div class="input-container select-flotante" style="flex: 1.5;">
                  <input type="text" id="buscarItemCodigo" name="buscarItemCodigo" placeholder=" " required>
                  <label for="buscarItemCodigo">Código de Ítem</label>
                </div>
                <div class="input-container" style="flex: 0.4;">
                  <button type="button" id="buscarItemBtn" class="btn-primary" style="width: 75%;">Buscar Catálogo</button>
                </div>
                </div>

              <!-- Buscar por NOMBRE_ITEM (autocomplete en vivo) -->
              <div class="input-container select-flotante" style="flex: 1.85; position: relative;">
                      <input type="text" id="buscarItemTextoNombre" name="buscarItemTextoNombre" placeholder=" " autocomplete="off">
                  <label for="buscarItemTextoNombre">Nombre de Ítem</label>
              <div id="sugerenciasItemsNombre" class="sugerencias-dropdown"></div>
              </div>
            </div>

            <!-- Resultados búsqueda catálogo -->
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

              <!-- Ítems agregados -->
            <div class="form-row">
              <h4>Ítems Catálogo Agregados</h4>
              <table id="tablaItemsSinPedido" style="width: 100%; font-size: 14px;">
                <thead style="background: #f5f5f5;">
                  <tr>
                    <th>Código</th><th>Nombre</th><th>Cantidad</th><th>Acción</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
    <!-- FIN: GRUPO REQUERIMIENTO -->

    
         <!-- Asunto y Observaciones, Selección de destinos, etc. continúan aquí... -->
         <div class="form-row">
          <div class="input-container" style="flex: 1; position: relative;">
            <textarea id="asunto" name="asunto" class="form-textarea" required></textarea>
            <label for="asunto">Asunto</label>
          </div>
          <div class="input-container" style="flex: 1; position: relative;">
            <textarea id="observaciones" name="observaciones" class="form-textarea"></textarea>
            <label for="observaciones">Observaciones</label>
          </div>
        </div>

        <div class="form-row">
        <div class="input-container" style="flex: 0.49; position: relative;">
          <input type="number" id="folios" name="folios" class="form-control" value="1" min="1">
          <label for="folios">Folios</label>
        </div>

        </div>

            
<!-- CAMPO EXTRA DESTINOS -->
            <h3>Búsqueda de Oficinas</h3>
            
            <div class="form-row">
            <div class="input-container oficina-ancha" style="position: relative;">
            <input type="text" id="nombreOficinaInput" placeholder=" " autocomplete="off" required>
            <label for="nombreOficinaInput">Nombre de Oficina</label>
            <input type="hidden" id="oficinasDestino" name="oficinasDestino">
            <div id="sugerenciasOficinas" class="sugerencias-dropdown" style=" position: absolute; top: 100%; left: 0; right: 0; z-index: 10; background: white; border: 1px solid #ccc; max-height: 150px; overflow-y: auto;"></div>
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
                   
            <label style="margin-left: 10px; align-self: center;"><input type="checkbox" id="copiaCheck"> Copia</label>

            <button type="button" class="btn-primary" style="padding: 0.75rem 1.5rem; min-width: 100px;" onclick="agregarDestino()">Agregar</button>
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
                            <th>Copia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            </div>
            <!-- Botón de Guardar -->
            <div class="form-group" style="display: flex; justify-content: flex-end;">
              <button type="button" class="btn-primary" style="padding: 0.75rem 1.5rem; min-width: 100px;" onclick="guardarTramite()">
              Generar
            </button>
            </div>    
        </form>
        </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        const oficinasAgregadas = new Set(); // Conjunto que guarda las oficinas ya agregadas como destino (para evitar duplicados)
        const jefesPorOficina = <?= json_encode($jefes, JSON_UNESCAPED_UNICODE) ?>;
        let itemsManual = {};              // Objeto que almacena los ítems agregados SIN pedido SIGA (desde catálogo)
        let itemsSeleccionados = {};       // Objeto que almacena los ítems agregados CON pedido SIGA (vía búsqueda)

        // 1. Al cambiar tipo de documento
          $('#tipoDocumento').on('change', function () {
            const tipo = $(this).val();

            // Obtener correlativo automáticamente
            if (tipo) {
              $.get("obtenerCorrelativo.php", { cCodTipoDoc: tipo }, function (data) {
                const res = JSON.parse(data);
                $("#correlativo").val(res.status === "success" ? res.correlativo : "");
              });
            } else {
              $("#correlativo").val("");
            }

            // Mostrar solo si es Nota Informativa Requerimiento
            if (tipo === "109" || tipo === "108") {
              $('#grupoRequerimiento').show();
            } else {
              // Limpiar y ocultar secciones relacionadas al SIGA
              $('#grupoRequerimiento, #seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado, #busquedaItemSinPedido').hide();
              $('#tipoBien, #pedidoSiga').val('');
              $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');
              $('#tablaSiga tbody, #tablaSigaAgregados tbody, #tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();
              itemsManual = {};
              itemsSeleccionados = {};
            }
          });

///////// INICIO JS PARA SIGA
// 2. Al cambiar "¿Tiene Pedido SIGA?"
$('#pedidoSiga').on('change', function () {
  const tipo = $('#tipoDocumento').val();
  const valor = $(this).val();

  if (tipo !== "109" && tipo !== "108") {
    alert("Primero seleccione 'Nota Informativa Requerimiento'");
    $(this).val('');
    return;
  }

 // Limpiar ambos tipos de ítems
 $('#tablaSiga tbody, #tablaSigaAgregados tbody, #tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();
  itemsManual = {};
  itemsSeleccionados = {};
  $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');

// Mostrar secciones según selección
  if (valor === "1") {
    $('#seccionPedidoSiga').show();
    $('#resultadoBusqueda, #resultadoAgregado').show();
    $('#busquedaItemSinPedido').hide();
  } else {
    $('#seccionPedidoSiga, #resultadoBusqueda, #resultadoAgregado').hide();
    $('#busquedaItemSinPedido').show();
  }
});

// 3. Al cambiar entre Bien / Servicio
$('#tipoBien').on('change', function () {
    const tipoSeleccionado = $(this).val();
  const tipoEsperado = tipoSeleccionado === 'B' ? 'BIEN' : 'SERVICIO';

  // Limpiar ítems del catálogo
  itemsManual = {};
  $('#tablaItemsSinPedido tbody, #tablaItemsEncontrados tbody').empty();

  // Limpiar ítems SIGA que no coincidan
  $('#tablaSigaAgregados tbody tr').each(function () {
    const tipoBienTexto = $(this).children().eq(2).text().trim().toUpperCase();
        if (tipoBienTexto !== tipoEsperado) {
        const clave = $(this).data('clave');
      delete itemsSeleccionados[clave];
      $(this).remove();
    }
  });

  $('#tablaSiga tbody').empty();
  $('#expedienteSIGA, #buscarItemTexto, #buscarItemCodigo').val('');
});

// 4. Buscar ítems por pedido  SIGA
let pedidosSigaData = {}; // Guardará { nroPedido_tipoBien: [...items] }
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
    const hidden = `<input type="hidden" name="pedidosSiga[]" value="${nro}_${tipoBien}_${item.CODIGO_ITEM}_${cantidadEntera}">`;
    filas += `
      <tr data-pedido="${clave}">
        ${idx === 0 ? `<td rowspan="${totalItems}">${nro}</td>` : ''}
        <td>${item.CODIGO_ITEM}</td>
        <td>${item.NOMBRE_ITEM}</td>
        <td>${item.CANT_SOLICITADA}</td>
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
  $(`#tablaSigaAgregados tr[data-pedido='${clave}']`).remove();
  delete pedidosSigaData[clave];
}

// 5. Agregar ítem SIGA a tabla
$(document).on('click', '.agregarPedido', function () {
  const clave = $(this).data('clave');
  const tipoSeleccionado = $('#tipoBien').val(); 
  const tipoTexto = tipoSeleccionado === 'B' ? 'BIEN' : 'SERVICIO';
  const tipoItem = $(this).closest('tr').children().eq(2).text().trim().toUpperCase();
  
  if (tipoItem !== tipoTexto) {
    return alert("Este ítem no coincide con el tipo de requerimiento seleccionado.");
  }

  if (itemsSeleccionados[clave]) return alert('Este ítem ya fue agregado.');

  itemsSeleccionados[clave] = true;
  const tds = $(this).closest('tr').children().map(function () {
    return `<td>${$(this).html()}</td>`;
  }).get();
  const hidden = `<input type="hidden" name="pedidosSiga[]" value="${clave}">`;
  const eliminar = `<button type="button" class="btn-secondary" onclick="eliminarItem('${clave}', this)">Eliminar</button>`;
  $('#tablaSigaAgregados tbody').append(`<tr data-clave="${clave}">${tds.slice(0, 10).join('')}<td>${eliminar}${hidden}</td></tr>`);
});

// Eliminar ítem SIGA
function eliminarItem(clave, btn) {
  delete itemsSeleccionados[clave];
  $(btn).closest('tr').remove();
}

// 6A. Buscar ítems del catálogo por CÓDIGO
$('#buscarItemBtn').on('click', function () {
  const tipo = $('#tipoBien').val();
  const codigo = $('#buscarItemCodigo').val().trim();
  if (!tipo || !codigo) return alert("Buscar por código requiere tipo y un valor válido.");
  
  $.get('buscar_item.php', { tipo: tipo, q: codigo }, function (res) {
    if (!res.length) return alert("No se encontró el ítem para ese código.");
    renderizarItemsCatalogo(res);
  }, 'json');
});

// 6B. Buscar ítems del catálogo por NOMBRE en vivo (autosugerencias)
$('#buscarItemTextoNombre').on('input', function () {
  const texto = $(this).val().trim();
  const tipo = $('#tipoBien').val();

  if (!texto || texto.length < 3) {
    $('#sugerenciasItemsNombre').hide();
    return;
  }

    $.get('buscar_item_nombre.php', { q: texto }, function (res) {
        const contenedor = $('#sugerenciasItemsNombre');
    contenedor.empty().show();

    const tipoEsperado = tipo === 'B' ? 'BIEN' : tipo === 'S' ? 'SERVICIO' : null;

    res.forEach(item => {
      const tipoBien = item.TIPO_BIEN;
      const nombre = item.NOMBRE_ITEM;
      const codigo = item.CODIGO_ITEM;

      // Validar tipo al renderizar
      if (tipoBien === tipo) {
        const opcion = $(`<div class="sugerencia-item">${nombre}</div>`);
        opcion.on('click', function () {
          $('#buscarItemTextoNombre').val(nombre);
          $('#sugerenciasItemsNombre').hide();
          renderizarItemsCatalogo([item]);
        });
        contenedor.append(opcion);
      }
    });

    // Si no hubo resultados válidos
    if (contenedor.children().length === 0) {
      contenedor.append('<div class="sugerencia-item" style="color: #888;">Sin coincidencias del tipo seleccionado</div>');
    }
  }, 'json');
});

// Ocultar dropdown si se hace clic fuera
$(document).on('click', function (e) {
  if (!$(e.target).closest('#buscarItemTextoNombre, #sugerenciasItemsNombre').length) {
    $('#sugerenciasItemsNombre').hide();
  }
});

// 6C. Al perder foco, si seleccionó un ítem del datalist por nombre, cargar en tabla
$('#buscarItemTextoNombre').on('change', function () {
  const nombreSeleccionado = $(this).val().trim();
  const tipoRequerido = $('#tipoBien').val();
  const sugerencias = $(this).data('sugerencias') || [];

  const item = sugerencias.find(i => i.NOMBRE_ITEM === nombreSeleccionado);
   if (item.TIPO_BIEN !== tipoRequerido) return alert("El ítem seleccionado no coincide con el tipo de requerimiento.");


    renderizarItemsCatalogo([item]);
  
});

// 🔁 Reutilizable: Renderiza fila(s) de ítems catálogo
function renderizarItemsCatalogo(items) {
  const filas = items.map(item => `
    <tr>
      <td>${item.CODIGO_ITEM}</td>
      <td>${item.NOMBRE_ITEM}</td>
      
      <td><input type="number" min="1" value="1" style="width:60px;" data-codigo="${item.CODIGO_ITEM}"></td>
      <td><button type="button" onclick="agregarItemManual('${item.CODIGO_ITEM}', '${item.NOMBRE_ITEM}')">Agregar</button></td>
    </tr>
  `).join('');
  $('#tablaItemsEncontrados tbody').html(filas);
}

// 7. Agregar ítem manual del catálogo a tabla
function agregarItemManual(codigo, nombre) {
  const cantidad = $(`input[data-codigo="${codigo}"]`).val();
  if (!cantidad || cantidad <= 0) return alert("Cantidad inválida");
  if (itemsManual[codigo]) return alert("ITEM SIGA Ya fue agregado");

  itemsManual[codigo] = cantidad;
  $('#tablaItemsSinPedido tbody').append(`
    <tr>
      <td>${codigo}</td>
      <td>${nombre}</td>
      <td>${cantidad}</td>
      <td>
        <button type="button" onclick="eliminarItemManual('${codigo}')">Eliminar</button>
        <input type="hidden" name="itemsSigaManual[]" value="${codigo}_${cantidad}">
      </td>
    </tr>
  `);
}

// 8. Eliminar ítem manual
function eliminarItemManual(codigo) {
  delete itemsManual[codigo];
  $(`#tablaItemsSinPedido tbody tr:has(td:contains('${codigo}'))`).remove();
}

///////// FIN JS PARA SIGA

async function guardarTramite() {
        const form = document.getElementById("formularioRegistro");
        const formData = new FormData(form); // Recolecta todos los campos del formulario
        for (var pair of formData.entries()) {
            console.log(pair[0]+ ': ' + pair[1]);
        }
        try {
            const response = await fetch('registroOficinaGenerar.php', {
                method: "POST",
                body: formData
            });

            const data = await response.json();
            console.log("Respuesta del servidor:", data);

            if (data.status === "error") {
                alert("Error: " + data.message);
                return;
            }

                // Redirigir al editor 
            document.getElementById("iCodTramite").value = data.iCodTramite;
            window.location.href = `RegistroOficinaEditor.php?iCodTramite=${data.iCodTramite}`;
        } catch (err) {
            console.error("Error en la solicitud:", err);
            alert("Ocurrió un problema al guardar el trámite.");
        }
    }

    document.getElementById('nombreOficinaInput').addEventListener('input', function () {
        const nombreSeleccionado = this.value;
        const opciones = document.querySelectorAll('#listaOficinas option');
        let match = false;
        opciones.forEach(op => {
            if (op.value === nombreSeleccionado) {
                      // Extraer datos asociados
                document.getElementById('oficinasDestino').value = op.dataset.id;
                document.getElementById('jefeOficina').value = op.dataset.jefe;
                document.getElementById('jefeOficina').dataset.jefeid = op.dataset.jefeid;
                match = true;
            }
        });
        if (!match) {
                // Limpiar si no se encontró coincidencia exacta
            document.getElementById('oficinasDestino').value = '';
            document.getElementById('jefeOficina').value = '';
            document.getElementById('jefeOficina').dataset.jefeid = '';
        }
    });

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



</script>
</body>
</html>
