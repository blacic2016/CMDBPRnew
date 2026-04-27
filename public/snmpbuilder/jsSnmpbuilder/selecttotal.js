function tree22 () {
fetch('listar-mibs.php') // Asegúrate de que la URL sea la correcta
        .then(response => response.text())
        .then(data => {
            // document.getElementById('mib').innerHTML = data;
			//console.log("dd");
			//console.log(JSON.parse(data));
			var newarr= new Array(JSON.parse(data));
			
//console.log("dd2");
			//console .log(newarr);
			
			
			//console.log(JSON.stringify(data));
			
			// TafelTreeInit (newarr);
			 
            })
            .catch(error => {
                console.error('Error al cargar el contenido PHP: ', error);
            });




var tree = null;

}
function tree(data)
 { 
		$.ajax({ 
				data:  {"path" : data.path},		
                url:   'listar-mibs2.php',
                type:  'get',
				dataType: "json",
				async:false,
                beforeSend: function () {                      
                },
                success:  function (response) 
					{
					document.getElementById('oid_discription').innerHTML = "";
					jstree(response);
					}
        });
		
	
   
 
}


function treeoidinfo(data) { 
//console.log("datas");
//console.log(data);   
		$.ajax({ 
				data:  {"oid" : data},		
                url:   'content_oid.php',
                type:  'get',
				dataType: "json",
				async:false,
                beforeSend: function () {                      
                },
                success:  function (response) {
				//console.log(response);
				 document.getElementById('oid_discription').innerHTML = response;

                }
        });
		
	
   
 
}


function treeoidvalue(data,iphost,snmpversion,comunidad) { 
//console.log("datas");
//console.log(data);   
		$.ajax({ 
				data:  {"oid" : data,"iphost" : iphost,"snmpversion" : snmpversion,"comunidad" : comunidad},		
                url:   'value_oid.php',
                type:  'get',
				dataType: "json",
				async:false,
                beforeSend: function () {                      
                },
                success:  function (response) {
                document.getElementById('oid_discription').innerHTML = response.info;
                let oidText = '';
                if (response.value) {
                    if (response.value.rowse && response.value.rowse[0]) {
                        oidText = '<div class="zabbix-tag zabbix-tag-blue"><strong><i class="fas fa-microchip mr-2"></i> OID Seleccionado:</strong> <br><span class="text-dark">' + response.value.rowse[0] + '</span></div>';
                    } else if (response.value.rowse && response.value.rowse.length > 0) {
                        oidText = '<div class="zabbix-tag zabbix-tag-blue"><strong><i class="fas fa-table mr-2"></i> OID de Tabla:</strong> <br><span class="text-dark">' + data + '</span></div>';
                    } else if (data) {
                        oidText = '<div class="zabbix-tag zabbix-tag-blue"><strong><i class="fas fa-tag mr-2"></i> OID MnemÃ³nico:</strong> <br><span class="text-dark">' + data + '</span></div>';
                    }
                }
                document.getElementById('oidlist').innerHTML = oidText;
                Tablaoid(response.value);

                }
        });
		
	
   
 
}






function jstree(data) { 

console.log(data);

 $('#jstree_demo').jstree('destroy');

$('#jstree_demo').on("click", function () {
		var instance = $('#jstree_demo').jstree(true);
		instance.deselect_all();
		instance.select_node('1');
	});
	
	$('#jstree_demo')
		.on("changed.jstree", function (e, data) {
			if(data.selected.length) {
				
				console.log(data);
				//alert('The selected node is: ' + data.instance.get_node(data.selected[0]).text);
				//treeoidinfo(data.selected[0])
				
				var iphost = document.getElementById("iphost").value;
				var snmpversion = document.getElementById("snmpversion").value;
				var comunidad = document.getElementById("comunidad").value;
				console.log(iphost);
				console.log(comunidad);
				treeoidvalue(data.selected[0],iphost,snmpversion,comunidad)
			}
		})
		.jstree({
			'core' : {
				'multiple' : false,
				'data' : data
			}
		});
		



}












 



function cargarOpcionesMib() {
   // const mibSelect = document.getElementById('mib');
    fetch('listar-archivos22.php') // Asegúrate de que la URL sea la correcta
        .then(response => response.text())
        .then(data => {
		//console.log(data);
             //document.getElementById('mib').innerHTML = data;
			select_cliente(JSON.parse(data));
            })
            .catch(error => {
                console.error('Error al cargar el contenido PHP: ', error);
            });
			
			document.getElementById('mib').addEventListener('change', function() {
    // Aquí puedes llamar a la función que deseas ejecutar cuando se cambie la selección
    // Por ejemplo, puedes llamar a la función cargarOpcionesMib() aquí si deseas recargar las opciones al cambiar la selección.
    // cargarOpcionesMib();
	const mibSelect = document.getElementById('mib');
	console.log(mibSelect);
});
}

function select_cliente(temp) 
{
	//console.log(temp);  	

//console.log("veamos");	
	 var $select_RZ = $('#mib');
   // $select_RZ.append('<option disabled selected>Elija la MIB</option>');
    $.each(temp.cliente, function(id, var_cliente)
		{
		var tarR= $select_RZ.append('<option value=' + id + '>' +  var_cliente.file+ '</option>');
		});
	$select_RZ.find('option:selected').prop('disabled', false);
	$select_RZ.selectpicker('refresh');
//002-a	
    var select = document.getElementById('mib');
    select.addEventListener('change',
    function() {
            var selectedOption = this.options[select.selectedIndex];
			//console.log(selectedOption);
            var ndex = selectedOption.value;
			//console.log(temp.cliente[ndex]);
			tree(temp.cliente[ndex]);
			//var cliente_syscloud=clientes[ndex].Empresa;			
	});
	}



