<? // mobile-prov-fns.php

function visitListClientLabel($appt) {
	// Use only in the context of a $_SESSION
	static $allPets, $clients;
	$clientptr = $appt['clientptr'];
	
	
	if(!dbTEST('agisdogs,tonkatest,dogslife')) {
		if($appt['pets'] == 'All Pets') {
			if(!in_array($clientptr, array_keys((array)$allPets))) {
				require_once "pet-fns.php";
				foreach(getPetNamesForClients(array($clientptr), $inactiveAlso=false) as $id => $val)
					$allPets[$id][$clientptr] = $val;
			}
			$clientPets = join(',', (array)$allPets[$clientptr]);
		}
		$rowPets = trim($appt['pets']);
		$displayPets = $rowPets == 'All Pets' ? $clientPets : ($rowPets ? $rowPets : "no pets");
		$noPetsToDisplay = $displayPets == '' || $displayPets == "no pets";
		$displayPets = "<span class='petfont'>$displayPets</span>";
		return "{$appt['name']} ($displayPets)";
	}
	
	
	if(!$clients[$clientptr]) $clients[$clientptr] = 
		fetchFirstAssoc("SELECT lname, CONCAT_WS(' ', fname, lname) as clientname 
											FROM tblclient 
											WHERE clientid = $clientptr
											LIMIT 1",1);
//require_once "preference-fns.php"; $_SESSION['preferences']['molbileSitterVisitListClientFormat'] = fetchPreference('molbileSitterVisitListClientFormat');
	$displayMode = $_SESSION['preferences']['molbileSitterVisitListClientFormat'];
	$displayMode = $displayMode ? $displayMode : 'fullname/pets';
	if(strpos($displayMode, 'pets') !== FALSE) {
		if($appt['pets'] == 'All Pets') {
			if(!in_array($clientptr, array_keys((array)$allPets))) {
				require_once "pet-fns.php";
				foreach(getPetNamesForClients(array($clientptr), $inactiveAlso=false) as $id => $val)
					$allPets[$id][$clientptr] = $val;
			}
			$clientPets = join(',', (array)$allPets[$clientptr]);
		}
		$rowPets = trim($appt['pets']);
		$displayPets = $rowPets == 'All Pets' ? $clientPets : ($rowPets ? $rowPets : "no pets");
		$noPetsToDisplay = $displayPets == '' || $displayPets == "no pets";
		$displayPets = "<span class='petfont'>$displayPets</span>";
	}
	if(!$displayMode) $displayMode = 'fullname/pets';
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($clients);exit;; }	


	/*$options = array('Client name (Pets)'=>'fullname/pets', 
										'Client name'=>'fullname', 
										'Last name (Pets)'=>'name/pets', 
										'Pets (Last name)'=>'pets/name', 
										'Pets Only'=>'justpets');*/ 

	$label = $displayMode == 'fullname' ? $clients[$clientptr]['clientname'] : (
					 $displayMode == 'name/pets' ? "{$clients[$clientptr]['lname']} ($displayPets)" : (
					 $displayMode == 'justpets' ? ($noPetsToDisplay ? "({$clients[$clientptr]['clientname']})" : $displayPets) : (
					 $displayMode == 'pets/name' ? "$displayPets ({$clients[$clientptr]['lname']}) " : (
					 $displayMode == 'fullname/pets' ? "{$clients[$clientptr]['clientname']} ($displayPets)" :  
					'???'))));
	return $label;
}
