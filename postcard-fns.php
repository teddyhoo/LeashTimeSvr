<? // postcard-fns.php
function emailMessageForPostcard($cardid) {
	$card = fetchFirstAssoc("SELECT * FROM tblpostcard WHERE cardid = $cardid LIMIT 1", 1);
	if(!$card) {
		return "Postcard #$cardid not found.";
		exit;
	}
	//$date = longDate(strtotime($card['created']));
	$sitter = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$card['providerptr']} LIMIT 1", 1);
	$dearName = fetchRow0Col0("SELECT fname FROM tblclient WHERE clientid = {$card['clientptr']} LIMIT 1", 1);
	$root = $card['attachment'] 
		? substr(($root = basename($card['attachment'])), 0, strpos($root, '.')) 
		: 'greetings';
	$attachment = "postcard-photo-email.php?bid={$_SESSION["bizptr"]}&root=$root";
	return "Dear $dearName,<p>Your pet minder $sitter has sent you a postcard.<p>"
						.($card['note'] ? "The note says:"
															."<div style='display:block;border:solid #999999 1px;padding:7px;margin-left:10px;'>"
															.htmlText($card['note'])
															."</div>" 
							: "(No note was included.)<p>")
						.($card['attachment'] 
								? "<p>It includes a ".attachmentDescription($card['attachment'])."."
										."  Click <a href='".globalURL($attachment)."'>here</a> to view it.<br>"
										."<a href='".globalURL($attachment)."'><img border=0 src='".globalURL("$attachment&dims=550x550")."'></a>"
								: "<p>(No attachment was included.)<br><img border=0 src='".globalURL("$attachment&dims=550x550")."'>");
}

function htmlText($s) {
	return str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", (String)$s)));
}

function replyToCardWith($cardid, $replytext) {
	updateTable('tblpostcard', 
							array('reply'=>$replytext, 'replydate'=>date('Y-m-d H:i:s')), 
							"cardid = $cardid", 1);
							
	$card = fetchFirstAssoc("SELECT * FROM tblpostcard WHERE cardid = $cardid LIMIT 1", 1);
							
	$client = getOneClientsDetails($card['clientptr'], array('pets'));
	$sitter = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = {$card['providerptr']} LIMIT 1", 1);
	enqueueEmailNotification($sitter, "Re: A postcard from {$sitter['name']}", 
		replyEmailNote($client, $sitter, $card), null, $_SESSION["auth_login_id"], 
		'html');
							
}

function replyEmailNote($client, $sitter, $card) {
	$pets = $client['pets'] ? " (who owns: ".join(', ', $client['pets']).")" : '';
	return 
"Hi {$sitter['fname']},
<p>
{$client['clientname']}$pets has responded to your postcard of ".longDayAndDate(strtotime($card['created'])).':
<p>'
.htmlText($card['reply'])
.'<hr>'
.($card['note'] ? "Your postcard said:<p>".htmlText($card['note']) 
	: "(Your postcard had no note, just a ".attachmentDescription($card['attachment']).").");
}

function toggleSuppression($cardid) {
	$suppressed = fetchRow0Col0("SELECT suppressed FROM tblpostcard WHERE cardid = $cardid LIMIT 1", 1);
	updateTable('tblpostcard', 
								array('suppressed'=>($suppressed ? sqlVal('NULL') : ($suppressed = date('Y-m-d H:i:s')))),
								"cardid = $cardid", 1);
	return $suppressed;
}

function markClientViewed($card) {
	$updates = array('expiration'=>date('Y-m-d H:i:s', strtotime("+180 days")));
	if(!$card['viewed']) $updates['viewed'] = date('Y-m-d H:i:s');
	updateTable('tblpostcard', $updates, "cardid={$card['cardid']}", 1);
}

function greetingsImage() {
	return $_SESSION['preferences']['standardpostcardimage'] 
		? $_SESSION['preferences']['standardpostcardimage']
		: 'postcard-noimage.jpg';
}

function getExtension($name) {
	$dot = strrpos($name, '.');
	if($dot === FALSE) return null;
	return strtoupper(substr($name, $dot+1));
}

function attachmentType($name) {
	static $types;
	$types = $types ? $types : explodePairsLine('JPG|PHOTO||JPEG|PHOTO||PNG|PHOTO||MP4|VIDEO||MOV|VIDEO');
	return $types[getExtension($name)];
}

