<? // provider-profile-fns.php

function getStandardProviderProfileFieldNames() {
	return fetchCol0("SELECT value FROM tblpreference WHERE property LIKE 'provprofilefield%' ORDER BY property");
}

function getProviderProfileFields($provid) {
	$raw = fetchCol0(
		"SELECT value 
			FROM tblproviderpref 
			WHERE providerptr = $provid AND property LIKE 'provprofilefield%' ORDER BY property");
	foreach($raw as $pair) {
		$div = strpos($pair, '|');
		$pairs[substr($pair, 0, $div)] = substr($pair, $div+1);
	}
	return $pairs;
}

function getProfiledSitterOptions() {
	$sitters = fetchAssociations(
		"SELECT DISTINCT CONCAT_WS(', ', lname, fname) as sortname,
			CONCAT_WS(' ', fname, lname) as sittername, 
			nickname,	providerptr 
			FROM tblproviderpref
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE active = 1 AND property LIKE 'provprofilefield%' ORDER BY sortname");
	foreach($sitters as $sitter)
		$options[$sitter['sittername'].($sitter['nickname'] ? " ({$sitter['nickname']})" : '')] = $sitter['providerptr'];
	return $options;
}

function setProviderProfileFields($provid, $fields) {
	deleteTable('tblproviderpref', "providerptr=$provid AND property LIKE 'provprofilefield%'", 1);
	$n = 1;
	foreach($fields as $k=>$label) {
		if($label && strpos($k, 'provprofilefieldlabel') === 0) {
			$index = substr($k, strlen('provprofilefieldlabel'));
			if($fields["provprofilefield$index"])
				setProviderPreference($provid, 'provprofilefield'.$n++, "$label|{$fields["provprofilefield$index"]}");
		}
	}
	if($error = saveSitterPhoto($provid, $fields))
		$_SESSION['user_notice'] .= "<div class='noticeblock'>$error</div>" ;
}

function setStandardProviderProfileFieldNames($arr) {
	deleteTable('tblpreference', "property LIKE 'provprofilefield%'", 1);
	$n = 1;
	foreach($arr as $k=>$v)
		if(strpos($k, 'provprofilefield') === 0)
			setPreference('provprofilefield'.sprintf('%03d', $n++), $v);
}

function getStandardProviderProfileFields($provid) {
	foreach(getStandardProviderProfileFieldNames() as $i => $nm)
		$arr['provprofilefield'.sprintf('%03d', $i+1)] = $nm;
	return $arr;
}

function dumpSitterProfileValidationLines() {
	echo <<<LINES
	var els = document.providereditor.elements;
	var sitterProfileWarning = '';
	for(var i=1; i<els.length; i++) {
		if(els[i].id.substring(0, 'provprofilefield'.length) == 'provprofilefield' && els[i].innerHTML.trim().length > 0) {
			// check for a label
			var labelEl = document.getElementById('provprofilefieldlabel'+els[i].id.substring('provprofilefield'.length));
			if(labelEl.value.trim().length == 0) {
				sitterProfileWarning = 'Each entry in the Sitter Profile must have a label';
			}
		}
	}
	vArgs[vArgs.length] = sitterProfileWarning
	vArgs[vArgs.length] = '';
	vArgs[vArgs.length] = 'MESSAGE';
LINES;
}
	

function dumpProviderProfileTab($provider) {
	// Idea: somehow offer an SMS version distinct from an email version
	// SMS version: straight text is probably best UNLESS we go with 
	// the FANCY MMS option (construct an image with embedded text
	// Idea: if FANCY MMS option is offered, simple SMS text block should be offered
	$provid = $provider['providerid'];
	$standardFieldNames = getStandardProviderProfileFieldNames();
	$populatedFields = (array)getProviderProfileFields($provid);
	
	if(!$populatedFields && $standardFieldNames) $populatedFields = array_combine($standardFieldNames, array_fill(0, count($standardFieldNames), ''));
	echo "<h3>Sitter's Public Profile</h3>";
	echo "<table><tr><td valign='top'>";
	echo "<table>";
	$n = 1;
	foreach($populatedFields as $label => $val) {
		inputRow('Label', 'provprofilefieldlabel'.sprintf('%03d', $n), $label,  $labelClass=null, $inputClass='VeryLongInput');
		textRow('<img src="art/spacer.gif" width=1 height=1>', 'provprofilefield'.sprintf('%03d', $n), $val, $rows=3, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
		$n++;
	}
	$otherStandardFieldNames = array_diff($standardFieldNames, array_keys($populatedFields));
	foreach($otherStandardFieldNames as $label) {
		inputRow('Label', 'provprofilefieldlabel'.sprintf('%03d', $n), $label,  $labelClass=null, $inputClass='VeryLongInput');
		textRow('<img src="art/spacer.gif" width=1 height=1>', 'provprofilefield'.sprintf('%03d', $n), $emptyString, $rows=3, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
		$n++;
	}
	for($n; $n < (count($populatedFields)+6); $n++) {
		echo "<tr id='newppfrow$n'><td>";
		echoButton(null, "Add a field", "toggleField($n,0,1)", $class='', $downClass='', $noEcho=false, $title='Add another profile field.');
		echo "</td></tr>";
		inputRow('Label', 'provprofilefieldlabel'.sprintf('%03d', $n), '',  $labelClass=null, $inputClass='VeryLongInput', $rowId="provprofilefieldlabelrow$n");
		textRow('<img src="art/spacer.gif" width=1 height=1>', 'provprofilefield'.sprintf('%03d', $n), '', $rows=3, $cols=60, $labelClass=null, $inputClass=null, $rowId="provprofilefieldrow$n", $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
		$hideRows .= "toggleField($n, ".($hideRows ? 0 : 1).");\n";
	}
	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	echo "<tr><td colspan=2>Standard fields: ".join(', ', $standardFieldNames).'<br>';
	fauxLink('Edit Standard Profile fields', "if(confirm(\"About to leave this page and lose any unsaved changes.  Proceed?\")) document.location.href=\"provider-profile-standard-fields-editor.php\"", $noecho=false, "Go to the Standard Fields List");
	echo "</td></tr>";
	echo "</table>";
	echo "<td valign='top'><div style='background-color:lightgrey;text-align:center;'>";
	providerPhotoSection($provider);
	echo "</div>";
	echo "</td>";
	echo "</tr></table>";
	echo "<script language='javascript'>
function preview(format) {
	$.fn.colorbox({href:'sitter-profile-preview.php?id=$provid&format='+format, iframe:'true', width:'750', height:'470', scrolling: true, opacity: '0.3'});
}

function toggleFieldV1(n, exceptbutton, clicked)	{ // does not work unless profile tab is visible at render time
	$('#provprofilefieldlabelrow'+n).toggle();
	$('#provprofilefieldrow'+n).toggle();
	if(exceptbutton != 1) $('#newppfrow'+n).toggle();
	if(clicked == 1) $('#newppfrow'+(n+1)).toggle();
}

function toggleField(n, exceptbutton, clicked)	{
	if(clicked == 1) {
		$('#provprofilefieldlabelrow'+n).toggle();
		$('#provprofilefieldrow'+n).toggle();
		if(exceptbutton != 1) $('#newppfrow'+n).toggle();
		$('#newppfrow'+(n+1)).toggle();
	}
	else {
		$('#provprofilefieldlabelrow'+n).css('display', 'none');
		$('#provprofilefieldrow'+n).css('display', 'none');
		if(exceptbutton != 1) $('#newppfrow'+n).css('display', 'none');
		$('#newppfrow'+(n+1)).css('display', 'none');
	}
}
</script>";

	global $onLoadFragments;
	$onLoadFragments[] = $hideRows;
// image source: http://pixabay.com/en/sunset-dog-dogs-walking-woman-163479/
	//echo "<div style='height:30px'>&nbsp;</div>";

}

function providerPhotoSection($provider) {
	global $maxBytes, $maxDim, $maxPixels;
	$providerId = $provider['providerid'];
	$phototoken = fetchRow0Col0(
		"SELECT value 
			FROM tblproviderpref 
			WHERE providerptr = '$providerId' AND property = 'phototoken'
			LIMIT 1", 1);


  if($providerId) {
		$imgfile = getSitterDisplayFile($providerId);
		$showDims = photoDimsToFitInside($imgfile, array(249, 292));
		echo "<img width={$showDims[0]} height={$showDims[1]} src='sitter-photo.php?token=$phototoken&version=display' border=1 bordercolor=black>";
		$title = "title= 'Upload a new photo for this sitter, replacing the old photo (if any).'";
		echo "<br><b>Set photo</b> <input $title type='file' id='sitterphoto' name='sitterphoto' autocomplete='off' onchange='photoAction(this)'>";
		$title = "title= 'Drop the photo (if any) for this sitter and do not upload a new photo.'";
		echo "<br>... or <input type='checkbox' id='dropphoto' name='dropphoto' onclick='photoAction(this)' $title ><label for='dropphoto' $title > <b>Drop photo</b> (remove photo - if any - for this sitter)</label>";
		$z = number_format($maxDim);
		echo "<br>Maximum file size approx: ".number_format((int)($maxBytes / 1024))." KB.<br>Max. image size: ".sprintf('%01.2f', $maxPixels / (1024 * 1024.0))." megapixels (approx. $z X $z)";
		echo "<p>";
		echoButton('', 'Preview Emailed Profile', 'preview("email")');

	}
	else {
		echo "<center>You must Save this sitter before you can upload photos<p>";
		echoButton('', 'Save and Continue', 'checkAndSubmit("sitterprofile")');
  }
}

function sitterProfileEmailTemplateOptions() {
	return array(
		"Photo on Left"=>'photoleft',
		"Photo on Right"=>'photoright',
		"Bare Bones"=>'barebones',
		"Bare Bones, No Photo"=>'barebonesnophoto');
}

function chosenTemplate($sel=null) {
	$sel = $sel ? $sel :$_SESSION['preferences']['emailedSitterProfileTemplate'];
	return $sel == 'photoleft' ? sidePhotoTemplate('left') : (
				 $sel == 'photoright' ? sidePhotoTemplate('right') : (
				 $sel == 'barebones' ? bareBonesTemplate() : (
				 $sel == 'barebonesnophoto' ? bareBonesNoPhotoTemplate() 
				 : sidePhotoTemplate('left'))));
}

function bareBonesTemplate() {
	return <<<TEMPLATE
<img src='#PHOTOURL#' align=CENTER>
#FIELDSTART#
<p><b>#FIELDLABEL#</b><br>#FIELDCONTENT#
#FIELDEND#
TEMPLATE;
}

function bareBonesNoPhotoTemplate() {
	return <<<TEMPLATE
#FIELDSTART#
<p><b>#FIELDLABEL#</b><br>#FIELDCONTENT#
#FIELDEND#
TEMPLATE;
}

function sidePhotoTemplate($side) {
	$margins = $side == 'left' ? "margin:0 5px 0 0;" : "margin:0 0 0 5px;";
	return <<<TEMPLATE
<img src='#PHOTOURL#' style="float:$side;$margins">
#FIELDSTART#
<p><b>#FIELDLABEL#</b><br>#FIELDCONTENT#
#FIELDEND#
TEMPLATE;
}

function dumpEmailProfile($provid, $template=null) {
	$template = chosenTemplate($template);
	$fieldstart = strpos($template, '#FIELDSTART#');
	$fieldend = strpos($template, '#FIELDEND#');
	$fieldTemplate = substr($template, $fieldstart+strlen('#FIELDSTART#'), $fieldend-($fieldstart+strlen('#FIELDSTART#')));
	$before = substr($template, 0, $fieldstart);
	$after = substr($template, $fieldend+strlen('#FIELDEND#'));
	$populatedFields = getProviderProfileFields($provid);
	foreach((array)$populatedFields as $label => $val) {
		$htmlval = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $val));
		$out .= str_replace('#FIELDLABEL#', $label, str_replace('#FIELDCONTENT#', $htmlval, $fieldTemplate));
	}
	$phototoken = fetchRow0Col0(
			"SELECT value 
				FROM tblproviderpref 
				WHERE providerptr = '$provid' AND property = 'phototoken'
				LIMIT 1", 1);
	$url = globalURL("sitter-photo.php?token=$phototoken&version=display&bid={$_SESSION['bizptr']}");
	echo str_replace('#PHOTOURL#', $url, "$before$out$after");
}

//
// PHOTO STUFF
//
$maxBytes = 5000000;
$maxPixels = 11000000; // 6800000;
$maxDim = (int)sqrt($maxPixels);
$displaySize = array(300, 300);

function saveSitterPhoto($provId, $changes) {
	$photo = $_FILES["sitterphoto"] && $_FILES["sitterphoto"]['error'] != 4;
	$extension = strtolower(substr($_FILES["sitterphoto"]['name'], strrpos($_FILES["sitterphoto"]['name'], '.')+1));
	$photoName = "{$_SESSION['bizfiledirectory']}photos/sitters/$provId.$extension";

//print_r($changes);exit;
	if($changes['dropphoto']) {
		dropPhoto("{$_SESSION['bizfiledirectory']}photos/sitters/$provId.jpg");
		dropPhoto("{$_SESSION['bizfiledirectory']}photos/sitters/$provId.png");
		unset($changes['dropphoto']);
	}
	else if($photo)
		$photoUploadError = uploadPhoto("sitterphoto", $photoName, $makeDisplayVersion=true);
	// set up a token to this photo 
	if(!$photoUploadError && !getProviderPreference($provId, 'phototoken')) {
		$phototoken = randomPhotoToken();
		setProviderPreference($provId, 'phototoken', $phototoken);
	}
	
	return $photoUploadError;
}	

function randomPhotoToken() { // generate a random five-letter token based on a range of base26 numbers
  $reallyBadWordFrags = explode(',','alla,amci,anus,arse,ass,bast,biatc,bitc,blow,boio,bollo,boob,bone,buce,bull,butt,cabro,cawk,chri,chinc,chink,chine,choad,chode,coon,clit,cock,cooc,coot,cum,cunt,dago,damn,daygo,dego,deggo,dick,dike,dild,dook,douc,dumb,dum,dyke,ejac,faece,fag,fann,fat,feces,felch,feltch,fellat,flamer,fuck,fuk,gay,god,gook,gring,guido,hardo,hell,hoe,homo,hump,jap,jerk,jesu,jiga,jigg,jiz,kike,kyke,kunt,kooch,koot,lesb,lez,mick,minge,muff,nig,nip,negr,paki,peck,peni,piss,poon,poop,pric,prik,pud,punta,puss,puto,quee,quim,rimj,schlon,scrot,shit,shiz,skank,skeet,slut,smut,spic,sploo,suck,tard,teat,teet,testi,testy,teste,tit,twat,vag,vaj,wank,wetba,whor,wop,yed');
  //sort($unacceptableWords);
  //echo join(',',$unacceptableWords);
  $token = null;
  while(!$token) {
    $base26Num = base_convert(''.mt_rand(5000000, 10000000+5000000),10,26);
    $token = tokenize($base26Num);
    foreach($reallyBadWordFrags as $frag)
      if(strpos($token, $frag) !== false) {
         $token = null;
         break;
      }
  }
  return $token;
}

function dropPhoto($photoName) {
	if(file_exists($photoName)) unlink($photoName);
	$displayVer = str_replace("\\", '/', dirname($photoName));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/'.basename($photoName);
	if(file_exists($displayVer)) unlink($displayVer);
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
	
	//function outboardFile($f)
	require_once "remote-file-storage-fns.php";
	if(!remoteCacheAvailable()) return;
	getPetPhotoFileCacheParameters();
	$cacheid = cacheFile($target_path, $target_path, $overwrite=true);
	if($cacheid) saveCachedFileRemotely($cacheid);
	
	
	if($makeDisplayVersion)
		makeDisplayImage($target_path);
	return null;
}

function makeDisplayImage($file) {
	global $displaySize;
	$displayVer = str_replace("\\", '/', dirname($file));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/';
	ensureDirectory($displayVer);
	$displayVer .= basename($file);
	$dims = getimagesize($file);
	if($dims[0] <= $displaySize[0] && $dims[1] <= $displaySize[1])
	  copy($file, $displayVer);
	else {
		makeResizedVersion($file, $displayVer, $displaySize, $dims);
	}
	
}

function photoDimsToFitInside($file, $maxDims) {
  list($width, $height) = getimagesize($file);
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
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
  
	if(file_exists($outName)) unlink($outName);
	if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($resized, $outName);
	else if($extension == 'PNG') imagepng($resized, $outName);
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
function photoAction(el) {
	if(el.type=='checkbox' && el.checked) { 
		var uploader = document.getElementById('sitterphoto');
		if(navigator.appName == 'Microsoft Internet Explorer') {
			var clone = uploader.cloneNode(false);
			clone.onchange = uploader.onchange;
			uploader.parentNode.replaceChild(clone,uploader);
		}
		else uploader.value = '';
	}
	else if(el.type=='file' && el.value) {
		document.getElementById('dropphoto').checked=false;
		if($iosSafari) setTimeout(function() {alert(message);}, 0); 
		else alert("Photo will be added when all changes are saved.");
	}
}
FUNC;
}

function getSitterDisplayFile($id) {
	$file = "{$_SESSION['bizfiledirectory']}photos/sitters/display/$id.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/sitters/display/$id.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/sitters/display/$id.png";
	if(!file_exists($file)) $file = 'art/sitter-photo-unavailable.jpg';
	return $file;
}