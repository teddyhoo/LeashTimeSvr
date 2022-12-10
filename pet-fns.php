<?
// pet-fns.php

require_once "js-gui-fns.php";
require_once "common/db_fns.php";
require_once "preference-fns.php";

$maxBytes = 5000000;
$maxPixels = 11000000; // 6800000;
$maxDim = (int)sqrt($maxPixels);
$displaySize = array(300, 300);

$numExtraPets = 5;
if(mattOnlyTEST()) $numExtraPets = 15;
//petFields type,dob,notes,description,active,name,breed,sex,color,fixed, (photo,ownerptr,petid,vetptr)

function getPetFields() {
	global $petFields;
	if(!$petFields) {
		$petFields = array();
		$raw = explode(',', 'name,Pet Name,type,Pet Type,sex,Sex,breed,Breed,color,Color,fixed,Fixed,dob,'.
												'Birthday,description,Description,active,Active,notes,Notes');
		for($i=0;$i < count($raw) - 1; $i+=2) $petFields[$raw[$i]] = $raw[$i+1];
	}
	return $petFields;
}
$petFields = getPetFields(); // I don't know why this does not work without the assignment

$allTblpetFieldNames = array_merge(array_keys($petFields), array('photo','ownerptr','petid','vetptr'));

foreach(explode('|', $_SESSION['preferences']['petTypes']) as $type) $petTypes[$type] = $type;
$petTypes = array_merge(array('--'=>'', '--Edit Pet Types--'=>-1), $petTypes);

function getClientPets($id, $cols='*') {
	if(!$id) return array();
	return fetchAssociations("SELECT $cols FROM tblpet WHERE ownerptr = $id ORDER BY name");
}

function getPet($id) {
	if(!$id) return array();
	return fetchFirstAssoc("SELECT * FROM tblpet WHERE petid = $id LIMIT 1");
}

function getClientPetNames($id, $inactiveAlso=false, $englishList=false) {
	$arr = getPetNamesForClients(array($id), $inactiveAlso, $englishList);
	return $arr[$id];
}

function getAppointmentPetNames($id, $petnames=null, $englishList=false) {
	if(!$petnames || $petnames == 'All Pets') {
		$details = fetchFirstAssoc("SELECT pets, clientptr FROM tblappointment WHERE appointmentid = $id LIMIT 1");
		$petnames = $details['pets'];
	}
	if($petnames == 'All Pets') 
		$petnames = getClientPetNames($details['clientptr'], $inactiveAlso=false, $englishList);
	return $petnames;
}
	
	

function petNamesCommaList($names, $maxLength=40) {
	if(!$names) return '';
	$list = array();
	foreach($names as $nm) {
		if($str && strlen($str)+strlen($nm)+2 > $maxLength-3) return $str.'...';
		$str = $str ? join(', ', array($str, $nm)) : $nm;
	}
	return $str;
}


function getPetNamesForClients($ids, $inactiveAlso=false, $englishList=false) {
	if(!$ids) return array();
	$ids = join(', ', $ids);
	$filter = $inactiveAlso ? '' : 'active = 1 AND ';
  if(!($result = doQuery("SELECT ownerptr, name FROM tblpet WHERE $filter ownerptr IN ($ids) ORDER BY ownerptr, name"))) return null;
  $pets = array();
  while($row = mysql_fetch_array($result, MYSQL_ASSOC))
   $pets[$row['ownerptr']][] = $row['name'];
	
	foreach($pets as $owner => $names) {
	  if(!$englishList || count($names) == 1) $pets[$owner] = join(', ', $names);
	  else if(count($names) > 1) {
			$lastName = array_pop($names);
			$pets[$owner] = join(', ', $names)." and $lastName";
		}
	}
	return $pets;
}

function getActiveClientPets($id, $cols='*') {
	if(!$id) return array();
	return fetchAssociations("SELECT $cols FROM tblpet WHERE ownerptr = $id AND active=1 ORDER BY name");
}

function getActiveClientPetsTip($id, $petNames='All Pets', $pets=null) {
	if(!$id) return '';
	if(!$petNames) return '';
	if(!$pets) $pets = getActiveClientPets($id);
	$tips = array();
	if($petNames != 'All Pets')
		$petNames = array_map('trim', explode(',', $petNames));
	foreach($pets as $pet)
		if($petNames == 'All Pets' || in_array($pet['name'], $petNames)) 
			$tips[] = "{$pet['name']}: ".($pet['type'] ? $pet['type'] : '?');
	return join(', ', $tips);
}