function attachmentDescription($name) {
	static $types;
	$types = $types ? $types : explodePairsLine('JPG|photo||JPEG|photo||PNG|photo||MP4|video (MP4)||MOV|video (QuickTime)');
	return $types[getExtension($name)];
}

function mimeType($name) {
	static $types;
	$types = $types ? $types : explodePairsLine('JPG|image/jpg||JPEG|image/jpg||PNG|image/png||MP4|video/mp4||MOV|video/quicktime');
	return $types[getExtension($name)];
}

function uploadAttachment($formFieldName, $target_path, $makeDisplayVersion=false, $allowedTypes=null, $allowedTypesDescr=null) {
	$originalName = $_FILES[$formFieldName]['name'];
  $extension = getExtension($originalName);
  $allowedTypes = $allowedTypes ? $allowedTypes : array('JPG','JPEG','PNG','MP4','MOV');
  if(!in_array($extension, $allowedTypes))
    return "Photo Not uploaded!  Uploaded file MUST be a $allowedTypesDescr.<br>[$originalName] does not qualify.";

	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path), 0775); // x is necessary for group
//echo substr(sprintf('%o', fileperms(dirname($target_path))), -4);
	if(!move_uploaded_file($_FILES[$formFieldName]['tmp_name'], $target_path)) {
		return "There was an error uploading the file, please try again!";
	}
	//if($makeDisplayVersion)
	//	makeDisplayImage($target_path);
	return null;
}

function dumpImage($file, $dims, $standard=true) {
	if($standard) $file = "art/$file";
	makeDisplayImage($file, $dims, true);
}

function dumpVideo($file) {
	$_SESSION['videofile'] = $file;	
	globalRedirect("viewmedia/movie.".getExtension($file));  // calls dumpVideoToStdOut
	exit;
}

function dumpVideoToStdOut($file) {
	
	$iminfo = array('mime'=>strtolower(mimeType($file)));
//echo "<h2>Content-type: ".$iminfo['mime']."</h2><h2>Content-Length: ".filesize($file)."</h2><h2>[{$_SERVER['HTTP_RANGE']}[</h2>";exit;		
//if($_SERVER['REMOTE_ADDR'] == '65.198.52.6') {print_r("Content-type: ".$iminfo['mime']."<p>Content-Disposition: inline; filename=".basename($file)."<p>Content-Length: ".filesize($file).'<p>'.print_r($_SERVER, 1)); exit;}	
	header("Content-type: ".$iminfo['mime']);
	if (isset($_SERVER['HTTP_RANGE']))  { // do it for any device that supports byte-ranges not only iPhone
		rangeDownload($file);
	}
	else {
		//header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		//header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		//header("Content-Disposition: inline; filename=".basename($file));
		header("Content-Length: ".filesize($file));

		$handle = fopen($file, "rb");
		while (!feof($handle)) {
			echo fread($handle, 8192);
		}
		fclose($handle);
	}
	exit;
}

