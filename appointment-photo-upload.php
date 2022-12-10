<? // appointment-photo-upload.php
require_once "common/init_session.php";
require_once "remote-file-storage-fns.php";
require_once "preference-fns.php";

// NOTE -- all responses are JSON encoded

extract(extractVars('loginid,password,appointmentid,delete', $_REQUEST));
if($loginid && $password) {
	require_once "native-sitter-api.php";
	if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
		echo jsonPair('error', $userOrFailure);
		ditch();
	}
	$user = $userOrFailure;
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	logScriptCallForThisDB();
}
else {
	require_once "common/init_db_petbiz.php";
	locked('p-');
}

if($_POST['test']) {
	echo "FILES: ".print_r($_FILES, 1).'\n\n<p>'.print_r($_POST, 1);
	exit;
}


$appointmentid = $_POST['appointmentid'];
$delete = $_REQUEST['delete']; // allow GET. $_POST['delete'];
$photoParameterName = "image";

if($delete) {
	// https://leashtime.com/appointment-photo-upload.php?loginid=bball1&password=pass&delete=	
	$appointmentid = $delete;
	$filecacheid = getAppointmentProperty($appointmentid, 'visitphotocacheid');
	if($filecacheid) $cache = getCachedFileEntry($filecacheid);
	setAppointmentProperty($appointmentid, 'visitphotocacheid', null);
	setAppointmentProperty($appointmentid, 'deletedvisitphotocacheid', null);
	if(!$cache) {
				echo jsonPair('error', "No photo for visit $appointmentid FOUND");
		ditch();
	}
	if(file_exists($cache['localpath'])) unlink($cache['localpath']);
	updateTable('tblfilecache', array('existslocally'=>0), "filecacheid = '{$cache['filecacheid']}'", 1);
	// leave remote version in place for now, for the possibility of UNDO until expiration time
	deleteTable('tblappointmentprop', "appointmentptr = '$delete'", 1);
	setAppointmentProperty($appointmentid, 'deletedvisitphotocacheid', $cache['filecacheid']);
	setAppointmentProperty($appointmentid, 'visitphotodeleted', date('Y-m-d H:i:s'));
	echo "DELETED PHOTO for visit $appointmentid";
	ditch();
}
else if($appointmentid) {
	$clientid = fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = '$appointmentid' LIMIT 1", 1);
	if(!$clientid) $error = "ERROR: Visit [$appointmentid] NOT FOUND";
	if(!$error) {
		$maxBytes = 5000000;
		$maxPixels = 11000000; // 6800000;
		$maxDim = (int)sqrt($maxPixels);
		$displaySize = array(300, 300);

		$photo = $_FILES[$photoParameterName] && $_FILES[$photoParameterName]['error'] != 4;
		$extension = strtolower(substr($_FILES[$photoParameterName]['name'], strrpos($_FILES[$photoParameterName]['name'], '.')+1));
		$photoName = "{$_SESSION['bizfiledirectory']}photos/appts/$clientid/$appointmentid.$extension";
		$error = uploadPhoto($photoParameterName, $photoName, $makeDisplayVersion=false);
	}
	if($error) {
		echo jsonPair('error', $error);;
		setAppointmentProperty($appointmentid, 'visitphotouploadfailed', date('Y-m-d H:i:s')." $error");
		ditch();
	}
	else {
		if(!$fileCacheParameters) getFileCacheParameters();
		//$fileCacheParameters['localCountLimit'] = 3;
		$cacheid = cacheFile($photoName, $photoName, 'overwrite');
		setAppointmentProperty($appointmentid, 'visitphotocacheid', $cacheid);
		setAppointmentProperty($appointmentid, 'visitphotoreceived', date('Y-m-d H:i:s'));
		echo jsonPair('ok', "UPLOADED $photoName [cacheid: $cacheid]");
		ditch();
	}
}
// ====================================
function ditch() {
	global $loginid, $password;
	if($loginid && $password) endRequestSession();
	exit;
}

function jsonPair($key, $val) {
	return json_encode(array($key=>$val));
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
  //imagecopyresized($resized, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
  
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


if($_POST) echo "FILES: ".print_r($_FILES, 1).'\n\n<p>POST: '.print_r($_POST, 1);
ditch();
exit;


if($_GET['geturl']) echo getAppointmentPhotoPublicURL($_GET['geturl']);
?>
<hr>
Local:<br>
<? foreach(fetchAssociations("SELECT * FROM tblfilecache") as $cache) {
		if($cache['existslocally'])
			echo "<img src='{$cache['localpath']}' width=30>	[{$cache['filecacheid']}] {$cache['localpath']}";//print_r($cache, 1).
		else echo "[{$cache['filecacheid']}] {$cache['localpath']}";
		echo " (remote: {$cache['existsremotely']}]<br>";
	}?>
<hr>
Visit photos:<br>
<? foreach(fetchCol0("SELECT appointmentptr FROM tblappointmentprop WHERE property = 'visitphotocacheid'") as $id) 
		echo "<a href='appointment-photo.php?id=$id'>$id</a> - "; ?>

<hr>
Actual files in bizfiles/biz_3/photos/appts/240/:<br>
<? foreach(glob("bizfiles/biz_3/photos/appts/240/*") as $f) 
		echo basename($f)."<br>"; 
	foreach(glob("bizfiles/biz_3/photos/appts/240/display/*") as $f) 
		echo basename($f)."<br>"; ?>
<hr>
<hr>
<form name='uploader' method='post' enctype='multipart/form-data'>
Visit <select name=appointmentid><option>5371<option>5372<option>5373<option>5374<option>5375
<option>5376<option>5377<option>5378<option>5379<option>5380</select>
<? echoButton('', 'View', "document.location.href=\"appointment-photo-upload.php?view=\"+document.getElementById(\"appointmentid\").value"); ?>
<br>
Photo <input  id='<?= $photoParameterName ?>' name='<?= $photoParameterName ?>' title= 'Upload a new photo for this pet, replacing the old photo (if any).' type='file' autocomplete='off'>
<input type=hidden name='loginid' value='<?= $loginid ?>'>
<input type=hidden name='password' value='<?= $password ?>'>
<input type=submit>
</form>