function petTable($pets, $client, $allowExtraPets=true, $finalSectionMessage=null) {
	global $numExtraPets, $inactivePets, $totalNumPets, $clientPetContext;
	// for segmented profile requests $clientPetContext = null, pet{petid}, pet_new
	if($clientPetContext) {
		$finalSectionMessage = '.';
		$pets = array();
		$parts = explode('_', $clientPetContext);
		if($parts[1] == 'new') {
			$numExtraPets=1;
		}
		else {
			$pets = fetchAssociations("SELECT * FROM tblpet WHERE petid = {$parts[1]} LIMIT 1");
			$allowExtraPets = false;
			$numExtraPets=0;
		}
	}
	$totalNumPets = count((array)$pets);
	$inactivePets = 0;
	foreach($pets as $i => $pet) {
		if(!$pet['active']) $inactivePets++;
		else $lastActivePetIndex = $i+1;
	}
	// *******************
	if(FALSE && mattOnlyTEST()) {
		//echo "<hr>".print_r($pets,1)."<hr>";
		echo "**** INACTIVE: [$inactivePets]";

	}
	// *******************
	echo "<table width=100%>\n";  // Pets table: 1 column
	$visibleSections = max(1, count($pets));
	hiddenElement("pets_visible", $visibleSections);
	$checked = ($client ? $client['emergencycarepermission'] : $_SESSION['preferences']['emergencycarepermission']) ? 'CHECKED' : '';

	if(dbTEST('poochydoos'))
		hiddenElement('emergencycarepermission', 1);
	else 
		echo "<tr><td colspan=2><input type='checkbox' $inputClass id='emergencycarepermission' name='emergencycarepermission' $checked>".
			'Client authorizes emergency medical care for all pets as deemed necessary by a veterinarian and agrees to pay fully for such care</td></tr>';

	if($inactivePets && !$clientPetContext) {
		echo "<tr><td colspan=2>".fauxLink("<span id='showinactivepets' style='display:inline'>Show the $inactivePets inactive or deceased pets</span>", 'showInactive(1)', 1);
		echo fauxLink("<span id='hideinactivepets' style='display:none'>Hide the $inactivePets inactive or deceased pets</span>", 'showInactive(0)', 1)."</td></tr>";
	}
	$allowedAdditionalPets = $allowExtraPets ? $numExtraPets : 0;
//if(mattOnlyTEST()) {echo "pets: ".count($pets)."<p>allowedAdditionalPets: $allowedAdditionalPets";}
	// add a section for each pet + five extra sections
	for($i=1; $i <= count($pets)+$allowedAdditionalPets; $i++) {
		$pet = $i <= count($pets) ? $pets[$i-1] : array('active'=>1, 'fixed'=>1);
    $addAnother = $i < count($pets) ? '' : ($i < count($pets)+$allowedAdditionalPets ? 'button' : 'final');
    if($allowExtraPets && $i == $lastActivePetIndex) $addAnother = 'button';
    $inactivePet = $pet && !$pet['active'];
		addPetSection($i, $pet, $client, ($inactivePet || $i > $visibleSections), $addAnother, $finalSectionMessage);
	}
	echo "</table>\n";
}

function addPetSection($number, $pet, $clientId, $hidden=false, $addAnother=false, $finalSectionMessage=null) {
	global $petFields, $petTypes, $allowAutoCompleteOnce, $inactivePets, $totalNumPets, $clientPetContext;

	$initialDisplay = $hidden ? 'none' : $_SESSION['tableRowDisplayMode'];
	$inactivePetClass = $pet['active'] ? "class='petsection'" : "class='petsection inactivePet'";
	echo "<tr id='row_pet_$number' $inactivePetClass style='display:$initialDisplay' name='petsection'><td>\n";
	hiddenElement("petid_$number", $pet['petid']);
	
	$horizontalRule = $number == 1 ? '' : "<hr>\n";
	echo "$horizontalRule<table width=95% border=0 bordercolor=red><tr>\n";  // Pet table: 2 rows, 2 cols 

	echo "<td style='vertical-align:top;'><table border=0 bordercolor=blue>\n"; // R1C1 Pet details
	if(!$pet['active']) echo "<tr><td style='font-style:italic;font-weight:bold;'>Inactive</td></tr>\n";
	if($_SERVER['REMOTE_ADDR'] == '68.225.89.173')  echo "<tr><td style='font-style:italic;font-weight:bold;'>{$pet['petid']}</td></tr>\n";
	$jqueryVersion = $_SESSION["responsiveClient"];  // probably does not belog here...
	foreach($petFields as $field => $label) {
		$field_N = $field."_$number";
    $val = $pet[$field];
		if($field == 'type')
		  selectRow($label.':', $field_N, $val, $petTypes, 'petTypeChanged(this)');
		//else if($field == 'dob') calendarRow($label.':', $field_N, $val);
		else if($field == 'dob')  calendarRow($label.':', $field_N, $val, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $timeAlso=null, $jqueryVersion);

		else if($field == 'active') {
			if(!$pet['petid']) $val = 1;
//function checkboxRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null) {
			if(mattOnlyTEST()) $extraContent = "<span class='tiplooks'> (pet is still living at home)</span>";
			checkboxRow($label.':', $field_N, $val, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent);
    }
		else if($field == 'fixed') checkboxRow($label.':', $field_N, $val);
		else if($field == 'sex') 
			radioButtonRow($label.':', $field_N, $val, array('Male'=>'m', 'Female'=>'f'), '');
		
		else if($field == 'notes') textRow($label, $field_N, $val, $rows=6, $cols=50);

		else {
			if(in_array($field, array('breed', 'color')))
				$allowAutoCompleteOnce = true;
			$extraContent = '';
			if($field == 'breed' && in_array(userRole(), array('o', 'd')))
				$extraContent = fauxLink('Choose', "chooseBreed(\"$field_N\", \"type_$number\")", 'noecho', 'Choose a breed from a list.');
			else if($field == 'name' and !$val) $extraContent = "<span class='tiplooks'> * required</span>";
			inputRow($label.':', $field_N, $val, null, null, null, null, null, $extraContent);
			
		}
			
	}
	
	// ***** KLUDGE *****
	global $responsiveMobile;
	if($responsiveMobile) {
		echo "<tr>\n"; // End R1C1
		echo "<td colspan=2 style='vertical-align:middle;'>\n"; // R1C2 Pet photo
		petPhotoSection($pet, $number, $clientId);
		echo "</td></tr>\n</table></td>\n"; // End R1C2 and R1
	}
	else {
		echo "</table></td>\n"; // End R1C1

		echo "<td style='vertical-align:middle;'>\n"; // R1C2 Pet photo
		petPhotoSection($pet, $number, $clientId);
		echo "</td></tr>\n"; // End R1C2 and R1
	}
	// ***** END KLUDGE *****
	
	
	if($_SESSION['custom_pet_fields_enabled']) {
		echo "<tr><td colspan=2>\n";
		customPetFields($pet, $number, (userRole() == 'p'), (userRole() == 'c'));
		echo "</td></tr>\n"; 
	}
	
	$number++;
	echo "<tr>";
	if($addAnother == 'button') {
		echo "<td>";
		$nextBlankRow = $number;
		if($pet['petid']) $nextBlankRow = $totalNumPets+1;
		echoButton(null, 'Add another pet', 
		   "document.getElementById(\"row_pet_$nextBlankRow\").style.display=(navigator.userAgent.toLowerCase().indexOf(\"msie\") != -1 ? \"block\" : \"table-row\");".
		   "this.style.display=\"none\";document.getElementById(\"pets_visible\").value=$nextBlankRow;");
	}
	else if($addAnother == 'final') {
		$finalSectionMessage = $finalSectionMessage ? $finalSectionMessage : 'To add more pets, please Save Changes first and reopen this editor.';
		if($addAnother == 'final') echo "<td class='tiplooks'>$finalSectionMessage";
	}
	echo "</td></tr>";

	echo "</table>\n</td></tr>\n"; 
}

