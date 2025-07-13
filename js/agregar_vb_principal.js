const oficinaContent = document.getElementById("oficinaContent");
const trabajadoresBody= document.getElementById("trabajadoresBody");
const selectionForm = document.getElementById("selectForm");

document.getElementById("oficinaOpen").addEventListener("click", function() {
    document.getElementById("oficinaSelection").showModal();
})

document.querySelectorAll(".oficinaOption").forEach((option) => {
    option.addEventListener("click", async function (){
        try{
            const body = new FormData();
            body.append("iCodOficina",this.dataset.value)
            const res = await fetch("registroTrabajadoresBusqueda.php",{
                method: "POST",
                body
            })
            const {status, message, data} = await res.json();

            if(status === "error"){
                throw new Error(message);
            }

            let html = "";
            for(const trabajador of data){
                html += "<tr data-cod-trab='" + trabajador.iCodTrabajador + " " + trabajador.cNombresTrabajador + "'>";
                html += "<td>" + trabajador.cNombresTrabajador + trabajador.cApellidosTrabajador + "</td>";
                html += "<td>" + trabajador.cSiglaOficina + "</td>";
                html += "<td>" + trabajador.cDescPerfil + "</td>";
                html += "<td align='center'><input type='checkbox' name='lstTrabSel[]' value='" + trabajador.iCodTrabajador + "'></td>";
                html += "</tr>";
            }

            console.log(html);

            if(this.dataset.value === ""){
                oficinaContent.innerHTML = "BUSCAR OFICINAS";
            } else {
                oficinaContent.innerHTML =`
                    <div style="font-weight: bold;">${this.dataset.sigla}</div>
                    <div style="font-size: 0.6rem; text-align: left;">${this.dataset.name}</div>
                `;
            }
            trabajadoresBody.innerHTML = html;
        } catch(error){
            console.error(error)
        } finally {
            document.getElementById("oficinaSelection").close();
        }
    })
})

selectionForm.addEventListener("submit", async function(e) {
    e.preventDefault();

    const body = new FormData(this);
    const res = await fetch("./getTrabajadores.php",{
        method: "POST",
        body
    });
    const { data, tramites } = await res.json();

    let container;

    for (const trabajador of data){
        const div = document.createElement("div");
        div.setAttribute(
            "style",
            "width: 100%, position: relative; border-radius: 8px; background-color: var(--light); padding: 1rem; display: flex; align-items: center; justify-content: space-between"
        );
        div.setAttribute("data-trabajador", trabajador.iCodTrabajador);
        div.setAttribute("id", `trabajador_${trabajador.iCodTrabajador}`);
        div.innerHTML = `
            <input type="hidden" name="addTrabajador[]" value="${trabajador.iCodTrabajador}"/>
            <div>${trabajador.cNombresTrabajador} ${trabajador.cApellidosTrabajador}</div>
            <div style="display: flex; align-items:center; gap: 0.5rem;">
                <button type="button" class="icon-btn-danger " onclick="removerTrabajadorP('trabajador_${trabajador.iCodTrabajador}')">
                    <i class="material-icons">delete</i>
                </button>
            </div>
        `
        listadoFirmantesBody.append(div);
    }
    selectionForm.reset();
})


function removerTrabajadorP(id){
    listadoFirmantesBody.querySelector(`#${id}`).remove();
}

const formFirmantes = document.getElementById("listadoFirmantes");

formFirmantes.addEventListener("submit", async function(e){
    e.preventDefault();
    tinyMCE.triggerSave();
    const form = document.getElementById("formularioRegistro");
    const contenido = new FormData(form);
    try {
        contenido.append("generar",true);

        const resGen = await fetch("./exportarTramitePDF.php",{
            method: "POST",
            body: contenido
        })

        const dataGen = await resGen.json();

        if(dataGen.status === "error"){
            throw new Error(dataGen.message);
        }

        const body = new FormData(this);
        body.append("operacion", "principal");

        const res = await fetch("agregarFirmantes.php",{
            method: "POST",
            body
        });
        const {status, message} = await res.json();
        if(status === "error"){
            throw new Error(message);
        }
        document.getElementById("vbprincipal").style.display = "none";
        top.location = "BandejaEnviados.php";
    } catch (error) {
        console.error(error)
    }
})