function Tablaoidrerer(datatable) {
            // Datos proporcionados
			$("#miTabla").remove();
document.getElementById('oidview').innerHTML = "";

			//$("#oidview").remove();
            // Datos proporcionados
			console.log(datatable);
            var data = datatable;

            // Crear la tabla dinámicamente
            var tabla = $('<table>').attr('id', 'miTabla');
            var thead = $('<thead>').appendTo(tabla);
            var tbody = $('<tbody>').appendTo(tabla);

            // Crear encabezados de columna
            var encabezadoRow = $('<tr>').appendTo(thead);
            if (data && data.headers) { data.headers.forEach(function (encabezado) {
                $('<th>').text(encabezado).appendTo(encabezadoRow);
            }); }

            // Crear filas de datos

// Crear filas de datos

if(data && data.ret==1)
{ 

data.rowse.forEach(function (filae) {				
                var filaHtml = $('<tr>').appendTo(tbody);
							
                filae.forEach(function (dato) {
                    $('<td>').text(dato).appendTo(filaHtml);
                });
				
				
            });
	
	
	
	
}
else{
	
  // Crear fila de datos
            var filaHtml = $('<tr>').appendTo(tbody);
            data.rowse.forEach(function (dato) {
                $('<td>').text(dato).appendTo(filaHtml);
            });				
            
	
}

           

         

            // Agregar la tabla al cuerpo del documento
           $('#oidview').append(tabla);

            // Inicializar Dynatable
            $('#miTabla').dynatable();
        }     





function Tablaoid(datatable) {
    $("#miTabla").remove();
    document.getElementById("oidview").innerHTML = "";
    var data = datatable;
    if(!data) return;

    var tabla = $("<table>").attr("id", "miTabla");
    var thead = $("<thead>").appendTo(tabla);
    var tbody = $("<tbody>").appendTo(tabla);

    var encabezadoRow = $("<tr>").appendTo(thead);
    if (data && data.headers) {
        data.headers.forEach(function (encabezado) {
            $("<th>").text(encabezado).appendTo(encabezadoRow);
        });
    }

    if (data && data.ret == 1) {
        data.rowse.forEach(function (fila) {
            var filaHtml = $("<tr>").appendTo(tbody);
            fila.forEach(function (dato) {
                $("<td>").text(dato).appendTo(filaHtml);
            });
        });
    } else if (data && data.rowse) {
        var filaHtml = $("<tr>").appendTo(tbody);
        data.rowse.forEach(function (dato) {
            $("<td>").text(dato).appendTo(filaHtml);
        });
    }

    $("#oidview").append(tabla);

    // Initialize Dynatable only if we have headers to avoid errors
    if ($("#miTabla th").length > 0) {
        $("#miTabla").dynatable({
            features: {
                paginate: false,
                search: false     
            }
        });
    }

    // Pure DOM-based delegated tracking
    $("#miTabla").on("click", "tbody td", function () {
        var colIndex = $(this).index();
        var headerTitle = $("#miTabla th").eq(colIndex).text();
        var firstColValue = $(this).parent().find("td").eq(0).text();
        var dato = $(this).text();
        
        var fullOid = "";
        
        // Check if single-value mode or table mode
        if (headerTitle === "Oid/Name" || headerTitle === "Type" || headerTitle === "Value") {
            fullOid = firstColValue;
        } else {
            fullOid = headerTitle + "." + firstColValue;
        }
        
        handleCellClick(dato, fullOid, headerTitle);
    });

    function handleCellClick(dato, fullOid, headerTitle) {
        console.log("Celda clickeada: " + dato);
        
        document.getElementById("oidlist").innerHTML = '<div class="text-center mt-3 text-secondary"><i class="fas fa-spinner fa-spin"></i> TraducciÃ³n OID (snmptranslate)...</div>';
        
        $.ajax({
            url: "translate_oid.php",
            data: { oid: fullOid },
            dataType: "json",
            success: function(res) {
                let oidText = '<div class="zabbix-tag zabbix-tag-red">' +
                              '<strong><i class="fas fa-hand-pointer mr-2"></i> SelecciÃ³n OID (SNMP-TRANSLATE):</strong><hr class="my-1" style="border-color:#bac6cb;">' +
                              '<div class="text-dark" style="user-select:all; font-size: 0.85em; font-weight:bold; margin-bottom: 5px; word-break: break-all;">Toda la OID: ' + res.fulltext + '</div>' +
                              '<div class="text-primary" style="user-select:all; font-weight:bold; margin-bottom: 5px; word-break: break-all;">NumÃ©rico (1.3.6...): ' + res.numeric + '</div>' +
                              '<div class="text-danger mt-2" style="font-size: 1.1em; font-weight:bold; background-color:#fce4e4; padding:5px; border-radius:3px;">Valor: ' + dato + '</div>' +
                              '</div>';
                document.getElementById("oidlist").innerHTML = oidText;
            },
            error: function() {
                let oidText = '<div class="zabbix-tag zabbix-tag-red">' +
                              '<div class="text-dark" style="user-select:all; font-weight:bold; margin-bottom: 5px;">MnemÃ³nico: ' + fullOid + '</div>' +
                              '<div class="text-danger mt-2" style="font-size: 1.1em; font-weight:bold; background-color:#fce4e4; padding:5px; border-radius:3px;">Valor: ' + dato + '</div>' +
                              '</div>';
                document.getElementById("oidlist").innerHTML = oidText;
            }
        });
        
        // Calls the SNMP interface to get the true description and dump to left panel
        treeoidinfo(fullOid);
    }
}