function customPetFields($pet, $number, $visitSheetOnly=false, $clientVisibleOnly=false) {
	require_once "custom-field-fns.php";
	if(!$names = getPetCustomFieldNames()) return;
	$petFields = getCustomFields($activeOnly=true, $visitSheetOnly, $names, $clientVisibleOnly);
	$petFields = displayOrderCustomFields($petFields, 'petcustom');
//print_r($petFields);	
	$names = array_keys($petFields);
	
	$chunks = array_chunk($names, max((count($names) / 2)+(count($names) % 2 ? 1 : 0), 1), true);
	// ***** KLUDGE *****
	global $responsiveMobile;
	if($responsiveMobile) $chunks = array($names);
	$chunkCount = count($chunks);
	// ***** END KLUDGE *****
	$trdisp = $_SESSION['tableRowDisplayMode'];
	$CPFIELD = "cpfield$number";
	$show = "$(\".$CPFIELD\").each(function(i,el){el.parentNode.parentNode.style.display=\"$trdisp\";});";
	$show .= "$(\"#showcpflds$number\").hide();$(\"#hidecpflds$number\").show();";
	$show .= "$(\"#showallcpfields$number\").hide();$(\"#hideallcpfields$number\").show();";
	$hide = "$(\".$CPFIELD\").each(function(i,el){if(!el.value && !el.innerHTML){el.parentNode.parentNode.style.display=\"none\";}});";
	$hide .= "$(\"#showcpflds$number\").show();$(\"#hidecpflds$number\").hide();";
	$hide .= "$(\"#showallcpfields$number\").show();$(\"#hideallcpfields$number\").hide();";
	
	$showall = "$(\".anycpfield\").each(function(i,el){el.parentNode.parentNode.style.display=\"$trdisp\";});";
	$showall .= "$(\".anyshowfield\").hide();$(\".anyhidefield\").show();";
	$hideall = "$(\".anycpfield\").each(function(i,el){if(!el.value && !el.innerHTML){el.parentNode.parentNode.style.display=\"none\";}});";
	$hideall .= "$(\".anyshowfield\").show();$(\".anyhidefield\").hide();";
	
	$hideEmpties = in_array(userRole(), array('o', 'd'));
	fauxLink('Show All Custom Pet Fields', $show, null, null, "showcpflds$number", 'anyshowfield fauxlink fontSize1_2em', (!$hideEmpties ? 'display:none;' : ''));
	if(mattOnlyTEST() || strpos($_SERVER["HTTP_USER_AGENT"], 'Firefox') === false) {  // DOES NOT WORK after 7 pets or so in firefox
		echo " ";
		fauxLink('(...for all pets)', $showall, null, null, "showallcpfields$number", 'anyshowfield fauxlink fontSize1_2em', (!$hideEmpties ? 'display:none;' : ''));
	}
	fauxLink('Hide Empty Custom Pet Fields', $hide, null, null, "hidecpflds$number", 'anyhidefield fauxlink fontSize1_2em', ($hideEmpties ? 'display:none;' : ''));
	if(mattOnlyTEST() || strpos($_SERVER["HTTP_USER_AGENT"], 'Firefox') === false) {  // DOES NOT WORK after 7 pets or so in firefox
		echo " ";
	fauxLink('(...for all pets)', $hideall, null, null, "hideallcpfields$number", 'anyhidefield fauxlink fontSize1_2em', ($hideEmpties ? 'display:none;' : ''));
	}
	echo "<table width='90%' border=0 bordercolor=blue><tr>\n";
	$petValues = getPetCustomFields($pet['petid'], 'raw');
	$petValues['clientid'] = $pet['ownerptr'];
	$prefix = "pet$number".'_';
	foreach($chunks as $chunk) {
		$customFields = array();
		foreach($chunk as $name) $customFields[$name] = $petFields[$name];
		echo "<td style='vertical-align:top'>";
		customFieldsTable($petValues, $customFields, $prefix, $groupClass="anycpfield $CPFIELD overallColumns_$chunkCount", $hideEmpties);
		echo "</td>";
	}
	echo "</table>\n"; 
	
}

