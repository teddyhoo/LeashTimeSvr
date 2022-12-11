<? // appointment-map-upload.php
require_once "common/init_session.php";
require_once "remote-file-storage-fns.php";
require_once "preference-fns.php";

// https://leashtime.com/appointment-map-upload.php?loginid=bball1&password=pass&appointmentid=

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
	echo "FILES: ".print_r($_FILES, 1).'\n\n<p>'.print_r($_POST, 1)."<br>REQUEST:<br>".print_r($_REQUEST, 1);
	exit;
}
//echo print_r($_GET, 1);

$delete = $_REQUEST['delete']; // allow GET. $_POST['delete'];
$imageParameterName = "image";

if($delete) {
	// https://leashtime.com/appointment-map-upload.php?loginid=bball1&password=pass&delete=	
	$appointmentid = $delete;
	$filecacheid = getAppointmentProperty($appointmentid, 'visitmapcacheid');
	if($filecacheid) $cache = getCachedFileEntry($filecacheid);
	setAppointmentProperty($appointmentid, 'visitmapcacheid', null);
	setAppointmentProperty($appointmentid, 'deletedvisitmapcacheid', null);
	if(!$cache) {
				echo jsonPair('error', "No map for visit $appointmentid FOUND");
		ditch();
	}
	if(file_exists($cache['localpath'])) unlink($cache['localpath']);
	updateTable('tblfilecache', array('existslocally'=>0), "filecacheid = '{$cache['filecacheid']}'", 1);
	// leave remote version in place for now, for the possibility of UNDO until expiration time
	deleteTable('tblappointmentprop', "appointmentptr = '$delete' AND property = 'visitmapcacheid'", 1);
	setAppointmentProperty($appointmentid, 'deletedvisitmapcacheid', $cache['filecacheid']);
	echo "DELETED PHOTO for visit $appointmentid";
	ditch();
}
else if($_POST && $appointmentid) {
	$clientid = fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = '$appointmentid' LIMIT 1", 1);
	if(!$clientid) $error = "ERROR: Visit [$appointmentid] NOT FOUND";
	if(!$error) {
		$maxBytes = 8 * 1024 * 1024; // 5000000;
		$maxPixels = 14000000; //11000000; // 6800000;
		$maxDim = (int)sqrt($maxPixels);
		$displaySize = array(300, 300);

		$photo = $_FILES[$imageParameterName] && $_FILES[$imageParameterName]['error'] != 4;
		$extension = strtolower(substr($_FILES[$imageParameterName]['name'], strrpos($_FILES[$imageParameterName]['name'], '.')+1));
		$mapName = "{$_SESSION['bizfiledirectory']}apptmaps/$clientid/$appointmentid.$extension";
		$error = uploadPhoto($imageParameterName, $mapName, $makeDisplayVersion=false);
	}
	if($error) {
		echo jsonPair('error', $error);;
		ditch();
	}
	else {
		if(!$fileCacheParameters) getFileCacheParameters();
		//$fileCacheParameters['localCountLimit'] = 3;
		$cacheid = cacheFile($mapName, $mapName, 'overwrite');
		setAppointmentProperty($appointmentid, 'visitmapcacheid', $cacheid);
		if(dbTEST('purrfectplaceforpets')) setAppointmentProperty($appointmentid, 'visitmapreceived', date('Y-m-d H:i:s'));
		echo jsonPair('ok', "UPLOADED $mapName [cacheid: $cacheid]");
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
	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path), 0775); // x is necessary for group
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
  return $failure;
}

if(!$_REQUEST['form']) {
	if($_POST) echo "FILES: ".print_r($_FILES, 1)."<p>POST: ".print_r($_POST, 1);
	ditch();
	exit;
}

if($_GET['geturl']) echo getAppointmentMapPublicURL($_GET['geturl']);
?>
<hr>
Local:<br>
<hr>
Visit photos:<br>
<? foreach(fetchCol0("SELECT appointmentptr FROM tblappointmentprop WHERE property = 'visitmapcacheid'") as $id) 
		echo "<a href='appointment-map.php?id=$id'>$id</a> - "; ?>

<hr>
Actual files in bizfiles/biz_3/photos/appts/240/:<br>
<? foreach(glob("bizfiles/biz_3/photos/appts/240/*") as $f) 
		echo basename($f)."<br>"; 
	foreach(glob("bizfiles/biz_3/photos/appts/240/display/*") as $f) 
		echo basename($f)."<br>"; ?>
<hr>
<hr>
<form name='uploader' method='post' enctype='multipart/form-data'>
Map <input type='file'  id='<?= $imageParameterName ?>' name='<?= $imageParameterName ?>' title= 'Upload a new map for this visit, replacing the old map (if any).' autocomplete='off'>
<input type=hidden name='loginid' value='<?= $loginid ?>'>
<input type=hidden name='password' value='<?= $password ?>'>
<input type=checkbox name='test'>
<input type=submit>
</form>