# 📦 TABLAS CON DATOS GENERADOS POR EL USO DEL SISTEMA

Estas tablas se llenan automáticamente durante el flujo del sistema conforme los usuarios crean, derivan, firman o complementan trámites.

# 📄 Tabla: Tra_M_Tramite
## 🧱 Estructura de campos

| Campo                         | Tipo de dato          | Descripción                      |
|-------------------------------|-----------------------|---------------------------------------------|
| `iCodTramite` 		| INT (PK, IDENTITY) 	| ID único del trámite. |
| `cAsunto` 			| TEXT 			| Asunto principal del documento. |
| `cObservaciones` 		| VARCHAR(4000) 	| Observaciones  adicionales. |
| `nNumFolio` 			| NVARCHAR(10) 		| Número de folios. |
| `iCodTrabajadorRegistro` 	| INT                   | ID del trabajador que registra (FK → `Tra_M_Trabajadores`). |
| `iCodOficinaRegistro` 	| INT 			| ID de la oficina de registro (FK → `Tra_M_Oficinas`). |
| `EXPEDIENTE` 			| CHAR(10) 		| Número de expediente. Prefijo: `I` (interno) o `E` (externo). |
| `extension` 			| INT 			| Número de extensión. |
| `fFecDocumento` 		| DATETIME 		| Fecha de inicio del encabezado del documento. |
| `fFecRegistro` 		| DATETIME 		| Fecha de fin de redacción (cuerpo del documento). |
| `nFlgEstado` 			| INT 			| 0: Solo encabezado, 1: Redacción completa. |
| `nFlgEnvio` 			| INT 			| 0: No enviado, 1: Enviado. |
| `nFlgFirma` 			| INT 			| 0: No firmado, 1: Firmado. |
| `nFlgTipoDerivo` 		| INT 			| NULL: Generado, 1: Derivado. |
| `cCodTipoDoc` 		| INT 			| Código del tipo de documento (FK → `Tra_M_Tipo_Documento`). |
| `cCodificacion` 		| NVARCHAR(150) 	| Codificación final: correlativo + siglas oficina. |
| `documentoElectronico` 	| VARCHAR(300) 		| Nombre del archivo PDF almacenado en /cDocumentosFirmados |
| `descripcion` 		| TEXT 			| Cuerpo del documento en formato HTML. |
| `cPassword` 			| CHAR(50) 		| Código CVV de seguridad para consulta. |
| `codigoQr` 			| VARCHAR(300) 		| Código QR del documento. |
| `cTipoBien` 			| CHAR(1) 		| `B`: Bien, `S`: Servicio. |
| `nTienePedidoSiga` 		| INT 			| 1 si el trámite tiene pedido SIGA. 0 si no tiene |
| `fase` 			| INT 			| Fase del requerimiento |
| `nFlgTipoDoc` 		| INT                   | 1: Externo (mesa de partes), 2: Interno. |