function petPhotoSection($pet, $number, $clientId) {
	global $maxBytes, $maxDim, $maxPixels;
	//hiddenElement("photo_$number", $pet['photo']); // 300 X 225
	//return;
  if($clientId) {
		if($pet['photo'])
			echo "<img src='pet-photo.php?id={$pet['petid']}&version=display' border=1 bordercolor=black>";
		else if($_SESSION["auth_login_id"] == 'matt' || TRUE)
			echo "<img src='art/nopetphoto.jpg' onmouseout='this.src=\"art/nopetphoto.jpg\"' onmouseover='this.src=\"art/nopetphoto-rollover.jpg\"' border=1 bordercolor=black>";
		else
			echo "<img src='art/nopetphoto.jpg' border=1 bordercolor=black>";
		$title = "title= 'Upload a new photo for this pet, replacing the old photo (if any).'";
		echo "<br><b>Set photo</b> <input $title type='file' id='photo_$number' name='photo_$number' autocomplete='off' onchange='photoAction(this)'>";
		$title = "title= 'Drop the photo (if any) for this pet and do not upload a new photo.'";
		echo "<br>... or <input type='checkbox' id='dropphoto_$number' name='dropphoto_$number' onclick='photoAction(this)' $title ><label for='dropphoto_$number' $title > <b>Drop photo</b> (remove photo - if any - for this pet)</label>";
		$z = number_format($maxDim);
		echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB.<br>Max. image size: ".sprintf('%01.2f', $maxPixels / (1024 * 1024.0))." megapixels (approx. $z X $z)";
if(mattOnlyTEST()) fauxLink($clockwise = '<br>&#8635;', "openConsoleWindow(\"rotator\", \"pet-photo-rotator.php?id={$pet['petid']}\", 620, 620);");

	}
	else {
		echo "<center>You must Save this client before you can upload photos for this pet<p>";
		echoButton('', 'Save and Continue', 'checkAndSubmit("pets")');
  }
}

$counterclockwise = '&#8634;';
$clockwise = '&#8635;';

function rotatePhoto($file, $clockwise) {
	// load original image
	if(!$file || !file_exists($file)) return;
	$extension = strtoupper(substr($file, strrpos($file, '.')+1));
	
	$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
	ini_set("gd.jpeg_ignore_warning", 1);
  if($extension == 'JPG' || $extension == 'JPEG') $image = imagecreatefromjpeg($file);
  else if($extension == 'PNG') $image = imagecreatefrompng($file);
	ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
	// rotate image
	$exifRotationMap = array(3=>180, 6=>-90, 8=>90);
	$rotation = $clockwise ? -90 : 90;
	
	$image = imagerotate($image, $rotation, 0);
	// save image
	if(file_exists($file)) unlink($file);
	if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($image, $file);
	else if($extension == 'PNG') imagepng($image, $file);
	return true;
}

function rotatePetPhoto($petid, $clockwise) {
	$file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$petid.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$petid.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$petid.png";
	// load original image
	if(!file_exists($file)) {
		require_once "remote-file-storage-fns.php";
		if(remoteCacheAvailable()) {
			getPetPhotoFileCacheParameters();
			if($cache = fetchFirstAssoc(
				"SELECT * FROM tblfilecache 
					WHERE existsremotely AND localpath IN 
						('{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png')
					LIMIT 1")) {
				getCachedFileAndUpdateExpiration($cache);
				$file = $cache['localpath'];
			}
		}
	}
	if(!$file || !file_exists($file)) return;
	rotatePhoto($file, $clockwise);
	// create display version
	makeDisplayImage($file);
	return true;
}


function petAge($petOrDate) {
	$bday = is_array($petOrDate) ? $petOrDate['dob'] : $petOrDate;
	if(!$bday) return '';
	$bday = date('Y-m-d', strtotime($bday));	
	$delta = strtotime(date('Y-m-d')) - strtotime($bday);
	$days = $delta/(24 * 60 * 60);
	if($days > 365) {
    list($Y,$m,$d)    = explode("-",$bday);
    return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y ).' years';
	}
	return floor($days / 7).' weeks.';
}

