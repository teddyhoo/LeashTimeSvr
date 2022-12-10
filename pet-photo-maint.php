<? // pet-photo-maint.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";

$locked = locked('o-');


$versions = array(
	'fullsize' => '',
	'display' => 'display/',
	'client' => 'fromClient/');
foreach($versions as $v => $dir) $photos[$v] = photoVersionExists($_REQUEST['id'], $v);
print_r($photos);	
function photoVersionExists($id, $version) {
	global $versions;
	$version = !$version ? '' : $versions[$version];
	$file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png";
	if(file_exists($file)) return $file;
}

$id = $_REQUEST['id'];
if($_REQUEST['copyDisplayToFullSize']) {
	if(!$photos['display']) echo "<font color=red>Display version not found!</font><p>";
	$fullSize = str_replace($versions['display'], '', $photos['display']);
	copy($photos['display'],  $fullSize);
	updateTable('tblpet', array('photo'=>$fullSize), "petid=$id",1);
}

showVersions($id);

function showVersions($id) {
	global $versions, $photos;
	
	$pet = fetchFirstAssoc(
		"SELECT p.*, CONCAT_WS(' ', fname, lname) as client 
			FROM tblpet p 
			LEFT JOIN tblclient ON clientid = ownerptr 
			WHERE petid = $id");
	if(!$pet) echo "No pet with id [$id] found.";
	else {
		echo "Pet: [{$pet['name']}] {$pet['type']} ($id)<br>".print_r($pet,1).'<hr>';
		echo "Owner: [{$pet['client']}] ({$pet['ownerptr']})<hr>";
		foreach($versions as $v => $dir) {
			echo "$v:<p>";
			if($photos[$v])
				echo "<img src='https://leashtime.com/pet-photo.php?id=$id&version=$v'>";
			else {
				echo "$v version is missing.  ";
				if($v == 'fullsize' && $photos['display']) {
					echo "Copy display version to fullsize? <a href='pet-photo-maint.php?id=$id&copyDisplayToFullSize=1'>Yes</a>";
				}
			}
			echo "<hr>";
		}
	}
}
