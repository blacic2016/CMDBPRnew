<?php
require_once('funtions2.php');
define('MIBS_ALL_PATH', 'mibs');
$mainDir = 'mibs'; // Ruta de la carpeta principal
$options = ''; // Inicializa la variable para las opciones del select

// Escanea la carpeta principal
$files = scandir($mainDir);

// Filtra los elementos no deseados (por ejemplo, '.' y '..')
$files = array_diff($files, array('.', '..'));

// Ordena el array
sort($files);
$cont_sysl=0;
// Recorre la lista de archivos y carpetass
foreach ($files as $file) {
    $fileOrDirPath = $mainDir . '/' . $file;


    if (is_dir($fileOrDirPath)) {
        // Si es una carpeta, escanéala y agrega sus archivos al select
        $subFiles = scandir($fileOrDirPath);
        $subFiles = array_diff($subFiles, array('.', '..'));
        sort($subFiles);      

        foreach ($subFiles as $subFile) {        
			
			
			
			
			
			$data['cliente'][$cont_sysl]['path']=$fileOrDirPath."/".$subFile;
			$data['cliente'][$cont_sysl]['file']=$subFile;	
			$data['cliente'][$cont_sysl]['main']=$mainDir;
			$data['cliente'][$cont_sysl]['sub']=$file;			
			$cont_sysl=$cont_sysl+1;
			
		
        }

        $options .= '<optgroup label="' . $file . '">' . $subOptions . '</optgroup>';
    } else {
        // Si es un archivo, agrega el archivo al select
        $options .= '<option value="' . $file . '">' . $file . '</option>';
		

	$subfilee=explode("/",$fileOrDirPath);
	$data['cliente'][$cont_sysl]['path']=$fileOrDirPath;
		$data['cliente'][$cont_sysl]['file']=$subfilee[1];
			$data['cliente'][$cont_sysl]['main']=$mainDir;	
		
			$data['cliente'][$cont_sysl]['sub']=$mainDir;			
			$cont_sysl=$cont_sysl+1;
		
		
		
    }
}


$json_string = json_encode($data, JSON_PRETTY_PRINT);	
echo $json_string;

?>