function saveClientPets($clientId) {	
	$pets_visible = $_POST["pets_visible"];
	$errors = array();
	// save every visible pet with a petid or a name
	for($i=1;$i<=$pets_visible;$i++) {
		if($_POST["petid_$i"] || $_POST["name_$i"])
		  if($r = saveClientPet($i, $clientId))
		    $errors[] = $r;
	}
//print_r($errors);exit;	
	if($errors) setOptionalAlert(join('\n', $errors)) ;
}

function saveClientPetChanges($index, $changes, $clientId, $petId=null) {
	if($changes['dropphoto']) $changes['photo'] = null;
	else if($changes['photo']) {
		$uploadedPhoto = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$changes['photo']}"."_".($index+1).".jpg";
		if(!file_exists($uploadedPhoto)) $uploadedPhoto = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$changes['photo']}"."_".($index+1).".jpeg";
		if(!file_exists($uploadedPhoto)) $uploadedPhoto = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$changes['photo']}"."_".($index+1).".png";
		$extension = strtolower(substr($uploadedPhoto, strrpos($uploadedPhoto, '.')+1));
//if(mattOnlyTEST()) {echo "[$uploadedPhoto] [$extension]";exit;}		
	}
	if(!$petId && $changes['name']) {
		//		create new pet as necessary
		$changes['ownerptr'] = $clientId;
		unset($changes['dropphoto']);
		$newPet = saveablePet($changes);
		$newPet['active'] = 1; // do not allow new pets to be marked inactive
		$petId = insertTable('tblpet', $newPet, 1);
		if($changes['photo']) {
			// 		copy pet photos to proper place and replace field with final path
			$finalPath = "{$_SESSION['bizfiledirectory']}photos/pets/$petId.$extension";
			updateTable('tblpet', array('photo' => $finalPath), "petid=$petId", 1);
			foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/$petId.*") as $oldFile)
				unlink($oldFile);
			if(!file_exists($uploadedPhoto)) $photoUploadError = "Uploaded pet photo not found [".basename($uploadedPhoto)."]";
			else {
				ensureDirectory(dirname($finalPath));
				foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/$petId.*") as $oldFile)
					unlink($oldFile);
				rename($uploadedPhoto, $finalPath);
				makeDisplayImage($finalPath);
				outboardFile($finalPath);
			}
		}
		
		if($petId) savePetCustomFields($petId, $changes, $index+1, $pairsOnly=true);  
		//exit;  // WTF?!
	}
	else if($petId) {
		//		modify existing pet
		if($changes['dropphoto']) {
			dropPhoto("{$_SESSION['bizfiledirectory']}photos/pets/$petId.jpg");
			unset($changes['dropphoto']);
		}
		else if($changes['photo']) {
			// 		copy pet photos to proper place and replace field with final path
			$finalPath = "{$_SESSION['bizfiledirectory']}photos/pets/$petId.$extension";
			//if(file_exists($finalPath)) unlink($finalPath);
			foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/$petId.*") as $oldFile)
				unlink($oldFile);			
			if(!file_exists($uploadedPhoto)) $photoUploadError = "Uploaded pet photo not found [".basename($uploadedPhoto)."]";
			else {
				ensureDirectory(dirname($finalPath));
				rename($uploadedPhoto, $finalPath);
				makeDisplayImage($finalPath);
				outboardFile($finalPath);
				$changes['photo'] = $finalPath; // 
			}
		}
		//savePetChanges($existingPets[$index]['petid'], $petFields[$index]);
		if(saveablePet($changes)) updateTable('tblpet', saveablePet($changes), "petid = $petId", 1);

		savePetCustomFields($petId, $changes, $index+1, $pairsOnly=true);  
	}	
	return $photoUploadError;
}

function outboardFile($f) {
	require_once "remote-file-storage-fns.php";
	if(!remoteCacheAvailable()) return;
	getPetPhotoFileCacheParameters();
	$cacheid = cacheFile($f, $f, $overwrite=true);
	if($cacheid) saveCachedFileRemotely($cacheid);
}

function saveablePet($pet) {
	global $allTblpetFieldNames;
	foreach($pet as $field => $v)
		if(!in_array($field, $allTblpetFieldNames))
			unset($pet[$field]);
	return $pet;
}