# 📄 Tabla: Tra_M_Tramite_Movimientos
## 🧱 Estructura de campos
| Campo                         | Tipo de dato          | Descripción                      |
|-------------------------------|-----------------------|---------------------------------------------|
| `iCodMovimiento`		| INT (PK, IDENTITY) 	| ID único del movimiento. |
| `EXPEDIENTE` 			| CHAR(10) 		| Número del expediente (mismo del trámite principal). |
| `extension` 			| INT 			| Número de extensión heredado del trámite o derivación anterior. |
| `iCodTramite` 		| INT 			| Número del tramite (mismo del trámite principal). |
| `iCodTramiteDerivar` 		| INT 			| Nuevo trámite generado si es una derivación. |
| `iCodMovimientoDerivo` 	| INT 			| Movimiento antecesor si aplica (seguimiento). |
| `iCodOficinaOrigen` 		| INT 			| Oficina que envía el trámite (FK → `Tra_M_Oficinas`). |
| `iCodOficinaDerivar`		| INT 			| Oficina destino de la derivación (FK → `Tra_M_Oficinas`). |
| `iCodTrabajadorRegistro` 	| INT 			| Trabajador que crea el movimiento (FK → `Tra_M_Trabajadores`). |
| `iCodTrabajadorDerivar` 	| INT 			| Jefe o trabajador que deriva a la oficina destino. |
| `fFecDerivar` 		| DATETIME 		| Fecha de envío (cuando se firma o envía desde bandeja POR APROBAR). |
| `iCodIndicacionDerivar` 	| INT 			| Indicacion (FK → `Tra_M_Indicaciones`). |
| `cPrioridadDerivar`   	| NVARCHAR(50) 		| Prioridad de respuesta: `baja`, `media`, `alta`. |
| `nTiempoRespuesta` 		| INT 			| Días de plazo para respuesta (por prioridad: alta=1, media=3, baja=5). |
| `fFecPlazo` 			| DATETIME 		| Fecha máxima límite de atención del trámite. |
| `fFecRecepcion` 		| DATETIME 		| Fecha en que el usuario acepta en bandeja de pendientes. |
| `cFlgTipoMovimiento` 		| INT 			| Tipo: `1` normal, `4` copia (no requiere respuesta). |
| `nEstadoMovimiento` 		| INT 			| Estado del movimiento:<br>0 = sin aceptar<br>1 = recibido<br>3 = delegado<br>5 = finalizado<br>6 = observado |
| `cAsuntoDerivar` 		| TEXT 			| Asunto del documento al derivar. |
| `cObservacionesDerivar` 	| TEXT 			| Observaciones indicadas durante la derivación. |

| `fFecDelegado` 		| DATETIME		| Fecha en que se delega a otro trabajador. |
| `fFecDelegadoRecepcion` 	| DATETIME 		| Fecha en que el delegado acepta el encargo. |
| `iCodTrabajadorDelegado` 	| INT 			| Trabajador al que se le delega la atención. |
| `iCodIndicacionDelegado` 	| INT 			| Instrucción asignada junto con la delegación. |
| `cObservacionesDelegado` 	| TEXT 			| Observaciones al momento de delegar. |

| `fFecFinalizar` 		| DATETIME 		| Fecha de finalización del movimiento. |
| `iCodTrabajadorFinalizar` 	| INT 			| Trabajador que da por finalizado el trámite. |
| `cObservacionesFinalizar` 	| TEXT 			| Comentario u observación al finalizar. |
| `cDocumentoFinalizacion` 	| VARCHAR(300) 		| Nombre del documento adjunto al cerrar el movimiento. |


| `fFecMovimiento` 		| DATETIME 		| Fecha general del movimiento. |
| `nFlgEnvio` 			| INT 			| Indicador si el movimiento fue enviado. |
| `nFlgTipoDoc` 		| NVARCHAR(2) 		| Tipo de documento del movimiento (externo/interno).

# 📄 Tabla: Tra_M_Tramite_Digitales
## 🧱 Estructura de campos

| Campo                  | Tipo de dato        | Descripción |
|------------------------|---------------------|-------------|
| `iCodDigital`          | INT (PK, IDENTITY)  |             |
| `iCodTramite`          | INT                 |             |
| `cDescripcion`         | NVARCHAR(250)       |             |
| `cNombreOriginal`      | NVARCHAR(250)       |             |
| `cNombreNuevo`         | NVARCHAR(250)       |             |
| `iCodMovimiento`       | INT                 |             |
| `nFlgFirma`            | INT                 |             |
| `nFlgPDF`              | INT                 |             |
| `fFechaRegistro`       | DATETIME            |             |
| `cCodTemp`             | VARCHAR(50)         |             |
| `id_periodo`           | INT                 |             |
| `iCodOficina`          | INT                 |             |
| `pedido_siga`          | INT                 |             |
| `cTipoComplementario`  | INT                 |             |

---

# 📄 Tabla: Tra_M_Tramite_Extension
## 🧱 Estructura de campos