function rangeDownload($file) {
 
	$fp = @fopen($file, 'rb');
 
	$size   = filesize($file); // File size
	$length = $size;           // Content length
	$start  = 0;               // Start byte
	$end    = $size - 1;       // End byte
	// Now that we've gotten so far without errors we send the accept range header
	/* At the moment we only support single ranges.
	 * Multiple ranges requires some more work to ensure it works correctly
	 * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	 *
	 * Multirange support annouces itself with:
	 * header('Accept-Ranges: bytes');
	 *
	 * Multirange content must be sent with multipart/byteranges mediatype,
	 * (mediatype = mimetype)
	 * as well as a boundry header to indicate the various chunks of data.
	 */
	header("Accept-Ranges: 0-$length");
	// header('Accept-Ranges: bytes');
	// multipart/byteranges
	// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	if (isset($_SERVER['HTTP_RANGE'])) {
 
		$c_start = $start;
		$c_end   = $end;
		// Extract the range string
		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		// Make sure the client hasn't sent us a multibyte range
		if (strpos($range, ',') !== false) {
 
			// (?) Shoud this be issued here, or should the first
			// range be used? Or should the header be ignored and
			// we output the whole content?
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		// If the range starts with an '-' we start from the beginning
		// If not, we forward the file pointer
		// And make sure to get the end byte if spesified
		if ($range0 == '-') {
 
			// The n-number of the last bytes is requested
			$c_start = $size - substr($range, 1);
		}
		else {
 
			$range  = explode('-', $range);
			$c_start = $range[0];
			$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		/* Check the range and make sure it's treated according to the specs.
		 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
		 */
		// End bytes can not be larger than $end.
		$c_end = ($c_end > $end) ? $end : $c_end;
		// Validate the requested range and return an error if it's not correct.
		if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
 
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		$start  = $c_start;
		$end    = $c_end;
		$length = $end - $start + 1; // Calculate new content length
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
	}
	// Notify the client the byte range we'll be outputting
	header("Content-Range: bytes $start-$end/$size");
	header("Content-Length: $length");
 
	// Start buffered download
	$buffer = 1024 * 8;
	while(!feof($fp) && ($p = ftell($fp)) <= $end) {
 
		if ($p + $buffer > $end) {
 
			// In case we're only outputtin a chunk, make sure we don't
			// read past the length
			$buffer = $end - $p + 1;
		}
		set_time_limit(0); // Reset time limit for big files
		echo fread($fp, $buffer);
		flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
	}
 
	fclose($fp);
 
}

/*function makeDisplayImage($file, $fitBox=null, $dumpToSTDOUT=false) {
	global $displaySize;
	$exifRotationMap = array(3=>180, 6=>-90, 8=>90);
	//$fitBox = $fitBox ? $fitBox : $displaySize;
	$iminfo = getimagesize($file);
//echo "dumpToSTDOUT: $dumpToSTDOUT<br>".print_r($iminfo, 1)."<br>".readImage($file, $iminfo);exit;	
	// DUMP 
	if($dumpToSTDOUT) {
		header("Content-type: ".$iminfo['mime']);
		if(!$fitBox || ($iminfo[0] <= $fitBox[0] && $iminfo[1] <= $fitBox[1])) {
			if($iminfo['mime'] == 'image/jpeg') {
				imageJPEG(readImage($file, $iminfo));
			}
			else if($iminfo['mime'] == 'image/png')
				imagePNG(readImage($file, $iminfo));
		}
		else 
			makeResizedVersion($file, null, $fitBox, $dims);
		exit;
	}
	// OR STORE
	$displayVer = str_replace("\\", '/', dirname($file));
	if(substr($displayVer, -1) == '/') $displayVer = substr($displayVer, 0, -1);
	$displayVer .= '/display/';
	ensureDirectory($displayVer);
	$displayVer .= basename($file);
	if($dims[0] <= $fitBox[0] && $dims[1] <= $fitBox[1])
	  copy($file, $displayVer);
	else {
		makeResizedVersion($file, $displayVer, $fitBox, $dims);
	}
	
}
*/

function readImage($file, $iminfo, $returnExifDataAndImage=false) {
	$exifRotationMap = array(3=>180, 6=>-90, 8=>90);
	if($iminfo['mime'] == 'image/jpeg') {
		if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
			$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
			ini_set("gd.jpeg_ignore_warning", 1);
		}		
		$image = imagecreatefromjpeg($file);
		if("IGNORE JPG WARNING") {
			ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
		}
		
		$exif_data = exif_read_data($file);
		$rotation = $exifRotationMap[$exif_data['Orientation']];
		if($rotation) $image = imagerotate($image, $rotation, 0);
	}
	else if($iminfo['mime'] == 'image/png')
		$image = imagecreatefrompng($file);
	return $returnExifDataAndImage ? array('exif'=>$exif_data, 'image'=>$image) : $image;
}

