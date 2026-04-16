<?php
require_once('funtions2.php');
define('MIBS_ALL_PATH', '/var/www/html/snmp/mibs:/var/lib/mibs/ietf');
$oid=filter_var($_GET['oid'],FILTER_SANITIZE_STRING);
$server_ip=filter_var($_GET['iphost'],FILTER_SANITIZE_STRING);
$snmpversion=filter_var($_GET['snmpversion'],FILTER_SANITIZE_STRING);
$community=filter_var($_GET['comunidad'],FILTER_SANITIZE_STRING);

if (isset($_REQUEST['oid']) && !empty($_REQUEST['oid']))
	{
		if (!preg_match('/^[a-z,0-9,\.,\-,\:]+$/i', $_REQUEST['oid']))
		{
			json_error('Invalid oid '.$_REQUEST['oid']);
		}
		$oid = escapeshellcmd($_REQUEST['oid']);
	}
	else
		$oid ='';
	
	if (isset($_REQUEST['idx']) && !empty($_REQUEST['idx']))
	{
		$idx = escapeshellcmd($_REQUEST['idx']);
	}
	else
		$idx =0;
		
	if (isset($_REQUEST['iphost']) && !empty($_REQUEST['iphost']))
	{
		if (!preg_match('/^[0-9,\.]+$/i', $_REQUEST['iphost']))
		{
			json_error('Invalid server ip '.$_REQUEST['iphost']);
		}
		$server_ip = escapeshellcmd($_REQUEST['iphost']);
	}
	else
		$server_ip ='';
		
	if (isset($_REQUEST['comunidad']) && !empty($_REQUEST['comunidad']))
	{
		$community = escapeshellcmd($_REQUEST['comunidad']);
	}
	else
		$community ='public';


//if (!$oid || !$mib)
		//{
		//	json_error('Missing oid or mib');
		//	exit;
		//}	
		$content = get_oid_content($oid);



if ($viewtype == 1 || preg_match('/Table$/',$oid)) 
		{
			$value = get_table_value($community, $server_ip, $oid);
		}
		else {
			//value
			$value = get_oid_value($community, $server_ip, $oid, $idx);
			if ($content == '') //Fix for table cells
			{
				$content = get_oid_content(escapeshellcmd($value['row'][0]));
			}
		}
		

$json = json_encode(array('info' => $content, 'value' => $value));
		echo $json;
		exit;



//echo $comunidad;

//echo $oid;

//echo $iphost;
//$filename="/var/www/html/snmp/".$path;

//$oid_mib = escapeshellcmd($oid);

//$oid_tree = get_oid_value($comunidad, $iphost, $oid, $idx);


//echo  json_encode($oid_tree);


?>