| Campo                    | Tipo de dato        | Descripción |
|--------------------------|---------------------|-------------|
| `iCodExtension`          | INT (PK, IDENTITY)  |             |
| `iCodTramite`            | INT                 |             |
| `nro_extension`          | INT                 |             |
| `iCodTramiteSIGAPedido`  | INT                 |             |
| `iCodTramiteSIGA`        | INT                 |             |
| `fFecCreacion`           | DATETIME            |             |
| `iCodMovimientoOrigen`   | INT                 |             |
| `iCodTrabajadorRegistro` | INT                 |             |
| `fFecRegistro`           | DATETIME            |             |
| `observaciones`          | NVARCHAR(500)       |             |

---

# 📄 Tabla: Tra_M_Tramite_Firma
## 🧱 Estructura de campos

| Campo               | Tipo de dato        | Descripción |
|---------------------|---------------------|-------------|
| `iCodFirma`         | INT (PK, IDENTITY)  |             |
| `iCodTramite`       | INT                 |             |
| `iCodDigital`       | INT                 |             |
| `iCodTrabajador`    | INT                 |             |
| `nFlgFirma`         | INT                 |             |
| `nFlgEstado`        | INT                 |             |
| `cCodSession`       | VARCHAR(50)         |             |
| `observaciones`     | NVARCHAR(255)       |             |
| `posicion`          | NVARCHAR(2)         |             |
| `iCodOficina`       | INT                 |             |
| `tipoFirma`         | NVARCHAR(2)         |             |
| `iCodPerfil`        | INT                 |             |

---

# 📄 Tabla: Tra_M_Tramite_Referencias
## 🧱 Estructura de campos

| Campo                | Tipo de dato        | Descripción |
|----------------------|---------------------|-------------|
| `iCodReferencia`     | INT (PK, IDENTITY)  |             |
| `iCodTramite`        | INT                 |             |
| `iCodTramiteRef`     | INT                 |             |
| `cReferencia`        | VARCHAR(100)        |             |
| `cCodSession`        | VARCHAR(28)         |             |
| `cDesEstado`         | VARCHAR(15)         |             |
| `iCodTipo`           | INT                 |             |
| `identificador`      | VARCHAR(10)         |             |

---

# 📄 Tabla: Tra_M_Tramite_SIGA_Pedido
## 🧱 Estructura de campos

| Campo               | Tipo de dato        | Descripción |
|---------------------|---------------------|-------------|
| `iCodTramiteSIGAPedido` | INT (PK, IDENTITY) |             |
| `iCodTramite`       | INT                 |             |
| `pedido_siga`       | VARCHAR(50)         |             |
| `extension`         | INT                 |             |
| `codigo_item`       | VARCHAR(50)         |             |
| `cantidad`          | INT                 |             |
| `stock`             | INT                 |             |
| `consumo_promedio`  | INT                 |             |
| `meses_consumo`     | INT                 |             |
| `situacion`         | NVARCHAR(255)       |             |

# 📄 Tabla: Tra_M_Correlativo_Oficina
## 🧱 Estructura de campos

| Campo             | Tipo de dato        | Descripción |
|-------------------|---------------------|-------------|
| `iCodCorrelativo` | INT (PK, IDENTITY)  |             |
| `cCodTipoDoc`     | INT                 |             |
| `iCodOficina`     | INT                 |             |
| `nNumAno`         | INT                 |             |
| `nCorrelativo`    | INT                 |             |

---

# 📦 TABLAS PARA GESTIÓN DEL SUPER ADMIN

Estas tablas son gestionadas exclusivamente por el usuario con rol de Super Administrador, y permiten configurar oficinas, usuarios y perfiles.

---

# 📄 Tabla: Tra_M_Oficinas
## 🧱 Estructura de campos

| Campo                 | Tipo de dato        | Descripción |
|------------------------|---------------------|-------------|
| `iCodOficina`          | INT (PK, IDENTITY)  |             |
| `cNomOficina`          | VARCHAR(250)        |             |
| `cSiglaOficina`        | VARCHAR(50)         |             |
| `cImgCabecera`         | VARCHAR(1000)       |             |
| `iFlgEstado`           | INT                 |             |
| `iCodOficina_padre`    | INT                 |             |
| `cImgCabeceraWord`     | VARCHAR(300)        |             |

