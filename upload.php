<? // upload.php
// convenience for uploading files to LeashTime
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(TRUE) exit;

if(!mattOnlyTEST()) exit;

$locked = locked('z-');

$userSubdir = userRole().$_SESSION['auth_user_id'];
echo "$userSubdir<hr>";
$userSubdir = "miscupload/$userSubdir";

if($_POST) {
	$upload = $_FILES["upload"] && $_FILES["upload"]['error'] != 4;
	if($upload) {
		$destFileName = "$userSubdir/{$_FILES['upload']['name']}";
		$error = uploadPhoto("upload", $destFileName, false);
		if(!$error) $message = "$destFileName uploaded.";
	}
}
if($error) echo "<font color=red>$error</font>";
if($message) echo "<font color=green>$message</font>";
?>


<form method="post" enctype="multipart/form-data">
File <input type='file'  id='upload' name='upload' title= 'Upload a file, replacing the old file of the same name (if any).' autocomplete='off'>
<p><input type=submit name='Upload'>
</form>


<?
echo "<hr>";
foreach(glob($userSubdir."/*") as $f) {
	$basename = basename($f);
	$url = "$userSubdir/$basename";
	echo "<a href='$url'>$basename</a><br>";
}

function getAllowedTypes() { return array('JPG','JPEG','PNG'); }

function getAllowedTypesDescr() { return "JPEG (.jpg or .jpeg) or PNG image"; }

function uploadPhoto($formFieldName, $destFileName, $makeDisplayVersion=true) {
  /*
  
  $allowedTypes = getAllowedTypes();
  $allowedTypesDescr = getAllowedTypesDescr();
	$dot = strrpos($_FILES[$formFieldName]['name'], '.');
	if($dot === FALSE) return "Uploaded file MUST be a $allowedTypesDescr.";
	$originalName = $_FILES[$formFieldName]['name'];
  $extension = strtoupper(substr($_FILES[$formFieldName]['name'], $dot+1));
  if(!in_array($extension, $allowedTypes))
    return "Photo Not uploaded!  Uploaded file MUST be a $allowedTypesDescr.<br>[$originalName] does not qualify.";
	*/
	$target_path = $destFileName;
//if(mattOnlyTEST() && $failure) {echo $target_path;exit;}  

	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path), 0775); // x is necessary for group
//echo substr(sprintf('%o', fileperms(dirname($target_path))), -4);
	if(!move_uploaded_file($_FILES[$formFieldName]['tmp_name'], $target_path)) {
		return "There was an error uploading the file, please try again!";
	}
	/*if($makeDisplayVersion)
		makeDisplayImage($target_path);
	outboardFile($target_path);*/

	return null;
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
  else if(FALSE/*$extension == 'JPG' */) {
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
  else if(FALSE) {
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

function ensureDirectory($dir, $rights=0765) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, $rights);
}