function saveClientPet($number, $clientId) {
	global $petFields;
	
	$petId = $_POST["petid_$number"];
  $pet = array('ownerptr'=>$clientId);
  $fieldNames = array_keys($petFields);
  unset($fieldNames['petid']);
  foreach($fieldNames as $field)
	  $pet[$field] = $_POST[$field."_$number"];
	if(!$pet['name']) $pet['name'] = 'unnamed';
	$pet['active'] = !$petId || $pet['active'] ? 1 : 0; // do not allow new pets to be marked inactive
	$pet['fixed'] = $pet['fixed'] ? 1 : 0;
	
//if(mattOnlyTEST()) {print_r($_FILES);exit;	}	
	$photo = $_FILES["photo_$number"] && $_FILES["photo_$number"]['error'] != 4;
	$extension = strtolower(substr($_FILES["photo_$number"]['name'], strrpos($_FILES["photo_$number"]['name'], '.')+1));
	
	$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/$petId.$extension";

	if(isset($_POST["dropphoto_$number"])) $pet['photo'] = null;
	else if($photo) $pet['photo'] = $photoName;
	
  if(!$petId) {
		$petId = insertTable('tblpet', $pet, 1);
		if($photo) {
			$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/$petId.$extension";
	  	updateTable('tblpet', array('photo' => $photoName), "petid=$petId", 1);
		}
	}
	else {
		$wasActive = fetchRow0Col0("SELECT active FROM tblpet WHERE petid = $petId LIMIT 1", 1);
		if($wasActive != $pet['active']) {
			$miracleOrTragedy = $pet['active'] ? 'Reactivated' : 'Deactivated';
			logChange($petId, 'tblpet', 'm', $miracleOrTragedy);
		}

	  updateTable('tblpet', saveablePet($pet), "petid=$petId", 1);
	}
	
  require_once "custom-field-fns.php";
	savePetCustomFields($petId, $_POST, $number);  
	
	if(isset($_POST["dropphoto_$number"]) && $_POST["dropphoto_$number"]) {
		foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/$petId.*") as $oldPhotoName)
			dropPhoto($oldPhotoName);
	}
	else if($photo) {
//if(mattOnlyTEST()) {echo "uploadPhoto('photo_$number', $photoName)";exit;	}	
		return uploadPhoto("photo_$number", $photoName);
		// return errormsg if necessary
	}
	return null;
}



function uploadClientPhotoNumber($number, $fileNameBase) {
	// ... to temporary storage
	if(isset($_POST["dropphoto_$number"])) return;
	$photo = $_FILES["photo_$number"] && $_FILES["photo_$number"]['error'] != 4;
	if($photo) {
		$extension = strtolower(substr($_FILES["photo_$number"]['name'], strrpos($_FILES["photo_$number"]['name'], '.')+1));
		$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/$fileNameBase"."_$number.$extension";
		return uploadPhoto("photo_$number", $photoName, false);
	}
}

function dropPhoto($photoName) {
	if(file_exists($photoName)) unlink($photoName);
	$displayVer = str_replace("\\", '/', dirname($photoName));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/'.basename($photoName);
	if(file_exists($displayVer)) unlink($displayVer);
	require_once "remote-file-storage-fns.php";
	if(remoteCacheAvailable()) deleteFileFromCache($photoName);
}

function getAllowedImageTypes() { return array('JPG','JPEG','PNG'); }
function getAllowedTypesDescr() { return "JPEG (.jpg or .jpeg) or PNG image"; }
function uploadPhoto($formFieldName, $destFileName, $makeDisplayVersion=true) {
  $allowedTypes = getAllowedImageTypes();
  $allowedTypesDescr = getAllowedTypesDescr();
	
	$dot = strrpos($_FILES[$formFieldName]['name'], '.');
	if($dot === FALSE) return "Uploaded file MUST be a $allowedTypesDescr.";
	$originalName = $_FILES[$formFieldName]['name'];
  $extension = strtoupper(substr($_FILES[$formFieldName]['name'], $dot+1));
  if(!in_array($extension, $allowedTypes))
    return "Photo Not uploaded!  Uploaded file MUST be a $allowedTypesDescr.<br>[$originalName] does not qualify.";

	$target_path = $destFileName;
//if(mattOnlyTEST() && $failure) {echo $target_path;exit;}  

	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path), 0775); // x is necessary for group
//echo substr(sprintf('%o', fileperms(dirname($target_path))), -4);
	if(!move_uploaded_file($_FILES[$formFieldName]['tmp_name'], $target_path)) {
		return "There was an error uploading the file, please try again!";
	}
	if($makeDisplayVersion)
		makeDisplayImage($target_path);
	outboardFile($target_path);

	return null;
}

function makeDisplayImage($file) {
	global $displaySize;
	$displayVer = str_replace("\\", '/', dirname($file));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/';
	ensureDirectory($displayVer);
	$petId = substr(basename($file), 0, strpos(basename($file), '.'));
	foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/display/$petId.*") as $oldFile)
		unlink($oldFile);
	
	$displayVer .= basename($file);
	$dims = getimagesize($file);
	if($dims[0] <= $displaySize[0] && $dims[1] <= $displaySize[1])
	  copy($file, $displayVer);
	else {
		makeResizedVersion($file, $displayVer, $displaySize, $dims);
	}
}

function photoDimsToFitInside($file, $maxDims) {
	if(file_exists($file)) list($width, $height) = getimagesize($file);
	else list($width, $height) = array(0,0);
  return dimensionsScaledToFitInside($width, $height, $maxDims);
  /*$maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  return array(round($width * $percent), round($height * $percent));*/
}

function dimensionsScaledToFitInside($width, $height, $maxDims) {
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  if($width + $height == 0) return array(0, 0);
  $percent = $maxDim / max($width, $height);
  return array(round($width * $percent), round($height * $percent));
}