/*function makeResizedVersion($f, $outName, $maxDims, $origDims=null) {
	ini_set('memory_limit', '512M');
	//ini_set('upload_max_filesize', '6M');
	$iminfo = getimagesize($f);
  list($width, $height) = $iminfo;
  $source = readImage($f, $iminfo, $returnExifDataAndImage=1);
  if(in_array($source['exif']['Orientation'], array(6,8))) { // 90 degree rotation
		list($height, $width) = $iminfo;
	}
	$source = $source['image'];
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  $newwidth = round($width * $percent);
  $newheight = round($height * $percent);
  // Load
  $resized = imagecreatetruecolor($newwidth, $newheight);
  	
	

  // Resize
  imagecopyresized($resized, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
  
  if($outName) {
		if(file_exists($outName)) unlink($outName);
		if($iminfo['mime'] == 'image/jpeg')
			imagejpeg($resized, $outName);
		else if($iminfo['mime'] == 'image/png')
			imagepng($resized, $outName);
	}
	else {
		if($iminfo['mime'] == 'image/jpeg')
			return imagejpeg($resized);
		else if($iminfo['mime'] == 'image/png')
			return imagepng($resized);
		}
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
  $attachmentType = attachmentType($_FILES[$formFieldName]['name']);
  
  if($failure = $_FILES[$formFieldName]['error']) {
		if($failure == 1) $failure = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
		else if($failure == 2) $failure = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
		else if($failure == 3) $failure = "The uploaded file was only partially uploaded.";
		else if($failure == 4) $failure = "No file was uploaded.";
		else if($failure == 6) $failure = "Missing a temporary folder.";
		else if($failure == 7) $failure = "Failed to write file to disk.";
		else if($failure == 8) $failure = "File upload stopped by extension.";
	}
  else if($attachmentType == 'PHOTO') {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "NO PROBLEM: {$_FILES[$formFieldName]['tmp_name']}";echo "<br>NO PROBLEM: ".print_r(getimagesize($_FILES[$formFieldName]['tmp_name']),1); }		
		$iminfo = getimagesize($_FILES[$formFieldName]['tmp_name']);
		$pixels = $iminfo[0]*$iminfo[1];
		if($pixels > $maxPixels) {
			$pixels = number_format($pixels);
			
		  $failure = "Photo dimensions are too big: ({$iminfo[0]} X {$iminfo[1]}) = $pixels pixels (Max: $maxPixels pixels, = approx. $maxDim X $maxDim)";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<p>FAILURE: $failure"; exit; }		
		}
    else {
			switch ($iminfo['mime']) {
        case 'image/jpeg': 
					if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
						$jpeg_ignore_warning = ini_set("gd.jpeg_ignore_warning");
						ini_set("gd.jpeg_ignore_warning", 1);
					}		
        	$img = imagecreatefromjpeg($_FILES[$formFieldName]['tmp_name']);
					if("IGNORE JPG WARNING") {
						ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
					}
        	
        	break;
        case 'image/png': 
        	$img = imagecreatefrompng($_FILES[$formFieldName]['tmp_name']);
        	break;
		 	}
      if(!$img) $failure = "it does not contain a valid $attachmentType image.";
		}
  }
  else if($attachmentType == 'ZIP') {
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
*/







/*
function dumpPetThumbnails($pets) {
	$colsPerRow = 5;
	$boxSize = array(110, 110);
	echo "<style>.photoTable {text-align:center;} .photoTable td {border: solid black 1px;} </style>";
	echo "<table class='photoTable'>";
	$col = 0;
	$onClick = '';
	for($i = 0; $i < count($pets); $i++) {
		if(!$pets[$i]['photo']) continue;
		if(!file_exists($pets[$i]['photo'])) {
			$photo = 'art/photo-unavailable.jpg';
			$src = $photo;
		}
		else {
			$photo = $pets[$i]['photo'];
			$src = "pet-photo.php?id={$pets[$i]['petid']}";
			$onClick = "openConsoleWindow(\"petview\", \"pet-photo.php?id={$pets[$i]['petid']}&version=fullsize\", 320, 380)";
			$onClick = "onClick = '$onClick'";
		}
		
		if($col == $colsPerRow) $col = 0;
		if($col == 0) echo ($i > 0 ? "</tr>" : "")."<tr>";
		$col++;
		$dims = photoDimsToFitInside($photo, $boxSize);
		echo "<td><img src='$src' width={$dims[0]} height={$dims[1]} $onClick><br>{$pets[$i]['name']}</td>";
	}
	echo "</tr></table>";
}

function photoDimsToFitInside($file, $maxDims) {
  list($width, $height) = getimagesize($file);
  $maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
  $percent = $maxDim / max($width, $height);
  return array(round($width * $percent), round($height * $percent));
}


*/
