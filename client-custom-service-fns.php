<? // client-custom-service-fns.php
/*
tblclientpreference
clientptr
property - customservice_001, customservice_002, ...
value - <cservice active="1" servicecode="99">label</cservice>

tblpreference
property - customservicelist_001
value - listid|label

tblcustomservicelist - EXCLUSIVE contents of a list
listid
order
servicecode
label
*/

function getCustomServiceLists() {
	for($i=1; $listdesc = $_SESSION['preferences']['customservicelist_'.$i]; $i++) {
		$listdesc = explode('|', $listdesc);
		$lists[$listdesc[1]] = $listdesc[0];
	}
	return $lists;
}

function getCustomServiceList($listid) {
	return fetchAssociations("SELECT * FROM tblcustomservicelist WHERE listid = $listid ORDER BY order");
}
			
function getClientCustomServiceList($client) {
	return fetchAssociations(  // tblclientpreference???
			"SELECT * 
				FROM tblclientpreference 
				WHERE clientptr = $client 
					AND property LIKE 'customservice_%'
				ORDER BY property");
}
			
