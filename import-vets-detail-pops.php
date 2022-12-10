<?// import-vets-detail-pops.php

/*
UNDER CONSTRUCTION 11/28/2010

This script parses a Bluewave Sitter Detail Report, .

Sample: S:\clientimports\waggingtail\Wagging-Tails-Sitter-1.htm
https://leashtime.com/import-vets-detail-pops.php?file=ksrpetcare/vets1.aspx.htm

*/
set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
locked('o-');
extract($_REQUEST);


if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>DB: $db</h2>";
echo "<hr>";

$strm = fopen("/var/data/clientimports/$file", 'r');
while(getLine($strm)) {
	if(strpos($line, 'input name="tbName_') !== FALSE || strpos($line, 'name=tbName_') !== FALSE) {
		if($vet) $vets[] = $vet;
		$vet = array();
		$vet['name'] = getValue($line);
	}
	else if(strpos($line, $pat='name="tbPhone_') !== FALSE || strpos($line, $pat='name=tbPhone_') !== FALSE) {
		$vet['phone1'] = getValue($line);
		$vet['phone2'] = getValue($line, 'name="tbAltPhone_');
	}
	else if(strpos($line, $pat='name=tbAltPhone_') !== FALSE) $vet['phone2'] =  getValue($line);
	else if(strpos($line, 'name="tbAddress1_') !== FALSE || strpos($line, 'name=tbAddress1_') !== FALSE) $vet['street1'] = getValue($line); // nickname
	else if(strpos($line, 'name="tbAddress2_') !== FALSE || strpos($line, 'name=tbAddress2_') !== FALSE) $vet['street2'] = getValue($line); // nickname
	else if(strpos($line, 'name="tbCity_1') !== FALSE || strpos($line, 'name=tbCity_1') !== FALSE) $vet['city'] = getValue($line);
	else if(strpos($line, 'option selected="selected"') !== FALSE || strpos($line, 'OPTION selected') !== FALSE) $vet['state'] = getValue($line);
	else if(strpos($line, 'name="tbZipcode_') !== FALSE || strpos($line, 'name=tbZipcode_') !== FALSE) $vet['zip'] = getValue($line); // workphone
}
$clinicNames = fetchCol0("SELECT clinicname FROM tblclinic");
foreach($vets as $i => $vet) {
	if(!$vet['name']) continue;
	if(in_array($vet['name'], $clinicNames)) {
		echo "<font color=red>Clinic {$vet['name']} already exists.<br></font>";
		continue;
	}
	$clinic = array('clinicname'=>$vet['name'],
									'officephone'=>$vet['phone1'],
									'cellphone'=>$vet['phone2'],
									'street1'=>$vet['street1'],
									'street2'=>$vet['street2'],
									'city'=>$vet['city'],
									'state'=>$vet['state'],
									'zip'=>$vet['zip']);
	insertTable('tblclinic', $clinic, 1);
	if(!mysql_error()) {
		echo "Vet #".mysql_insert_id()." [{$vet['name']}] added.<br>";
		$n++;
	}
}
echo "$n vets added.";
	
function addVet(&$vet) {
	// str_rmrk
	if(!$vet) {
		echo "<font color=red>No sitter id found: ".print_r($vet, 1)."</font><br>";
		return;
	}
	$prov = fetchFirstAssoc("SELECT * FROM tblprovider WHERE employeeid = {$vet['str_ID']} LIMIT 1");
	if(!$prov) {
		echo "<font color=red>No sitter found with employeeid = {$vet['str_ID']}".print_r($sitter, 1)."</font><br>";
		return;
	}
	$sitter['providerid'] = $prov['providerid'];
	$sitter['provideremail'] = $prov['email'];
	$sitter['provideruserid'] = $prov['userid'];
	echo "BW sitter {$sitter['str_ID']} = LT sitter {$prov['providerid']}<br>";
	$mods['taxid'] = $sitter['ssn'];
	$mods['street1'] = $sitter['str_address1'];
	$mods['street2'] = $sitter['str_address2'];
	$mods['city'] = $sitter['str_city'];
	$mods['state'] = $sitter['str_st'];
	$mods['zip'] = $sitter['str_zip'];
	$mods['nickname'] = $sitter['str_fl_nm'];
	$mods['hiredate'] = $sitter['str_strt_dt'];
	$mods['terminationdate'] = $sitter['str_end_dt'];
	$mods['ratetype'] = $sitter['compensation'];
	$noteParts = explodePairsLine('Birthday|dob||Email2|str_eml2||Area|str_grphy||Text Message Address|str_txtmsg_adrs||Remarks|str_rmrk');
	foreach($noteParts as $label => $val)
		if(trim($sitter[$val])) $notes[] = "$label: ".($label == 'Remarks' ? "\n" : "").$sitter[$val];
	if($notes) $mods['notes'] = join("\n", $notes);
	foreach($mods as $k=>$v) if(!$v) unset($mods[$k]);
	echo "<b>{$prov['fname']} {$prov['lname']}</b><br>";foreach($mods as $k=>$v) echo "$k: ".($k=='notes' ? "<textarea row=5 cols=60>$v</textarea>" : $v)."<br>";
	updateTable('tblprovider', $mods, "providerid = {$prov['providerid']}", 1);
	//echo "Login ID: {$sitter['str_cd']}<br>";
}

