function cargarOpcionesMib() {
	'use strict';
   
    fetch('listar-archivos22.php') // Asegúrate de que la URL sea la correcta
        .then(response => response.text())
        .then(data => {
		
			select_cliente(JSON.parse(data));
            })
            .catch(error => {
                console.error('Error al cargar el contenido PHP: ', error);
            });
			
			document.getElementById('mib').addEventListener('change', function() {
    
	const mibSelect = document.getElementById('mib');
	console.log(mibSelect);
});
}



// function select_cliente(temp) 
// {
	// console.log(temp);  	
// var jQueryAlias = jQuery.noConflict(true);
// console.log("veamos");	
	 // var $select_RZ = $('#mib');
    // $select_RZ.append('<option disabled selected>Elija la Razon Social</option>');
    // $.each(temp.cliente, function(id, var_cliente)
		// {
		// var tarR= $select_RZ.append('<option value=' + id + '>' +  var_cliente.file+ '</option>');
		// });
	// $select_RZ.find('option:selected').prop('disabled', false);
	// $select_RZ.selectpicker('refresh');
	
    // var select = document.getElementById('mib');
    // select.addEventListener('change',
    // function() {
            // var selectedOption = this.options[select.selectedIndex];
			// console.log(selectedOption);
            // var ndex = selectedOption.value;
			// console.log(temp.cliente[ndex]);
			// tree(temp.cliente[ndex]);
			//var cliente_syscloud=clientes[ndex].Empresa;			
	// });
	// }

function select_cliente(temp) {
    console.log(temp);
    var jQueryAlias = jQuery.noConflict(true);
    console.log("veamos");

    var $select_RZ = jQueryAlias('#mib');
    $select_RZ.append('<option disabled selected>Elija la Razon Social</option>');

    jQueryAlias.each(temp.cliente, function(id, var_cliente) {
        var tarR = $select_RZ.append('<option value=' + id + '>' + var_cliente.file + '</option>');
    });

    $select_RZ.find('option:selected').prop('disabled', false);
    $select_RZ.selectpicker('refresh');

    //002-a    
    var select = document.getElementById('mib');
    select.addEventListener('change', function() {
        var selectedOption = this.options[select.selectedIndex];
        console.log(selectedOption);
        var ndex = selectedOption.value;
        console.log(temp.cliente[ndex]);
        tree(temp.cliente[ndex]);
        //var cliente_syscloud=clientes[ndex].Empresa;
    });
}




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
function tree(data) { 

console.log(data);   
		$.ajax({ 
				data:  {"path" : data.path},		
                url:   'listar-mibs2.php',
                type:  'get',
				dataType: "json",
				async:false,
                beforeSend: function () {                      
                },
                success:  function (response) {
				//clientes=response.cliente;
				console.log(response);		
				console.log("Paso 1");	
				TafelTreeInit (response)
//funcion selectsr para clientes select.js				
				//selectsr(response);
                }
        });
		
	
   
 
}








