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
				console.log(response);
				
				// document.getElementById('oidview').innerHTML = response.value.row;
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
            data.headers.forEach(function (encabezado) {
                $('<th>').text(encabezado).appendTo(encabezadoRow);
            });

            // Crear filas de datos

// Crear filas de datos

if(data.ret==1)
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
    document.getElementById('oidview').innerHTML = "";

    // Datos proporcionados
    console.log(datatable);
    var data = datatable;

    // Crear la tabla dinámicamente
    var tabla = $('<table>').attr('id', 'miTabla');
    var thead = $('<thead>').appendTo(tabla);
    var tbody = $('<tbody>').appendTo(tabla);

    // Crear encabezados de columna
    var encabezadoRow = $('<tr>').appendTo(thead);
    data.headers.forEach(function (encabezado) {
        $('<th>').text(encabezado).appendTo(encabezadoRow);
    });

    // Crear filas de datos
    if (data.ret == 1) {
        data.rowse.forEach(function (fila, rowIndex) {
            var filaHtml = $('<tr>').appendTo(tbody);
            fila.forEach(function (dato, colIndex) {
                var celda = $('<td>').text(dato).appendTo(filaHtml);
                // Agregar evento click a cada celda utilizando delegación de eventos
                celda.click(function () {
                    // Obtener el índice de la fila, la columna y el título del header
                    var clickedRowIndex = rowIndex;
                    var clickedColIndex = colIndex;
                    var clickedHeaderTitle = data.headers[colIndex];
                    handleCellClick(dato, clickedRowIndex, clickedColIndex, clickedHeaderTitle);
                });
            });
        });
    } else {
        // Crear fila de datos
        var filaHtml = $('<tr>').appendTo(tbody);
        data.rowse.forEach(function (dato, colIndex) {
            var celda = $('<td>').text(dato).appendTo(filaHtml);
            // Agregar evento click a cada celda utilizando delegación de eventos
            celda.click(function () {
                // Obtener el índice de la fila, la columna y el título del header
                var clickedRowIndex = 0; // Assuming there is only one row in this case
                var clickedColIndex = colIndex;
                var clickedHeaderTitle = data.headers[colIndex];
                handleCellClick(dato, clickedRowIndex, clickedColIndex, clickedHeaderTitle);
            });
        });
    }

    // Agregar la tabla al cuerpo del documento
    $('#oidview').append(tabla);

    // Inicializar Dynatable
    $('#miTabla').dynatable({
        features: {
            paginate: false, // Desactiva la paginación si no la necesitas
            search: false     // Desactiva la búsqueda si no la necesitas
        }
        // Otras opciones aquí
    });

    // Función para manejar el clic en la celda
    function handleCellClick(dato, rowIndex, colIndex, headerTitle) {
        console.log('Celda clickeada: ' + dato);
        console.log('Índice de fila: ' + rowIndex);
        console.log('Índice de columna: ' + colIndex);
        console.log('Título del encabezado: ' + headerTitle);
        // Agregar más lógica según sea necesario
    }
}