function readVet($file) {
	global $line;
//echo "<font color=lightblue>$file</font><br>";	
	$strm = fopen($file, 'r');
	while(getLine($strm)) {
		if(strpos($line, 'tbName_0') !== FALSE) $vet['str_lst_nm'] = getValue($line);
		else if(strpos($line, 'str_frst_nm') !== FALSE) $vet['str_frst_nm'] = getValue($line);
		else if(strpos($line, 'str_fl_nm') !== FALSE) $vet['str_fl_nm'] = getValue($line); // nickname
		else if(strpos($line, 'str_phn_hm') !== FALSE) $vet['str_phn_hm'] = getValue($line);
		else if(strpos($line, 'str_phn_mbl') !== FALSE) $vet['str_phn_mbl'] = getValue($line);
		else if(strpos($line, 'str_phn_pgr') !== FALSE) $vet['str_phn_pgr'] = getValue($line); // workphone
		else if(strpos($line, 'str_grphy') !== FALSE) $vet['str_grphy'] = getValue($line); // area
		else if(strpos($line, 'str_eml"') !== FALSE) $vet['str_eml'] = getValue($line); // email
		else if(strpos($line, 'str_eml2') !== FALSE) $vet['str_eml2'] = getValue($line); // alternate email
		else if(strpos($line, 'str_txtmsg_adrs') !== FALSE) $vet['str_txtmsg_adrs'] = getValue($line); // text message addr
		else if(strpos($line, 'str_address1') !== FALSE) $vet['str_address1'] = getValue($line); // 
		else if(strpos($line, 'str_address2') !== FALSE) $vet['str_address2'] = getValue($line); // 
		else if(strpos($line, 'str_city') !== FALSE) $vet['str_city'] = getValue($line); // 
		else if(strpos($line, 'str_st"') !== FALSE) $vet['str_st'] = getValue($line); // 
		else if(strpos($line, 'str_zip') !== FALSE) $vet['str_zip'] = getValue($line); // 
		else if(strpos($line, 'dob') !== FALSE) $vet['dob'] = getValue($line); // 
		else if(strpos($line, 'ssn') !== FALSE) $vet['ssn'] = getValue($line); // 
		else if(strpos($line, 'str_strt_dt"') !== FALSE) $vet['str_strt_dt'] = getValue($line, 'str_strt_dt"'); // 
		else if(strpos($line, 'str_end_dt"') !== FALSE) $vet['str_end_dt'] = getValue($line, 'str_end_dt"'); // end date

		//						<option value="P" selected selected>Percentage</option>
		//					<option value="F" >Fixed</option>

		else if(strpos($line, 'selected>Percentage') !== FALSE) $vet['compensation'] = 'percentage'; // 
		else if(strpos($line, 'selected>Fixed') !== FALSE) $vet['compensation'] = 'fixed'; // 
		else if(strpos($line, 'str_cmpnstn') !== FALSE) $vet['str_cmpnstn'] = getValue($line); // end date
		else if(strpos($line, 'str_cd"') !== FALSE) $vet['str_cd'] = getValue($line); // username
		else if(strpos($line, 'value="A" selected>Admin') !== FALSE) $vet['accessrights'] = 'admin'; // 
		else if(strpos($line, 'value="E" selected>Full') !== FALSE) $vet['accessrights'] = 'full'; // 
		else if(strpos($line, 'value="R" selected>Limited') !== FALSE) $vet['accessrights'] = 'limited'; // 
		else if(strpos($line, 'value="O" selected>Owner') !== FALSE) $vet['accessrights'] = 'owner'; // 
		else if(strpos($line, 'str_ID') !== FALSE) $vet['str_ID'] = getValue($line); // sitter ID
		else if(strpos($line, 'str_rmrk') !== FALSE) {
			$vet['str_rmrk'] = getTextAreaContents($line);
			$readingTextArea = strpos($line, '</textarea') === FALSE;
		}
		else if($readingTextArea) {
			$vet['str_rmrk'] .= getTextAreaContents($line);
			$readingTextArea = strpos($line, '</textarea') === FALSE;
		}

	}
	//print_r($vet);
	//addSitter($vet);	
	fclose($strm);
	return $vet;
}

function getTextAreaContents($line) {
	if(($start = strpos($line, '<textarea')) !== FALSE) {
		$start = strpos($line, '>', $start)+1;
		if($end = strpos($line, '</textarea', $start))
			return substr($line, $start, $end-$start);
		else return substr($line, $start);
	}
	else if($end = strpos($line, '</textarea') === FALSE)
		return "\n".substr($line, $start, $end-$start);
	else return "\n".$line;
}



function htmlize($s) {return str_replace("\n", '<br>', $s);}


function getRow($strm) {
	global  $line, $lineNum; 
	while(getLine($strm)) {
		if(strpos($line, '<tr>') !== FALSE || strpos($line, '</table') !== FALSE) break;  // skip shim
	}
	if(strpos($line, '</table') !== FALSE) return -1;
	$row = array();
	while(strpos($line, '</tr>') === FALSE)
		$row[] = nextTDStripped($strm);
//echo "[[".print_r($row, 1).']]<p>';		
	return $row;
}

function getLine($strm) {
	global $line, $col, $lineNum;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim($line);
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

function nextTDStripped($strm) {
	global $line, $lineNum;
	$td = '';
	do {
		if(!getLine($strm)) return FALSE;
		if($td) $spacer = "\n"; else $spacer = '';
		$td .= $spacer.$line;
	} while (strpos($line, '</td>') === FALSE && strpos($line, '</tr>') === FALSE);
	return trim(strip_tags($td));
}

function getValue($line, $after=null) {
	//$pattern = '/value=".*"/gi';
	
	if($after) {
		$linestart = strpos($line, $after)+strlen($after);
		$lineend = strpos($line, '>', $linestart);
		$line = substr($line, $linestart, $lineend-$linestart);
		//echo "<p style='color:red;'>[$linestart] [$lineend] [$line]</p>";
	}
	
	$start = strpos($line, 'value="');
	if($start === FALSE) return;
	$start += strlen('value="');
	$end = strpos($line, '"', $start);
	return substr($line, $start, $end-$start);
}
	
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}