---

# 📄 Tabla: Tra_M_Trabajadores
## 🧱 Estructura de campos

| Campo                  | Tipo de dato        | Descripción |
|-------------------------|---------------------|-------------|
| `iCodTrabajador`        | INT (PK, IDENTITY)  |             |
| `cNombresTrabajador`    | NVARCHAR(100)       |             |
| `cApellidosTrabajador`  | NVARCHAR(100)       |             |
| `cUsuario`              | NVARCHAR(50)        |             |
| `cPassword`             | VARCHAR(100)        |             |
| `nFlgEstado`            | INT                 |             |
| `nEstadoClave`          | TINYINT             |             |
| `fUltimoCambioClave`    | DATETIME            |             |

---

# 📄 Tabla: Tra_M_Perfil_Ususario
## 🧱 Estructura de campos

| Campo              | Tipo de dato        | Descripción |
|---------------------|---------------------|-------------|
| `iCodPerfilUsuario` | INT (PK, IDENTITY)  |             |
| `iCodPerfil`        | INT                 |             |
| `iCodOficina`       | INT                 |             |
| `iCodTrabajador`    | INT                 |             |

---

# 📘 TABLAS CON DATOS FIJOS

Estas tablas contienen datos estáticos utilizados como referencias o catálogos dentro del sistema.

---

# 📄 Tabla: Tra_M_Perfil
## 🧱 Estructura de campos

| Campo         | Tipo de dato        | Descripción |
|----------------|---------------------|-------------|
| `iCodPerfil`   | INT (PK, IDENTITY)  |             |
| `cDescPerfil`  | CHAR(50)            |             |

> Valores  :
> - 1 Administrador  
> - 3 Jefe  
> - 4 Profesional  
> - 19 Asistente  

---

# 📄 Tabla: Tra_M_Indicaciones
## 🧱 Estructura de campos

| Campo             | Tipo de dato        | Descripción |
|--------------------|---------------------|-------------|
| `iCodIndicacion`   | INT (PK, IDENTITY)  |             |
| `cIndicacion`      | CHAR(255)           |             |

> Valores  :
> - 01 APROBACIÓN  
> - 02 ATENCIÓN  
> - 03 SU CONOCIMIENTO  
> - 04 OPINIÓN  
> - 05 INFORME Y DEVOLVER  
> - 06 POR CORRESPONDERLE  
> - 07 PARA CONVERSAR  
> - 08 ACOMPAÑAR ANTECEDENTE  
> - 09 SEGÚN SOLICITADO  
> - 10 SEGÚN LO COORDINADO  
> - 11 ARCHIVAR  
> - 12 ACCIÓN INMEDIATA  
> - 13 PREPARE CONTESTACIÓN  
> - 14 PROYECTE RESOLUCIÓN  
> - 17 INDAGACIÓN DE MERCADO  

---

# 📄 Tabla: Tra_M_Tipo_Documento
## 🧱 Estructura de campos

| Campo          | Tipo de dato        | Descripción |
|-----------------|---------------------|-------------|
| `cCodTipoDoc`   | INT (PK, IDENTITY)  |             |
| `cDescTipoDoc`  | NVARCHAR(50)        |             |
| `cSiglaDoc`     | NVARCHAR(50)        |             |
| `nFlgInterno`   | INT                 |             |

> Tipos de documento internos (`nFlgInterno = 1`):
> - 7 INFORME  
> - 57 MEMORANDUM  
> - 68 OFICIO  
> - 97 PROVEÍDO  
> - 107 MEMORANDO CIRCULAR  
> - 108 NOTA INFORMATIVA  
> - 109 NOTA INFORMATIVA REQUERIMIENTO  
> - 110 RESOLUCIÓN ADMINISTRATIVA  
> - 111 RESOLUCIÓN DIRECTORAL  