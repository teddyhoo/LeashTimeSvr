<?
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

$clientptr = 103;
$petname = 'Crizzle';

print_r(serviceTypesTailoredByPets($clientptr));

function serviceTypesTailoredByPets($clientptr, $petNames=null) {
	if(!$clientptr) return null;
	if(is_string($petNames)) $petNames = array_map('leashtime_real_escape_string', explode(', ', $petNames));
	if($petNames) $petNameFilter = "AND name IN ('".join("','", $petNames)."')";
	$pettypes = fetchCol0(
		"SELECT DISTINCT type 
			FROM tblpet 
			WHERE ownerptr = '$clientptr' AND type IS NOT NULL AND TRIM(`type`) != '' $petNameFilter", 1);
	if(!$pettypes) return null;
	require_once "pet-services.php";
	$servicesByPettype = getAllPetsTypesServices();
	$allServiceCodes = array();
	foreach($pettypes as $pettype) { // If ANY pet has no defined services, then filtering is pointless.
		if(!$servicesByPettype[$pettype])
			return null;
		else foreach($servicesByPettype[$pettype] as $code) $allServiceCodes[$code] = 1;
	}
	return array_keys($allServiceCodes);
}

exit;