function makeResizedVersion($f, $outName, $maxDims, $origDims=null) {
	ini_set('memory_limit', '512M');
	//ini_set('upload_max_filesize', '6M');
  list($width, $height) = $origDims ? $origDims : getimagesize($f);
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  $newwidth = round($width * $percent);
  $newheight = round($height * $percent);
  // Load
  $resized = imagecreatetruecolor($newwidth, $newheight);
	$extension = strtoupper(substr($f, strrpos($f, '.')+1));

	if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
		$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
		ini_set("gd.jpeg_ignore_warning", 1);
	}

  if($extension == 'JPG' || $extension == 'JPEG') $source = imagecreatefromjpeg($f);
  else if($extension == 'PNG') $source = imagecreatefrompng($f);
	if("IGNORE JPG WARNING") {
		ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
	}

  // Resize
  imagecopyresized($resized, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
  
  if(TRUE || mattOnlyTEST()) {
		$exif = exif_read_data($f);
		if(!empty($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 8:
						$rotated = imagerotate($resized,90,0);
						break;
				case 3:
						$rotated = imagerotate($resized,180,0);
						break;
				case 6:
						$rotated = imagerotate($resized,-90,0);
						break;
			}
			if($rotated) $resized = $rotated;
		}  
  }  
  
	if(file_exists($outName)) unlink($outName);
	if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($resized, $outName);
	else if($extension == 'PNG') imagepng($resized, $outName);
}

function dumpPetThumbnails($pets) {
	$colsPerRow = 5;
	$boxSize = array(110, 110);
	echo "<style>.photoTable {text-align:center;} .photoTable td {border: solid black 1px;} </style>";
	echo "<table class='photoTable'>";
	$col = 0;
	$onClick = '';
	for($i = 0; $i < count($pets); $i++) {
		if(!$pets[$i]['photo']) continue;
		require_once "remote-file-storage-fns.php";
		//if(remoteCacheAvailable()) ensureFileExistsLocally($pets[$i]['photo']);
		if(FALSE && !file_exists($pets[$i]['photo'])) {
			$photo = 'art/photo-unavailable.jpg';
			$src = $photo;
		}
		else {
			$photo = $pets[$i]['photo'];
			$dirName = dirname($photo);
			$basename = basename($photo);
			$displayphoto = "$dirName/display/$basename";
			$src = "pet-photo.php?id={$pets[$i]['petid']}&version=display";
			// need to "localize" photo o get image size later...
			$version = 'fullsize';
			ensureFileExistsLocally($photo);
			if(!file_exists($photo)) $version = 'display'; // fallout from the reote photo expiration mistake
			$onClick = "openConsoleWindow(\"petview\", \"pet-photo.php?id={$pets[$i]['petid']}&version=$version\", 320, 380)";
			$onClick = "onClick = '$onClick'";
			
			// now show the display version instead
			$photo = $displayphoto;
		}
		
		if($col == $colsPerRow) $col = 0;
		if($col == 0) echo ($i > 0 ? "</tr>" : "")."<tr>";
		$col++;
		$dims = photoDimsToFitInside($photo, $boxSize);
		$src = globalURL($src);
		
		echo "<td><img src='$src' width={$dims[0]} height={$dims[1]} $onClick><br>{$pets[$i]['name']}</td>";
	}
	echo "</tr></table>";
}


function ensureDirectory($dir, $rights=0765) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, $rights);
}

function invalidUpload($formFieldName, $file) {
  global $maxPixels, $maxDim;
  $basefile = basename($file);
  $oldError = error_reporting(E_ALL - E_WARNING);
  $failure = null;
  
  
  if($failure = $_FILES[$formFieldName]['error']) {
		if($failure == 1) $failure = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
		else if($failure == 2) $failure = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
		else if($failure == 3) $failure = "The uploaded file was only partially uploaded.";
		else if($failure == 4) $failure = "No file was uploaded.";
		else if($failure == 6) $failure = "Missing a temporary folder.";
		else if($failure == 7) $failure = "Failed to write file to disk.";
		else if($failure == 8) $failure = "File upload stopped by extension.";
	}
  else if(true/*$extension == 'JPG' */) {
		$extension = strtoupper(substr($_FILES[$formFieldName]['name'], strrpos($_FILES[$formFieldName]['name'], '.')+1));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "NO PROBLEM: {$_FILES[$formFieldName]['tmp_name']}";echo "<br>NO PROBLEM: ".print_r(getimagesize($_FILES[$formFieldName]['tmp_name']),1); }		
		$size = getimagesize($_FILES[$formFieldName]['tmp_name']);
		$pixels = $size[0]*$size[1];
		if($pixels > $maxPixels) {
			$pixels = number_format($pixels);
			
		  $failure = "Photo dimensions are too big: ({$size[0]} X {$size[1]}) = $pixels pixels (Max: $maxPixels pixels, = approx. $maxDim X $maxDim)";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<p>FAILURE: $failure"; exit; }		
		}
    else {
			//$allowedTypes = getAllowedImageTypes();
			$allowedTypesDescr = getAllowedTypesDescr();
			
			if("IGNORE JPG WARNING") {  // for "recoverable error: Premature end of JPEG file"
				$jpeg_ignore_warning = ini_set("gd.jpeg_ignore_warning");
				ini_set("gd.jpeg_ignore_warning", 1);
			}
			if($extension == 'JPG' || $extension == 'JPEG')
      	$img = imagecreatefromjpeg($_FILES[$formFieldName]['tmp_name']);
      else if($extension == 'PNG')
      	$img = imagecreatefrompng($_FILES[$formFieldName]['tmp_name']);
			if("IGNORE JPG WARNING") {
				ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
			}
      if(!$img) $failure = "it does not contain a valid $allowedTypesDescr.";
		}
  }
  else {
		require_once "zip-fns.php";
		$zipFile = $_FILES['$formFieldName']['tmp_name'];
    if(is_int($zip = zip_open($zipFile))) $failure = "File is not a valid ZIP archive.";
    $dir = getTargetPath();
    $existingPhotos = glob("$dir/*.jpg");
    foreach($existingPhotos as $index => $fname) $existingPhotos[$index] = basename($fname);
    $errors = invalidArchiveEntries($zip, $existingPhotos);
    if($errors)
			$failure = join("<br>\n", $errors);
		else {
			$newPhotos = array();
      $zip = zip_open($zipFile);
			$errors = unpackArchivePhotos($zip, $dir, $newPhotos, $existingPhotos, $maxPixels);
			foreach($newPhotos as $photo) registerPhoto($photo);
			echo join("<br>\n", $errors);
		}
  }
  error_reporting($oldError);
