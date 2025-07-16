<style>
:root {
  --primary: #005a86;
  --secondary: #c69157;
}

body {
  margin: 0;
  padding: 0;
}

/* Contenedor general para mantener separación del header */
body > .contenedor-principal {
  margin-top: 105px;
}

/* Barra azul superior */
.barra-titulo {
  background-color: var(--primary);
  color: white;
  padding: 8px 20px;
  font-weight: bold;
  font-size: 15px;
  width: 100vw;
  box-sizing: border-box;
  margin: 0;
}

/* Formulario de filtros a pantalla completa */
.filtros-formulario {
  display: flex;
  gap: 30px;
  background: white;
  border: 1px solid #ccc;
  border-radius: 0;
  padding: 20px 20px 10px;
  width: 100vw;
  box-sizing: border-box;
  flex-wrap: wrap;
  margin: 0;
}

.columna-izquierda {
  flex: 1;
  max-width: 40%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.columna-derecha {
  flex: 2;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.fila {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.input-container {
  position: relative;
  flex: 1;
  min-width: 120px;
}

.input-container input,
.input-container select {
  width: 100%;
  padding: 14px 12px 6px;
  font-size: 14px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  box-sizing: border-box;
  appearance: none;
  height: 42px;
  line-height: 1.2;
}

.input-container select:required:invalid {
  color: #aaa;
}

.input-container label {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  font-size: 13px;
  color: #666;
  background: white;
  padding: 0 4px;
  pointer-events: none;
  transition: 0.2s ease all;
}

.input-container input:focus + label,
.input-container input:not(:placeholder-shown) + label,
.input-container select:focus + label,
.input-container select:valid + label {
  top: -7px;
  font-size: 11px;
  color: #333;
  transform: translateY(0);
}

.input-container input[type="date"]:not(:placeholder-shown) + label,
.input-container input[type="date"]:valid + label {
  top: -7px;
  font-size: 11px;
  color: #333;
  transform: translateY(0);
}

.botones-filtro {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  margin-left: auto;
}

.btn-filtro {
  padding: 0 16px;
  font-size: 14px;
  border-radius: 4px;
  min-width: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: none;
  cursor: pointer;
  height: 42px;
  box-sizing: border-box;
}

.btn-primary {
  background-color: var(--primary);
  color: white;
}

.btn-secondary {
  background-color: var(--secondary);
  color: white;
}
</style>

<div class="contenedor-principal">

  <!-- TÍTULO PRINCIPAL PEGADO AL HEADER -->
  <div class="barra-titulo">BANDEJA DE PENDIENTES</div>

  <!-- FORMULARIO OCUPANDO TODA LA PANTALLA -->
  <form class="filtros-formulario">
    <!-- COLUMNA IZQUIERDA -->
    <div class="columna-izquierda">
      <div class="fila">
        <div class="input-container">
          <input type="text" name="anio" placeholder=" " required>
          <label>Año</label>
        </div>
        <div class="input-container">
          <input type="text" name="expediente" value="<?= $valorExpediente ?>" placeholder=" " required>
          <label>N° Expediente</label>
        </div>
        <div class="input-container">
          <input type="text" name="extension" value="<?= $valorExtension ?>" placeholder=" " required>
          <label>Extensión</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <select name="oficina_origen" required>
            <option value="" disabled selected hidden></option>
            <?php while ($of = sqlsrv_fetch_array($oficinasResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $of['cNomOficina'] ?>"><?= $of['cNomOficina'] ?></option>
            <?php endwhile; ?>
          </select>
          <label>Oficina de Origen</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <input type="date" name="desde" value="<?= $valorDesde ?>" placeholder=" " required>
          <label>Desde</label>
        </div>
        <div class="input-container">
          <input type="date" name="hasta" value="<?= $valorHasta ?>" placeholder=" " required>
          <label>Hasta</label>
        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div class="columna-derecha">
      <div class="fila">
        <div class="input-container">
          <input type="text" name="tipo_tramite" placeholder=" " required>
          <label>Tipo de Trámite</label>
        </div>
        <div class="input-container">
          <select id="tipoDocumento" name="tipoDocumento" required>
            <option value="" disabled selected hidden></option>
            <?php while ($td = sqlsrv_fetch_array($tipoDocResult, SQLSRV_FETCH_ASSOC)): ?>
              <option value="<?= $td['cCodTipoDoc'] ?>"><?= $td['cDescTipoDoc'] ?></option>
            <?php endwhile; ?>
          </select>
          <label for="tipoDocumento">Tipo de Documento</label>
        </div>
        <div class="input-container">
          <input type="text" name="nro_documento" placeholder=" " required>
          <label>Nro de Documento</label>
        </div>
        <div class="input-container">
          <input type="text" name="estado" placeholder=" " required>
          <label>Estado</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container" style="flex: 1;">
          <input type="text" name="asunto" value="<?= $valorAsunto ?>" placeholder=" " required>
          <label>Asunto</label>
        </div>
      </div>

      <div class="fila">
        <div class="input-container">
          <input type="text" name="operador" placeholder=" " required>
          <label>Operador</label>
        </div>
        <div class="input-container">
          <input type="text" name="delegado" placeholder=" " required>
          <label>Delegado a</label>
        </div>
        <div class="botones-filtro">
          <button type="submit" class="btn-filtro btn-primary">
            <span class="material-icons">search</span> Buscar
          </button>
          <button type="button" class="btn-filtro btn-secondary" onclick="window.location.href='bandejaPendientes.php'">
            <span class="material-icons">autorenew</span> Reestablecer
          </button>
        </div>
      </div>
    </div>
  </form>

  <!-- BARRA DE REGISTROS PEGADA AL FORMULARIO -->
  <div class="barra-titulo">REGISTROS</div>

</div>
