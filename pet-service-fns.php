<? // pet-service-fns.php

function setServicePetTypes($servicecode, $pettypes) {
	$pettypes = $pettypes ? json_encode($pettypes) : -1;
	replaceTable('tblpreference', array('property'=>"petservice_$servicecode", 'value'=>$pettypes), 1);
}

function getServicePetTypes($servicecode) {
	$types = fetchRow0Col0("SELECT value FROM tblpreference WHERE property='petservice_$servicecode' LIMIT 1", 1);
	if($types && $types != -1) $types = json_decode($types);
	else $types = array();
	return $types;
}

function getAllServicePetTypes() {
	$pairs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'petservice_%'", 1);
	foreach($pairs as $servicecode => $types)
		$allTypes[substr($servicecode, strlen('petservice_'))] = $types && $types != -1 ? json_decode($types) : null;
	return (array)$allTypes;
}

function getAllPetsTypesServices($allTypesArray=null) {
	if(!$allTypesArray) $allTypesArray = getAllServicePetTypes();
	foreach($allTypesArray as $servicecode => $types)
		foreach($types as $type)
			$allPetsTypes[$type][$servicecode] = 1;
	foreach((array)$allPetsTypes as $type => $codes)
		$allPetsTypes[$type] = array_keys($codes);
	return (array)$allPetsTypes;
}

function activeServiceTypesOnly($servicecodes) {
	static $activeservicecodes;
	$activeservicecodes = $activeservicecodes ? $activeservicecodes : fetchCol0("SELECT servicetypeid FROM tblservicetype WHERE active = 1", 1);
	return array_intersect((array)$servicecodes, $activeservicecodes);
}

function setPetServiceTypes($pettype, $servicecodes, $allPetsTypes=null) {
	$allTypesArray = getAllServicePetTypes();
	if(!$allPetsTypes) $allPetsTypes = getAllPetsTypesServices($allTypesArray);
	$oldservicecodes = (array)($allPetsTypes[$pettype]);
	$newservicecodes = activeServiceTypesOnly($servicecodes);
	foreach($newservicecodes as $newcode) {
		if(!in_array($newcode, $oldservicecodes)) {
			$pettypes = (array)($allTypesArray[$newcode]);
			$pettypes[] = $pettype;
//return "$pettype: old ".print_r($oldservicecodes, 1)." new".print_r($newservicecodes, 1)." pettypes=>".print_r($pettypes, 1).'<br>';
			setServicePetTypes($newcode, $pettypes);
//return $pettype.': '.print_r($newcode, 1).' pettypes: '.print_r($pettypes, 1);
		}
	}
	foreach($oldservicecodes as $oldcode) {
		if(!in_array($oldcode, $newservicecodes))
			setServicePetTypes($oldcode, array_diff((array)($allTypesArray[$oldcode]), array($pettype)));
	}
}
	
	
	
	