//if(mattOnlyTEST() && $failure) {echo $failure;exit;}  
  return $failure;
}


function dumpPetJS() {
$debug = $_SERVER['REMOTE_ADDR'] == '68.225.89.173' ? "1" : "";
	$iosSafari = (isIPhone() || isIPod() || isIPad()) && !strpos($_SERVER["HTTP_USER_AGENT"], 'Chrome') ? '1' : '0';
	echo <<<FUNC
function showInactive(reveal) {
	$('.inactivePet').each(function (index, el) {el.style.display= reveal ? '{$_SESSION['tableRowDisplayMode']}' : 'none';});
	$('#hideinactivepets').each(function (index, el) {el.style.display= reveal ? '{$_SESSION['tableRowDisplayMode']}' : 'none';});
	$('#showinactivepets').each(function (index, el) {el.style.display= !reveal ? '{$_SESSION['tableRowDisplayMode']}' : 'none';});
}
function photoAction(el) {
	var section = el.id.split('_')[1];
	if(el.type=='checkbox' && el.checked) { 
		var uploader = document.getElementById('photo_'+section);
		if(navigator.appName == 'Microsoft Internet Explorer') {
			var clone = uploader.cloneNode(false);
			clone.onchange = uploader.onchange;
			uploader.parentNode.replaceChild(clone,uploader);
		}
		else uploader.value = '';
	}
	else if(el.type=='file' && el.value) {
		document.getElementById('dropphoto_'+section).checked=false;
		if($iosSafari) setTimeout(function() {alert(message);}, 0); 
		else alert("Photo will be added when all changes are saved.");
	}
}

function petNameProblem(name) {
	if(name.indexOf(',') > -1
		 || name.indexOf('"') > -1
		 || name.indexOf('<') > -1
		 || name.indexOf('{') > -1
		 || name.indexOf('}') > -1)
		 return "Pet names may not contain commas, double quotes, angle brackets, or curly brackets.";
}
	
function petTypeChanged(sel) {
	if(sel.options[sel.selectedIndex].value == -1) {
		sel.selectedIndex = 0;
		editProp('petTypes|Pet+Types|list', sel.id);
	}
}
	
function updateProperty(widgetName, value) {
	var petTypes = unescape(value).split('|');
	//alert(value);
	var sels = document.getElementsByTagName("select");
	for(var i=0;i<sels.length;i++) {
		if(sels[i].id.indexOf('type_') == 0) updatePetTypesSelect(sels[i], petTypes);
	}

}

function updatePetTypesSelect(sel, petTypes) {
	var choice = sel.options[sel.selectedIndex].value;
	selectedIndex = 0;
	sel.options.length=0;
  sel.options[sel.options.length]= new Option('--', '', null, null);
	sel.options[sel.options.length]= new Option('--Edit Pet Types--', -1, null, null);
  for(var i=0; i < petTypes.length; i++) {
		var optionDescr = petTypes[i];
		//var checked = optionDescr[1] != ''; 
		if(petTypes[i] == choice) selectedIndex = i+2;
	  sel.options[sel.options.length]= new Option(petTypes[i], petTypes[i], null, null);
	}
	sel.selectedIndex = selectedIndex;
}
FUNC;
}

function echoPetPhotosDiv($clientid) {
	foreach(getClientPets($clientid, 'photo,active,petid') as $pet)
		if($pet && $pet['active'] && $pet['photo']) {
			/*if(mattOnlyTEST()) */
			$pet['photo'] = "pet-photo.php?id={$pet['petid']}&version=display";
			$pets[] = $pet;
		}
	if(!$pets) return;
	echo '<div id="petphotos" class="pics">';
	foreach($pets as $pet) echo "<img class=\"photo\" src=\"{$pet['photo']}\" width=160 height=120 />";
	echo "</div>";
}

function deletePets($ids) {
	$ids = is_array($ids) ? join(',', $ids) : $ids;
	doQuery("DELETE FROM tblpet WHERE petid IN ($ids)", 1);
	if($ids) doQuery("DELETE FROM relpetcustomfield WHERE petptr IN ($ids)", 1);
}
