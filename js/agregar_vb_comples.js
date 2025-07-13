const oficinaContent = document.getElementById("oficinaContent");
    const trabajadoresBody= document.getElementById("trabajadoresBody");
    const selectionForm= document.getElementById("selectForm");
    const searchInput = document.getElementById("oficinaAutoComplete");

    searchInput.addEventListener("input", function(event) {
        // TODO functionality
        let filter = event.target.value.toLowerCase();
        // const oficinas = <?= json_encode($oficinasObj) ?>;

        console.log(filter);
    })


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
                <input type="hidden" name="addTrabajador[]" value="${trabajador.iCodTrabajador}|"/>
                <div>${trabajador.cNombresTrabajador} ${trabajador.cApellidosTrabajador}</div>
                <div style="display: flex; align-items:center; gap: 0.5rem;">
                    <div class="popoverContainer" style="position: relative;">
                        <button type="button" class="btn-primary openFileSelect">Seleccionar archivos</button>
                        <div class="popover" style="z-index: 20; pointer-events: none; opacity: 0; padding: 0.75rem; border-radius: 8px; background-color: white; font-size: var(--font-sm); position: absolute; top: 100%; left: 0; width: 16rem; box-shadow: 0px 5px 10px rgba(0,0,0,0.25);">
                        </div>
                    </div>
                    <button type="button" class="icon-btn-danger " onclick="removerTrabajadorC('trabajador_${trabajador.iCodTrabajador}')">
                        <i class="material-icons">delete</i>
                    </button>
                </div>
            `
            listadoFirmantesBody.append(div);

            for(const tra of tramites){
                const subDiv = document.createElement("div");
                subDiv.innerHTML = `
                    <input id="item_${tra.iCodDigital}" type="checkbox" name="comples" value="${tra.iCodDigital}">
                    <label for="item_${tra.iCodDigital}">${tra.cNombreOriginal}</label>
                `
                const popover = div.querySelector(".popover");
                popover.append(subDiv);
                const options = popover.querySelectorAll('input[type="checkbox"][id^="item_"]');

                options.forEach((op) => {
                    op.addEventListener("change",function(){
                        let input = div.querySelector("input[name='addTrabajador[]']").value;

                        if(op.checked){
                            console.log("agregando: ", op.value, input);
                            if(!input.split("|")[1].split("_").includes(this.value)){
                                
                                let temp = input.split("|")[1].split("_")
                                temp[0] === "" ? temp[0] = this.value : temp.push(this.value);
                                let agg = temp.length > 1 ? temp.join("_") : temp[0]
                                let newInput =`${input.split("|")[0]}|${agg}`;
                                div.querySelector("input[name='addTrabajador[]']").value = newInput;
                            }
                        } else {
                            console.log("removiendo: ", op.value, input)
                            const files = input.split("|")[1].split("_");
                            if(files.includes(this.value)){
                                const index = [...files].indexOf(this.value);
                                files.splice(index, 1);
                                let newInput = `${input.split("|")[0]}|${files.join("_")}`;
                                div.querySelector("input[name='addTrabajador[]']").value = newInput;
                            }
                        }
                    });
                })
            }
            
            const popup = div.querySelector(".popoverContainer .popover");
            const popupBtn = div.querySelector(".popoverContainer .openFileSelect");

            popupBtn.addEventListener("click", function(){
                
                popup.style.opacity = popup.style.opacity == 1 ? 0 : 1;
                popup.style.pointerEvents = popup.style.pointerEvents == "none" ? "auto" : "none";
            })
        }
        selectionForm.reset();
    })


    function removerTrabajadorC(id){
        listadoFirmantesBody.querySelector(`#${id}`).remove();
    }

    const formFirmantes = document.getElementById("listadoFirmantes");

    formFirmantes.addEventListener("submit", async function(e){
        e.preventDefault();
        try {
            const body = new FormData(this);
            const res = await fetch("agregarFirmantes.php",{
                method: "POST",
                body
            });
            const {status, message} = await res.json();
            if(status === "error"){
                throw new Error(message);
            }
            document.getElementById("vbcomples").style.display = "none";
        } catch (error) {
            console.error(error)
        }
    })