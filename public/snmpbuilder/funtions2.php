
<?php
function get_module_name($filename)
{
	$modulename = '';
	$handle = @fopen($filename, "r");
	if ($handle) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 4096);
			if (preg_match('/^\s*(\S+)\s*DEFINITIONS\s*::=\s*BEGIN/i',$buffer,$matches))
			{
				$modulename = $matches[1];
				break;
			}
			
		}
		fclose($handle);
	}
	echo $modulename;
	return ($modulename);
}
function get_oid_from_name($name)
{
	$oid = exec("snmptranslate -M ".MIBS_ALL_PATH." -m ALL -On $name");
	
	if (preg_match('/[0123456789\.]+/', $oid))
		return $oid;
	else
		return null;
}

function get_table_value($community, $server_ip, $oid)
{
	// table view
	$rowse = array();
	if ($server_ip =="")
	{
		$rowse[0] = array("No server ip.");
	}
	else
	{
		exec("snmptable -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid -Ci -Ch -Cf \",\"", $results);
		$headers = explode(",",$results[0]);
		unset($results);
		exec("snmptable -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid -Ci -CH -Cf \",\"", $results);
		foreach ($results as $line)
		{
			$row = explode(",",$line);
			array_push($rowse, $row);
		}
		unset($results);
	}
	
			
	$value = array('ret' => 1,'headers' => $headers, 'rowse' => $rowse);
	return ($value);
}

function get_oid_value($community, $server_ip, $oid, $idx)
{	
	if (!$server_ip){
		$row = array('Missing server ip.','','');
		$value = array('ret' => 0,'rowse' => $row);
		return ($value);
	}
	
	// idx is number or string thank danrog
	if (preg_match('/^[0-9]+$/', $idx)) {
		$cmd = "snmpget -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid.$idx";  
    } else {        
		$cmd = "snmpget -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid.\"".$idx."\"";
    }	
	$results = exec($cmd);
	
	//exampe: IP-MIB::ipOutRequests.0 = Counter32: 12303729
	if (preg_match('/^(\S+) = (\S+): (.+)$/i', $results, $matches)) // full information
	{
		$row = array($matches[1], $matches[2], $matches[3]);
	}
	else if (preg_match('/^(\S+) = (\S+):$/i', $results, $matches)) //no value
	{
		$row = array($matches[1], $matches[2],'');
	}
	else if (preg_match('/^(\S+) = (.+)$/i', $results, $matches)) //no type
	{
		$row = array($matches[1], '',$matches[2]);
	}
	else // error
		$row = array($results,'','');
		
		$headers=  array('Oid/Name','Type','Value');
		
	$value = array('ret' => 0,'headers' => $headers,'rowse' => $row);
	return ($value);
}

function get_oid_content($oid)
	{
	exec("snmptranslate -Td -OS -M ".MIBS_ALL_PATH." -m ALL $oid", $results);
		
	$content = implode("<br>",$results);
	return ($content);
	}

//Get oid tree per mib 
function get_oid_tree($mib)
	{
	exec("snmptranslate -Ts -M ".MIBS_ALL_PATH." -m $mib 2>&1", $results);
	$oid_tree = explodeTree($mib, $results);
	return $oid_tree;
	}

function json_error($msg)
	{
	echo json_encode(array('error' => $msg));
	exit();
	}

function explodeTree($mib, $array, $delimiter = '.')
{
	if(!is_array($array)) return false;
	$splitRE   = '/' . preg_quote($delimiter, '/') . '/';
	$returnArr['id']='';
	$returnArr['text']=$mib;
	$returnArr['icon'] = 'imgs/globe.gif';
	//$returnArr['imgclose'] = 'imgs/globe.gif';
	$returnArr['children']=array(array('id'=>'.iso','text'=>'iso','icon'=>'imgs/page.gif'),array('id'=>'.ccitt','text'=>'ccitt','icon'=>'imgs/page.gif'));

    foreach ($array as $key) {
		
        // Get parent parts and the current leaf
        $parts    = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
		$leaf = array_pop($parts);
		$parentArr = &$returnArr;
	
	foreach ($parts as $part) 
		{
			$child_id = $parentArr['id'].'.'.$part;
			if (!isset($parentArr['children']))
				$parentArr['children'] = array();
		
			for ($i = 0; $i <count($parentArr['children']); $i++)
				{
					if ($parentArr['children'][$i]['id'] == $child_id)
					//	{
							
						break;
					//	}
					//else
					//	{
					//	$parentArr['children'][$i]['icon'] = 'imgs/page.gif';
					//	}
				}
		
			if (!isset($parentArr['children'][$i]))
				{
					echo $child_id." ".$leaf." ".$key;
					exit();
				}
		
			$parentArr = &$parentArr['children'][$i];
			
		}
		

		if (!isset($parentArr['children']))
				$parentArr['children'] = array();
				$i = count($parentArr['children']);
				$parentArr['children'][$i]['id'] = $key;
				$parentArr['children'][$i]['text'] = $leaf;
				//$parentArr['children'][$i]['icon'] = 'imgs/page.gif';
				
				if  (preg_match('/^\w+Table$/',$leaf))
					{
					$parentArr['children'][$i]['icon'] = 'imgs/table.gif';
					$parentArr['children'][$i]['imgclose'] = 'imgs/table.gif';
					}
	
    }
    
    return $returnArr;
}
?>