
	function loadResponsables(value)
	{
		var parametros = {
			"iCodOficinaResponsable"    : value
		};
		var dominio = document.domain;
		$("#responsable > option").remove(); 

	    $.ajax({
	        type: 'POST',
	        url: 'loadResponsable.php', 
	        data: parametros, 
	        dataType: 'json',
	        success: function(list){
	        	console.log(list);
	            var opt = $('<option />'); 
                        opt.val('');
                        opt.text('Seleccione un responsable');
                        $('#responsable').append(opt);
                        $.each(list,function(index,value) 
                        {
                        	console.log(value);
                            var opt = $('<option />'); 
                            opt.val(value.iCodTrabajador);
                            opt.text(value.cNombresTrabajador+" "+value.cApellidosTrabajador+" "+value.encargado);
                            // if (value.encargado.localeCompare('1') == 0) {
                            // 	opt.text(value.cNombresTrabajador+" "+value.cApellidosTrabajador+" | (Encargado)");
                            // }else{
                            // 	opt.text(value.cNombresTrabajador+" "+value.cApellidosTrabajador);
                            // }
                            $('#responsable').append(opt); 
                        });
	        },
	        error: function(e){
	        	console.log(e) == 0
	            alert('Error Processing your Request!!');
	        }
	    });
	}

	function loadProcedimiento(value)
	{
		var parametros = {
			"iCodTupaClase"    : value
		    };
		    
		$("#iCodTupa > option").remove(); 

	    $.ajax({
	        type: 'POST',
	        url: 'loadProcedimiento.php', 
	        data: parametros, 
	        dataType: 'json',
	        success: function(list){
	        	console.log(list);
	            var opt = $('<option />'); 
                        opt.val('');
                        opt.text('Seleccione un procedimiento');
                        $('#iCodTupa').append(opt);
                        $.each(list,function(index,value) 
                        {
                        	console.log(value);
                            var opt = $('<option />'); 
                            opt.val(value.iCodTupa);
                            opt.text(value.cNomTupa);
                            $('#iCodTupa').append(opt); 
                        });
	        },
	        error: function(e){
	        	console.log(e);
	            alert('Error Processing your Request!!');
	        }
	    });
	}