<?
// key-safe-fns.php

// custom field preferences are stored in tblpreference
//format: label|active|adminOnly

require_once "preference-fns.php";
require_once "gui-fns.php";


function getKeySafeFields($activeOnly=false) {
	global $maxKeySafes;
	$maxKeySafes = $_SESSION['preferences']['maxKeySafes'] ? $_SESSION['preferences']['maxKeySafes'] : 5;
	for($i=1; $i<= $maxKeySafes; $i++) $keySafeFieldNames[$i] = "safe$i";

	$fields = array();
	
	if(isset($_SESSION['preferences'])) foreach($_SESSION['preferences'] as $key => $val)
		if(in_array($key, $keySafeFieldNames)) {
			$field = explode('|', $val);
			
			if(!$activeOnly || $field[1])
				$fields[$key] = $field;
		}
	return $fields;
}

function getAdminOnlyKeySafes() { // returns key=>label
	$adminOnlyKeySafes = array();
	foreach(getKeySafeFields() as $key => $safe) 
		if(isSafeAdminOnly($safe)) $adminOnlyKeySafes[$key] = $safe[0];
	return $adminOnlyKeySafes;
}


function isSafeAdminOnly($safe) {
	// safe is array with label}|active|adminOnly
	return $safe[2];
}

function shouldEnforceAdminOnly() {
	// Return TRUE if Admin Only should be enforced
	// All owners and dispatchers can see Admin Only Key Safes
	// If allowed (keyAdminsCanSeeAdminOnlySafes) sitters with 'ka' rights can see Admin Only Key Safes
	require_once "key-fns.php";
	if(in_array(userRole(), array('o', 'd'))) return false;
	else if(!$_SESSION['preferences']['keyAdminsCanSeeAdminOnlySafes']) return true;
	else return userRole() != 'p' || keyManagementRight() != 'ka';
}

function getKeySafes($constrainedByRole=false) {
	$arr = array();
	foreach(getKeySafeFields(true) as $key => $descr) {
		if($constrainedByRole 
				&& $descr[2] == 1 
				&& shouldEnforceAdminOnly())
			continue;
		$arr[$key] = $descr[0];
	}
	return $arr;
}


function saveKeySafes() {
	global $maxKeySafes;
	getKeySafeFields();	
	for($i=1; $i<=$maxKeySafes; $i++) {
		$val = $_POST["safe$i"]
						.'|'.(isset($_POST["active_$i"]) && $_POST["active_$i"] ? 1 : 0)
						.'|'.(isset($_POST["adminOnly_$i"]) && $_POST["adminOnly_$i"] ? 1 : 0)
						;
		setPreference("safe$i", $val);
	}
}

function keySafeEditor() {
	global $maxKeySafes;
	
	$fields = getKeySafeFields();
	echo "<table width='400' class='sortableListCell'>";
	
	if($_SESSION['preferences']['enableAdminOnlyKeySafes']) $adminOnlyLabel = "<th>ADMIN Only</th>";
	echo "<tr><th>&nbsp;</th><th>Active</th><th>Label</th>$adminOnlyLabel</tr>";
	for($i=1; $i<=$maxKeySafes; $i++) {
		echo "<tr><td>Key Safe #".($i)."</td><td>";
		labeledCheckbox('',"active_$i", $fields["safe$i"][1]);
		echo "</td><td>";
		labeledInput('', "safe$i", $fields["safe$i"][0]);
		if($_SESSION['preferences']['enableAdminOnlyKeySafes']) {
			echo "</td><td>";
			labeledCheckbox('',"adminOnly_$i", $fields["safe$i"][2]);
		}
		echo "</td></tr>";
	}
	echo "</table>";
}
