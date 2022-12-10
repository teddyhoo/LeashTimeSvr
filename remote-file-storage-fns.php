<? // remote-file-storage-fns.php
use Aws\S3\S3Client;

/******
See: getFileCacheParameters() below

			'localCountLimit' => 5,
			'defaultShelfLifeSeconds' => 30  days
			'defaultRemoteShelfLifeSeconds' => 90 days *** -1 = no remote expiration date
*******/





// ***************************************************************************************************************************
if($_GET['send']) print_r(saveAWS($_GET['send'], $_GET['to']));
else if($_GET['restore']) {
	$result = restoreAWS($_GET['restore'], $_GET['from']);
	echo "<img src='{$_GET['restore']}'><p>{$_GET['restore']} restored at ".date("F d Y H:i:s.", filectime($_GET['restore']));
	echo "<hr>".print_r($result, 1);
}
else if($_GET['listcache']) {
	foreach(glob("bizfiles/biz_{$_SESSION['bizptr']}/photos/appts/*") as $f)
		echo "<br>$f";
}
// ***************************************************************************************************************************

function getFileCacheStats() {
	if(!remoteCacheAvailable())
		return array('error'=>'tblfilecache not found');
	$result = doQuery("SELECT * FROM tblfilecache");
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$stats['cachecount'] += 1;
		$stats['localcount'] += $row['existslocally'];
		$stats['remotecount'] += $row['existsremotely'];
		$localPath = $row['localpath'];
		$found = file_exists($localPath);
		if($found) {
			$filesize = filesize($localPath);
			$filectime = filectime($localPath);
			$mintime = $mintime ? min($mintime, $filectime) : $filectime;
		}
		else {
			$filesize = 0;
			$filectime = null;
		}
		$stats['localcachestorage'] += $filesize;

		if($found && !$row['existslocally']) $stats['stowaways'] += 1;
		if(!$found && $row['existslocally']) $stats['missing'] += 1;
		if(strpos($localPath, 'appts')) {
			$stats['localvisitsstorage'] += $filesize;
			$stats['visitphotostotal'] += 1;
			if($row['existslocally']) 
				$stats['visitphotoslocal'] += 1;
		}
		if(strpos($localPath, 'pets')) {
			$stats['localpetsstorage'] += $filesize;
			$stats['petphotostotal'] += 1;
			if($row['existslocally']) 
				$stats['petphotoslocal'] += 1;
		}
		if(strpos($localPath, 'fromClient'))
			$stats['petphotosfromclient'] += 1;
	}
	$stats['oldestfiledate'] = date('Y-m-d', $mintime);
	return $stats;
}

function getActualStorageStats($bizptr=null) {
	$bizptr = $bizptr ? $bizptr : $_SESSION["bizptr"];
	// fullsize pet photos and fullsize appts photo storage
	foreach(glob("bizfiles/biz_$bizptr/photos/pets/*") as $f) {
		$stats['petphotostotal'] += 1;
		if(!is_dir($f)) $stats['localpetsstorage'] += filesize($f);
	}
	//if($z = glob("bizfiles/biz_{$_SESSION["bizptr"]}/photos/appts/*")) echo "<br>APPT COUNT: ".count($z)."<br>";
	foreach(glob("bizfiles/biz_$bizptr/photos/appts/*") as $d) {
		foreach(glob("$d/*") as $f) {
			$stats['visitphotostotal'] += 1;
			$stats['localvisitsstorage'] += filesize($f);
		}
	}
	return $stats;

}

function getAppointmentPhotoPublicURL($appointmentid, $bizptr=null) {
	$bizptr = $bizptr ? $bizptr : $_SESSION['bizptr'];
	require_once 'response-token-fns.php';
	// ASSUMPTION: if $_SESSION, context is current database
	$respondant = array('userid'=>SYSTEM_USER, 'providerid'=>SYSTEM_USER);
	//return generateResponseURL($_SESSION['bizptr'], $respondant, 
	//										"appointment-photo.php?id=$appointmentid&passthru=", true, 
	//										date('Y-m-d H:i:s', strtotime("+1 year")), $appendToken=true);
								
	//generateResponseToken($bizptr, $respondent, $redirecturl, $systemlogin, $appendToken=false, $expires=null) 
							
	$token = generateResponseToken($bizptr, $respondant, 
											"appointment_photo=1&appointmentid=$appointmentid&token=", true, $appendToken=true, // NOT A URL!
											date('Y-m-d H:i:s', strtotime("+1 year")));
	return globalURL("appointment-photo.php?token=$token");

}

function getAppointmentMapPublicURL($appointmentid, $bizptr=null) {
	$bizptr = $bizptr ? $bizptr : $_SESSION['bizptr'];
	require_once 'response-token-fns.php';
	// ASSUMPTION: if $_SESSION, context is current database
	$respondant = array('userid'=>SYSTEM_USER, 'providerid'=>SYSTEM_USER);
	//return generateResponseURL($_SESSION['bizptr'], $respondant, 
	//										"appointment-photo.php?id=$appointmentid&passthru=", true, 
	//										date('Y-m-d H:i:s', strtotime("+1 year")), $appendToken=true);
								
	//generateResponseToken($bizptr, $respondent, $redirecturl, $systemlogin, $appendToken=false, $expires=null) 
							
	$token = generateResponseToken($bizptr, $respondant, 
											"appointment_map=1&appointmentid=$appointmentid&token=", true, $appendToken=true, // NOT A URL!
											date('Y-m-d H:i:s', strtotime("+1 year")));
	return globalURL("appointment-map.php?token=$token");

}

function dumpCachedImage($cacheORfilecacheid, $maxDims=null, $rotation=0) {
	if(!is_array($cacheORfilecacheid) && $cacheORfilecacheid < 0)
		$file = 'art/photo-unavailable.jpg';
	else {
		$cache = getCachedFileEntry($cacheORfilecacheid);
		if(!$cache || !($file = getCachedFileAndUpdateExpiration($cache))) {
			// dump image not available
			$file = 'art/photo-unavailable.jpg';
		}
	}
	dumpResizedVersion($file, $outName=null, $maxDims, $rotation);
	return true;
}

function targetSize($file, $maxDim) {

	if($maxDim) {  // e.g., array('width',300), array('height',300), , array('either',300), array('box',300, 500), 
	  list($width, $height) = getimagesize($file);
	  $fraction = 1;
		if($maxDim[0] == 'width') {
			if($width > $maxDim[1]) $fraction = $maxDim[1] / $width;
		}
		else if ($maxDim[0] == 'height') {
			if($height > $maxDim[1]) $fraction = $maxDim[1] / $height;
		}
		else if ($maxDim[0] == 'either') {
			$maximumDimensionSize = $maxDim[1];
		  $fraction = $maximumDimensionSize / max($width, $height);
		}
		else if ($maxDim[0] == 'box') {
			$maximumDimensionSize = min($maxDim[1], $maxDim[2]);
		  $fraction = $maximumDimensionSize / max($width, $height);
		}
//if(mattOnlyTEST()) {echo "[$file] [".print_r($maxDim, 1)."] (".print_r($fraction, 1).")";exit;}
		if($fraction < 1) return array(round($width * $fraction), round($height * $fraction), $width, $height);
		else return array(round($width * 1), round($height * 1), $width, $height);
	}
}

function rotateAndSaveImage($cacheORfilecacheid, $rotation) {
	// fetch original image
	if(!is_array($cacheORfilecacheid) && $cacheORfilecacheid < 0)
		$error = 'no cache id supplied';
	else {
		$cache = getCachedFileEntry($cacheORfilecacheid);
		if(!$cache || !($file = getCachedFileAndUpdateExpiration($cache))) {
			$error = 'no cacheed file found: $id';
		}
	}
	if(!$error) {
		if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
			$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
			ini_set("gd.jpeg_ignore_warning", 1);
		}
		
		
		$extension = strtoupper(substr($cache['localpath'], strrpos($cache['localpath'], '.')+1));
		if($extension == 'JPG' || $extension == 'JPEG') $source = imagecreatefromjpeg($cache['localpath']);
		else if($extension == 'PNG') $source = imagecreatefrompng($cache['localpath']);
		if("IGNORE JPG WARNING") {
			ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
		}
	// rotate it
		ini_set('memory_limit', '512M');

		$source = imagerotate ($source , $rotation, 0);
		// save it to the local cache
		if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($source, $cache['localpath']);
		else if($extension == 'PNG') imagepng($source, $cache['localpath']);
		// push it to storage
		getFileCacheParameters();
		$cacheid = cacheFile($cache['localpath'], $cache['remotepath'], 'overwrite');
	}
	return $error;
}

function dumpResizedVersion($f, $outName, $maxDims, $cacheResizedVersion=false, $rotation=0) {
	
//$cacheResizedVersion = mattOnlyTEST();	
	
	ini_set('memory_limit', '512M');

	if($cacheResizedVersion && $maxDims) {
		// on an ad-hoc basis, a photo may be requested to fit inside max dims
		// if so, and if($cacheResizedVersion), cache the resized imahe in such a way that it can be
		// retireved later.
		// if we are willing to cache the resized version we should check for the cached version first
		$cacheItPlease = strpos($maxDims, ',') === FALSE; // never cache resized exact-sized maps or photos
//if(mattOnlyTEST()) {print_r(targetSize($f, $maxDims));exit;}
//if(mattOnlyTEST()) {echo "[$f] [$maxDims]";exit;}

		if(dumpCachedResizedPhoto($f, $maxDims)) 
			return;
	}
	// targetSize expects maxDims to be one of array('width',300), array('height',300), array('either',300), array('box',300, 500)
	$maxDims = (!$maxDims || is_array($maxDims)) ? $maxDims : explode('_', $maxDims);

	if($targetSize = targetSize($f, $maxDims)) { // false if !$maxDims or image small enough
		$newwidth = $targetSize[0];
		$newheight = $targetSize[1];
		$width = $targetSize[2];
		$height = $targetSize[3];
		// Load
		$resized = imagecreatetruecolor($newwidth, $newheight);
		$extension = strtoupper(substr($f, strrpos($f, '.')+1));

		if("IGNORE JPG WARNING") { // for "recoverable error: Premature end of JPEG file"
			$jpeg_ignore_warning = ini_get("gd.jpeg_ignore_warning");
			ini_set("gd.jpeg_ignore_warning", 1);
		}
//if(mattOnlyTEST()) {echo "$newwidth, $newheight<hr>".print_r($targetSize, 1);	exit;}

		if($extension == 'JPG' || $extension == 'JPEG') $source = imagecreatefromjpeg($f);
		else if($extension == 'PNG') $source = imagecreatefrompng($f);
		if("IGNORE JPG WARNING") {
			ini_set("gd.jpeg_ignore_warning", $jpeg_ignore_warning);
		}

		// Resize
		imagecopyresized($resized, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
		
		// Rotation
		if($rotation) {
			$resized = imagerotate ($resized , $rotation, 0);
			header("Expires: Tue, 03 Jul 2001 06:00:00 GMT"); // *
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");			
		}

		if($outName && file_exists($outName)) unlink($outName);

		if($cacheItPlease) {  // if we have gotten here, the file does not yet exist
			$tmpfile = tmpfile();
			$tmpfilename = stream_get_meta_data($tmpfile);
			$tmpfilename = $tmpfilename['uri'];
			if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($resized, $tmpfilename);
			else if($extension == 'PNG') imagepng($resized, $tmpfilename);
			cacheResizedPhoto($tmpfilename, $f, $maxDims);
		}
		//dumpPhotoHeader($f);
		if($extension == 'JPG' || $extension == 'JPEG') imagejpeg($resized, $outName);
		else if($extension == 'PNG') imagepng($resized, $outName);
	}
	else {
		dumpPhotoHeader($f);
		dumpPhoto($f);
	}
}



function resizedPhotoRemoteName($f, $maxDims) {
	//f: bizfiles/biz_3/photos/appts/240/5373.jpg
	//remote resized name: clients/47/photos/appts/either_25/5373.jpg
	/* maxDims format
			array('width', <max width>)
			array('height', <max height>)
			array('either', <square dimension>)
			array('box', <max width>, <max height>)
	*/
	$maxDims = is_array($maxDims) ? $maxDims : explode('_', $maxDims);
	$revPath = array_reverse(explode('/', $f));
	$basename = $revPath[0];
	$clientid = $revPath[1];
	return "clients/$clientid/photos/appts/".join('_', $maxDims)."/$basename";
}

function cacheResizedPhoto($tmpfileName, $photoName, $maxDims) {
	// clients/47/photos/appts/either_25/5373.jpg
	$remoteName = resizedPhotoRemoteName($photoName, $maxDims);
	$parts = explode('/', $remoteName);
	$ownertable = $parts[0] == 'clients' ? 'tblclient' : 'tblprovider';
	$ownerptr = $parts[1];
	uploadFileForOwner($tmpfileName, $ownerptr, $ownertable, $usename=null, $remoteName);
}

function dumpCachedResizedPhoto($photoName, $maxDims) {
	$remoteName = resizedPhotoRemoteName($photoName, $maxDims);
	// ensure file is registered
	$parts = explode('/', $remoteName);
	$ownertable = $parts[0] == 'clients' ? 'tblclient' : 'tblprovider';
	$ownerptr = $parts[1];
	$entryid = fetchRow0Col0(
		"SELECT remotefileid 
			FROM tblremotefile 
			WHERE ownertable = '$ownertable' AND ownerptr = '$ownerptr' AND remotepath = '$remoteName' LIMIT 1", 1);;
//if(mattOnlyTEST()) {echo "[$photoName] [$maxDims] [$entryid]";exit;}
	
	if(!$entryid) return false;
	else {
//if(mattOnlyTEST()) echo "BANG! [$photoName] [$remoteName]".print_r( $entryid, 1);exit;
		dumpPhotoHeader($photoName);
		dumpRemoteFileId($entryid);
		return true;
	}
}








function dumpPhotoHeader($file) {
	$ctypes = array('jpg'=>'jpeg', 'png'=>'png', 'jpeg'=>'jpeg', 'gif'=>'gif');
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	//if(mattOnlyTEST()) {echo "$file: Content-Type: image/{$ctypes[$extension]}";} else
	header("Content-Type: image/{$ctypes[$extension]}");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
}

function dumpPhoto($file) {
	dumpPhotoHeader($file);
	readfile($file);
}

function getCachedFileEntry($cacheORfilecacheid) {

	if(is_array($cacheORfilecacheid)) return $cacheORfilecacheid;
	else return fetchFirstAssoc("SELECT * FROM tblfilecache WHERE filecacheid = '$cacheORfilecacheid' LIMIT 1", 1);
}

function getCachedFileAndUpdateExpiration($cacheORfilecacheid) {
	$cache = getCachedFileEntry($cacheORfilecacheid);
	if(!$cache) {
		logError("Attempt to access non-existent cached file: $filecacheid");
		return null;
	}
	
	global $fileCacheParameters;  
	// This should REALLY be set bfore this function is called, 
	// to ensure proper expiration for the type of file requested
	if(!$fileCacheParameters) getFileCacheParameters();
	
	$defaultShelfLifeSeconds = $fileCacheParameters['defaultShelfLifeSeconds'];
	$localExpirationDate = date('Y-m-d H:i:s', strtotime("+$defaultShelfLifeSeconds seconds"));
	
	$defaultRemoteShelfLifeSeconds = $fileCacheParameters['defaultRemoteShelfLifeSeconds'];
	if($defaultRemoteShelfLifeSeconds == -1) $remoteExpirationDate = noExpirationDate();
	else $remoteExpirationDate = date('Y-m-d H:i:s', strtotime("+$defaultRemoteShelfLifeSeconds seconds"));
	
	
	if(!$cache['existslocally']) restoreCachedFile($cache['localpath'], $cache['remotepath']);
	if(!file_exists($cache['localpath'])) return null;
	else {
		$cache['existslocally'] = 1;
		$cache['expireslocally'] = $localExpirationDate;
		$cache['expiresremotely'] = $remoteExpirationDate;
		updateTable('tblfilecache', $cache, "filecacheid = '{$cache['filecacheid']}'", 1);
		return $cache['localpath'];
	}
}

function getTemporaryURLToRemoteFile($filecacheid) {
	//// Get a pre-signed URL for an Amazon S3 object
	//$signedUrl = $client->getObjectUrl($bucket, 'data.txt', '+10 minutes');
}




function getPetPhotoFileCacheParameters() {
	global $fileCacheParameters;
	$fileCacheParameters =	array(
			'localCountLimit' => 60,
			'defaultShelfLifeSeconds' => 30 /* days */ * 24 * 60 * 60,
			'defaultRemoteShelfLifeSeconds' => -1 // no remote expiration
			);
	return $fileCacheParameters;
}



function getFileCacheParameters() {
	/*
	11/28/2016.  Limit: 100 photos
	
	Filesystem           1K-blocks      Used Available Use% Mounted on
	/dev/mapper/vglocal20130822-root00
											 136111280 115508068  13689164  90% /
	*/
	global $fileCacheParameters;
	$fileCacheParameters =	array(
			'localCountLimit' => 40,
			'defaultShelfLifeSeconds' => 30 /* days */ * 24 * 60 * 60,
			'defaultRemoteShelfLifeSeconds' => 90 /* days */ * 24 * 60 * 60
			);
	return $fileCacheParameters;
}

function getRemoteStorageCredentials($serviceName=null) {
	require_once "common/db_fns.php";
	$settings = ensureInstallationSettings();
	$serviceName = $serviceName ? $serviceName : $settings['defaultRemoteStorageService'];
	if($serviceName == 'AWS') 
		return array('servicename'=>$serviceName,
									'accessKey'=>$settings['amazonAWSAccessKey'],
									'secretAccessKey'=>$settings['amazonAWSSecretAccessKey'],
									'bucketName'=>$settings['amazonAWSBucketName']);
}




function cacheClientFile($file, $clientid, $clientRelativeRemotePath) {  // UNUSED
	// Assume $_SESSION["bizptr"]
	// in our bucket, store client files as "biz_$bizNum
	if(!$_SESSION["bizptr"]) {
		echo "No Business Context!";
		exit;
	}
	$remotePath = "biz_{$_SESSION["bizptr"]}/clients/$clientid/$clientRelativeRemotePath";
	cacheFile($file, $remotePath);
}

function cacheFile($file, $remotePath, $overwrite=false) {
	global $fileCacheParameters;
	if(!$fileCacheParameters) getFileCacheParameters();
	
	$defaultShelfLifeSeconds = $fileCacheParameters['defaultShelfLifeSeconds'];
	$defaultRemoteShelfLifeSeconds = $fileCacheParameters['defaultRemoteShelfLifeSeconds'];
	$localExpirationDate = date('Y-m-d H:i:s', strtotime("+$defaultShelfLifeSeconds seconds"));
	if($defaultRemoteShelfLifeSeconds == -1) $remoteExpirationDate = noExpirationDate();
	else $remoteExpirationDate = date('Y-m-d H:i:s', strtotime("+$defaultRemoteShelfLifeSeconds seconds"));
	$credentials = getRemoteStorageCredentials();
	// cache the file locally.  Do NOT cache remotely at this time.
	if($overwrite) {
		$existingCacheId = fetchRow0Col0($sql = "SELECT filecacheid FROM tblfilecache WHERE localpath = '$file' LIMIT 1");
		if($existingCacheId) {
			$newCacheId = $existingCacheId;
			updateTable('tblfilecache', array('existslocally'=>1, 'expireslocally'=>$localExpirationDate), "filecacheid = '$newCacheId'", 1);
		}
	}
	if(!$existingCacheId)
		$newCacheId = insertTable('tblfilecache', 
					array('remoteservice'=>$credentials['servicename'],  
								'localpath'=>$file, 'existslocally'=>1,  'expireslocally'=>$localExpirationDate,
								'remotepath'=>$file, 'existsremotely'=>0,  'expiresremotely'=>$remoteExpirationDate), 1);
	checkCacheLimits();
	return $newCacheId;
}

function saveCachedFileRemotely($cacheORfilecacheid) {
	$cache = getCachedFileEntry($cacheORfilecacheid);
	$credentials = getRemoteStorageCredentials();
	if(file_exists($cache['localpath'])) {
		if($credentials['servicename'] == 'AWS') 
			return saveAWS($cache['localpath'], $cache['remotepath'], $credentials);
	}
	return false;
}

function ensureFileExistsLocally($localpath) {
	if(file_exists($localpath)) return true;
	if($cache = fetchFirstAssoc(
		"SELECT * FROM tblfilecache 
			WHERE localpath = '$localpath'
			LIMIT 1"))
		return getCachedFileAndUpdateExpiration($cache);
}


function deleteFileFromCache($localpath, $credentials=null) {
	$cache = fetchFirstAssoc("SELECT * FROM tblfilecache WHERE localpath = '$localpath' LIMIT 1");
	if(file_exists($localpath)) unlink($localpath);
	if(!$cache) return;
	deleteRemoteCachedFile($cache);
	deleteTable('tblfilecache', "filecacheid = {$cache['filecacheid']}", 1);
}

function deleteRemoteCachedFile($cacheORfilecacheid, $credentials=null) {
	$cache = getCachedFileEntry($cacheORfilecacheid);
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	if($credentials['servicename'] == 'AWS') return deleteAWS($cache['remotepath'], $credentials);
}

function deleteAWS($remotePath, $credentials=null) {
	// http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	// returns true on delete
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	$bucket = $credentials['bucketName'];
	require_once 'aws-autoloader.php';
	
	//echo print_r($credentials,1).'<hr>'.print_r(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['amazonAWSSecretAccessKey']),1);
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$result = $s3Client->deleteObject(
		array(
			'Bucket' => $credentials['bucketName'],
			'Key'    => $remotePath
			));
	return $result;
}

function saveAWS($localPath, $remotePath, $credentials=null, $contentType=null) {
	// http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	$bucket = $credentials['bucketName'];
	require_once 'aws-autoloader.php';
	//echo print_r($credentials,1).'<hr>'.print_r(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['amazonAWSSecretAccessKey']),1);
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$obj = array(
			'Bucket' => $credentials['bucketName'],
			'Key'    => $remotePath,
			'Body'   => file_get_contents($localPath)
	);
	if($contentType) $obj['ContentType'] = $contentType;
	$result = $s3Client->putObject($obj);
	return $result;
}

function restoreCachedFile($localPath, $remotePath) {
	$credentials = getRemoteStorageCredentials();	
	if($credentials['servicename'] == 'AWS') 
//if(mattOnlyTEST()) echo print_r($remotePath,1)."<hr>"; // .$s3Client."<hr>"
		if($success = restoreAWS($localPath, $remotePath, $credentials))
			checkCacheLimits();
	return $success;
}

function restoreAWS($localPath, $remotePath, $credentials=null) {
	// http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	
	require_once 'aws-autoloader.php';
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$bucket = $credentials['bucketName'];
//if(mattOnlyTEST()) echo print_r($s3Client,1)."<hr>"; // .$s3Client."<hr>"
	try {
		$result = $s3Client->getObject(array(
				'Bucket' => $bucket,
				'Key'    => $remotePath
		));
	} catch (AmazonClientException $e) {
		logLongError("AWS could not return [$remotePath]. ".$e->toString());
	} catch (AmazonServerException $e) {
		logLongError("AWS could not return [$remotePath]. ".$e->toString());
	} catch (Exception $e) {
		logLongError("AWS could not return [$remotePath]. ".$e->__toString());
	}
//if(mattOnlyTEST()) echo print_r($s3Client,1)."<hr>bucket [$bucket] key [$remotePath] RESULT: [".print_r($result,1)."]";
	if($result) file_put_contents($localPath, $result['Body']);
	return $result;
}

function checkAWSErrorFAST($remotePath, $credentials=null) {
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	require_once 'aws-autoloader.php';
	try {
		$object = remoteObjectDescription($remotePath, $credentials);
	} catch (AmazonClientException $e) {
		$error = "AWS could not return [$remotePath]. ".$e->toString();
	} catch (AmazonServerException $e) {
		$error = "AWS could not return [$remotePath]. ".$e->toString();
	} catch (Exception $e) {
		$error = "AWS could not return [$remotePath]. ".$e->__toString();
	}
	
	return $error;
}

function checkAWSError($remotePath, $credentials=null) {
	// http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	
	require_once 'aws-autoloader.php';
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$bucket = $credentials['bucketName'];
//if(mattOnlyTEST()) echo print_r($s3Client,1)."<hr>"; // .$s3Client."<hr>"
	try {
		$result = $s3Client->getObject(array(
				'Bucket' => $bucket,
				'Key'    => $remotePath
		));
	} catch (AmazonClientException $e) {
		$error = "AWS could not return [$remotePath]. ".$e->toString();
	} catch (AmazonServerException $e) {
		$error = "AWS could not return [$remotePath]. ".$e->toString();
	} catch (Exception $e) {
		$error = "AWS could not return [$remotePath]. ".$e->__toString();
	}
	
	return $error;
}


function define_tblfilecache() {
	doQuery( <<<DEF
	CREATE TABLE IF NOT EXISTS `tblfilecache` (
		`filecacheid` int(11) NOT NULL AUTO_INCREMENT,
		`remoteservice` varchar(40) NOT NULL,
		`localpath` varchar(255) NOT NULL,
		`existslocally` tinyint(4) NOT NULL,
		`expireslocally` datetime NOT NULL,
		`bucket` varchar(255) NOT NULL,
		`remoteid` varchar(40) DEFAULT NULL,
		`remotepath` varchar(255) NOT NULL,
		`existsremotely` tinyint(4) NOT NULL,
		`expiresremotely` datetime NOT NULL,
		PRIMARY KEY (`filecacheid`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
DEF
	, 1);
}

// *******************************************************************
// PURELY REMOTE FILES
// Idea: allow document upload and retrieval by manager
function define_tblremotefile() {
	doQuery( <<<DEF
CREATE TABLE IF NOT EXISTS `tblremotefile` (
  `remotefileid` int(11) NOT NULL AUTO_INCREMENT,
  `remoteservice` varchar(40) NOT NULL,
  `bucket` varchar(255) NOT NULL,
  `remotepath` varchar(255) NOT NULL,
  `ownertable` varchar(40) NOT NULL,
  `ownerptr` int(11) NOT NULL,
  `filesize` int(11) NOT NULL DEFAULT '0',
  `uploaded` datetime DEFAULT NULL,
  PRIMARY KEY (`remotefileid`),
  UNIQUE KEY `ownertable` (`ownertable`,`ownerptr`,`remotepath`),
  KEY `ownertable_2` (`ownertable`,`ownerptr`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
DEF
	, 1);
}

function getRemoteFileEntry($remotefileidOrEntry) {
	if(is_array($remotefileidOrEntry)) return $remotefileidOrEntry;
	else return fetchFirstAssoc("SELECT remotepath FROM tblremotefile WHERE remotefileid = '$remotefileidOrEntry' LIMIT 1", 1);
}

function returnOwnerFileToBrowser($basename, $ownerptr, $ownertable) {
	$entry = findFileForOwner($basename, $ownerptr, $ownertable);
	if($entry) returnToBrowser($entry['remotepath']);
}

function returnToBrowser($remotePath, $download=false, $bizptr=null) { // $bizptr to allow for public doc links
	$basename = basename($remotePath);
	$absolute = true;
	$remoteObj = remoteObjectDescription($remotePath);
	if(!$remoteObj) {
		$remoteObj = remoteObjectDescription(absoluteRemotePath($remotePath, $bizptr));
		$absolute = false;
	}
	if(!$remoteObj) {
		echo "File not found.";
		exit;
	}
	$remoteObj = $remoteObj->toArray();
//if(mattOnlyTEST()) {echo "[$remotePath]  => ".print_r($remoteObj, 1); return;}
if(0 && mattOnlyTEST()){
	$disposition = $download ? 'attachment' : 'inline';
	echo("Content-Type: ".$remoteObj['ContentType'])
	."<br>".("Pragma: public") // required
	."<br>".("Expires: 0")
	."<br>".("Content-disposition: $disposition; filename=\"$basename\"")
	."<br>".("Cache-Control: must-revalidate, post-check=0, pre-check=0")
	."<br>".("Cache-Control: private")
	."<br>".("Content-Transfer-Encoding: binary");
	exit;
}

	header("Content-Type: ".$remoteObj['ContentType']);
	header("Pragma: public"); // required
	header("Expires: 0");
	$disposition = $download ? 'attachment' : 'inline';
	header("Content-disposition: $disposition; filename=\"$basename\"");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary"); // ??
	dumpRemoteFile($remotePath, $bizptr, $absolute);
}


function dumpRemoteFile($remotePath, $bizptr=null, $absolute=false) { // fetch contents to standard output
	$bizptr = $bizptr ? $bizptr : $_SESSION["bizptr"];
	// http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	require_once 'aws-autoloader.php';
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$bucket = $credentials['bucketName'];
//if(mattOnlyTEST()) echo print_r($s3Client,1)."<hr>"; // .$s3Client."<hr>"
	$absRemotePath = $absolute ? $remotePath : absoluteRemotePath($remotePath, $bizptr);
	try {
		$result = $s3Client->getObject(array(
				'Bucket' => $bucket,
				'Key'    => $absRemotePath
		));
	} catch (AmazonClientException $e) {
			logLongError("AWS could not return [$absRemotePath]. ".$e->toString());
	} catch (AmazonServerException $e) {
		logLongError("AWS could not return [$absRemotePath]. ".$e->toString());
	} catch (Exception $e) {
		logLongError("AWS could not return [$absRemotePath]. ".$e->__toString());
	} catch (NoSuchKeyException $e) {
		logLongError("AWS could not find key [$absRemotePath]. ".$e->__toString());
	}
//if(mattOnlyTEST()) logError("$absRemotePath body length: ".strlen($result['Body']));	
	if($result) echo $result['Body'];
	return $result != null;
}

function dumpRemoteFileId($remotefileid) { // fetch contents to standard output
	if($entry = getRemoteFileEntry($remotefileid))
		return dumpRemoteFile($entry['remotepath']);
}

function deleteRemoteFileId($remotefileidOrEntry, $credentials=null) {
	$entry = getRemoteFileEntry($remotefileidOrEntry);
	if(!$entry) return;
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	if($credentials['servicename'] == 'AWS') return deleteRemoteFile($entry['remotepath'], $credentials);
}

function deleteRemoteFile($remotePath, $credentials=null) {
	if(!$credentials) $credentials = getRemoteStorageCredentials();
	if($credentials['servicename'] == 'AWS') return deleteAWS($remotePath, $credentials);
}

function saveEntryRemotely($localPath, $remotefileidOrEntry) {
	$entry = getRemoteFileEntry($remotefileidOrEntry);
	$credentials = getRemoteStorageCredentials();
	if($entry && file_exists($localPath)) {
		return saveFileRemotely($localPath, $entry['remotepath']);
	}
	return false;
}

function absoluteRemotePath($remotePath, $bizptr=null) {
	// remotePaths are stored in tblremotefile without the bizptr prefix
	// prepend the bizptr prefix and return it as the absolute path
	$bizptr = $bizptr ? $bizptr : $_SESSION["bizptr"];
	if(!$bizptr) {
		echo "No Business Context!";
		exit;
	}
	return "bizfiles/biz_{$bizptr}/$remotePath";
}

function saveFileRemotely($localPath, $remotePath, $credentials) {
	// $remotePath is relative to biz directory
	$remotePath = absoluteRemotePath($remotePath);

	$credentials = $credentials ? $credentials : getRemoteStorageCredentials();
	if(file_exists($localPath)) {
		if($credentials['servicename'] == 'AWS') 
			return saveAWS($localPath, $remotePath, $credentials, $contentType=enhanced_mime_content_type($localPath));
	}
	
	return false;
}

function uploadClientFile($filename, $clientptr, $usename=null) {
	return uploadFileForOwner($filename, $clientptr, 'tblclient', $usename);
}

function uploadProviderFile($filename, $providertr, $usename=null) {
	return uploadFileForOwner($filename, $providertr, 'tblprovider', $usename);
}

function uploadOfficeFile($filename, $ownerptr, $usename=null) {
	return uploadFileForOwner($filename, $ownerptr, 'office', $usename);
}

function deleteFileForOwner($basename, $ownerptr, $ownertable) {
	$classes = explodePairsLine("tblclient|clients||tblprovider|providers");
	$remotePath = auxFilesPrefix($ownerptr, $ownertable).$basename;
	$credentials = getRemoteStorageCredentials();
	if(deleteAWS(absoluteRemotePath($remotePath), $credentials)) {
		$saferemotepath = mysql_real_escape_string($remotePath);
		return deleteTable('tblremotefile', "ownerptr = $ownerptr AND ownertable = '$ownertable' AND remotepath = '$saferemotepath'", 1);
	}
	return false;
}

function findFileForOwner($basename, $ownerptr, $ownertable) {
	$remotePath = mysql_real_escape_string(auxFilesPrefix($ownerptr, $ownertable).$basename);
	return fetchFirstAssoc(
		"SELECT * 
			FROM tblremotefile 
			WHERE ownerptr = $ownerptr 
				AND ownertable = '$ownertable' 
				AND remotepath = '$remotePath' LIMIT 1", 1);
}

function uploadFileForOwner($filename, $ownerptr, $ownertable, $usename=null, $remotePath=null) {
	// there is one namespace per owner
	// the remotepath is
	//	"biz_{$_SESSION["bizptr"]}/clients/$clientid/auxfiles/basename"
	// or
	// "biz_{$_SESSION["bizptr"]}/clients/$providerid/auxfiles/basename"
	// where basename = basename($filename)
	// $usename should be employed when $filename is a temp file
	if(!$remotePath) {
		$basename = $usename ? $usename : basename($filename);
		$remotePath = auxFilesPrefix($ownerptr, $ownertable).$basename;
	}
	$credentials = getRemoteStorageCredentials();
	if(file_exists($filename)) {
		$fileSize = filesize($filename);
		if($credentials['servicename'] == 'AWS') {
			if(saveFileRemotely($filename, $remotePath, $credentials)) { // absoluteRemotePath( is called in saveFileRepotely
//if(mattOnlyTEST()) logError("Uploaded: ".absoluteRemotePath($remotePath));
				$object = array('ownerptr'=>$ownerptr, 'ownertable'=>$ownertable, 'remotepath'=>$remotePath, 'uploaded'=>date('Y-m-d H:i:s'), 'filesize'=>$fileSize, 'remoteservice'=>'AWS', 'bucket'=>sqlVal("''"));
				$savedRemoteFileId = replacetable('tblremotefile', $object, 1);
				return $savedRemoteFileId;
			}
		}
	}
	return false;
}

function filePreviouslyUploaded($filename, $ownerptr, $ownertable) {
	$ownerfiles = listRemoteFilesForOwner($ownerptr, $ownertable);
	$basename = basename($filename);
	return $ownerfiles[$basename];
}

function getOwnerClass($ownertable) {
	static $classes;
	if(!$classes) $classes = array('tblclient'=>'clients', 'tblprovider'=>'providers');
	return $classes[$ownertable];
}

function auxFilesPrefix($ownerptr, $ownertable) {
	$ownerclass = getOwnerClass($ownertable);
	return "$ownerclass/$ownerptr/auxfiles/";
}

function listRemoteFileNamesForOwner($ownerptr, $ownertable) {
	// http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html#_listObjects
	// use mime_content_type($file) to guess type of file
	if($list = listRemoteFilesForOwner($ownerptr, $ownertable))
		$files = array_keys($list);
	sort($files);
	return $files;
}

function listRemoteFilesForOwner($ownerptr, $ownertable, $prefix=null, $noKeys=false) {
	// http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html#_listObjects
	// use mime_content_type($file) to guess type of file
	$credentials = getRemoteStorageCredentials();
	$bucket = $credentials['bucketName'];
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	$prefix = $prefix ? $prefix : auxFilesPrefix($ownerptr, $ownertable);
	$list = $s3Client->listObjects(array('Bucket'=>$bucket, 'Prefix'=>absoluteRemotePath($prefix)));
	if($list) {
		$data = $list->toArray();
		foreach($data['Contents'] as $obj) {
			if($noKeys) $final[] = $obj;
			else $final[basename($obj['Key'])] = $obj;
		}
		return $final;
	}
	return array();
}

function remoteObjectDescription($absRemotePath, $credentials=null) {
	// http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html#_listObjects
	// use mime_content_type($file) to guess type of file
	$credentials = $credentials ? $credentials : getRemoteStorageCredentials();
	$bucket = $credentials['bucketName'];
	$s3Client = S3Client::factory(array('key'=>$credentials['accessKey'], 'secret'=>$credentials['secretAccessKey']));
	try {$description = $s3Client->headObject(array('Bucket'=>$bucket, 'Key'=>$absRemotePath));
	} catch (Aws\S3\Exception\NoSuchKeyException $e) {
		logLongError("AWS could not find key [$absRemotePath]. ".$e->__toString());
		if(0 && mattOnlyTEST()) echo "AWS could not find key [$absRemotePath]. ".$e->__toString();
	}
	catch (Aws\S3\Exception\S3Exception $e) {
			logLongError("AWS exception [$absRemotePath]. ".$e->__toString());
			if(mattOnlyTEST()) echo "AWS Bad request [$absRemotePath] [[".print_r($credentials, 'ASSOC')."]].<p>".$e->__toString();
	}
	return $description;
}

function safeRemoteName($localFileName, $remoteFileName) {
	// produce a version of remoteFileName with an extension that matches its MIME type
	$properExtensionsRaw = 
	'image/jpeg jpg
	image/gif gif
	image/png png
	text/plain txt
	application/msword doc
	application/vnd.openxmlformats-officedocument.wordprocessingml.document docx
	text/html htm
	application/vnd.oasis.opendocument.spreadsheet ods
	application/vnd.oasis.opendocument.text odt
	application/pdf pdf
	application/rtf rtf
	application/vnd.ms-excel xls
	application/vnd.openxmlformats-officedocument.spreadsheetml.sheet xlsx
	text/csv csv
	';
	foreach(array_map('trim', explode("\n", trim($properExtensionsRaw))) as $pair) {
		$pair = explode(' ', $pair);
		$properExtensions[$pair[0]] = $pair[1];
	}

	$mimeType = enhanced_mime_content_type($localFileName);


	$dot = strrpos($remoteFileName, '.');
	$dot = $dot ? $dot : strlen($remoteFileName); 
	return substr($remoteFileName, 0, $dot).'.'.$properExtensions[$mimeType];
}

function localFileMIMEType($localFileName) {
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	return finfo_file($finfo, $localFileName);
}


function uploadPostedFile($formFieldName, $ownerptr, $ownertable, $usename=null) {
	if($_FILES) {
		if(!function_exists('getAllowedMIMETypes')) { // allow multiple calls to this uploadPostedFile
			function getAllowedMIMETypes() { 
				return array('image/jpeg','image/png', 'application/msword', 'application/pdf', 
					'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
					'text/plain', 'application/rtf', 'text/csv'); }
			function getAllowedFileTypes() { return array('JPG','JPEG','PNG', 'DOC', 'PDF', 'DOCX', 'XLS', 'XLSX', 'CLSX ??', 'TXT', 'RTF', 'CSV', 'NUMBERS'); }
			function getAllowedFileTypesDescr() { return "Document, Image, spreadsheet, etc."; }

			function invalidFileUpload($formFieldName) {
				global $maxPixels, $maxDim;
				$oldError = error_reporting(E_ALL - E_WARNING);
				$failure = null;

				$extension = strtoupper(substr($_FILES[$formFieldName]['name'], strrpos($_FILES[$formFieldName]['name'], '.')+1));
				if($failure = $_FILES[$formFieldName]['error']) {
					if($failure == 1) $failure = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
					else if($failure == 2) $failure = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
					else if($failure == 3) $failure = "The uploaded file was only partially uploaded.";
					else if($failure == 4) $failure = "No file was uploaded.";
					else if($failure == 6) $failure = "Missing a temporary folder.";
					else if($failure == 7) $failure = "Failed to write file to disk.";
					else if($failure == 8) $failure = "File upload stopped by extension.";
				}
				else if(FALSE && $extension == 'ZIP') {
					require_once "zip-fns.php";
					$zipFile = $_FILES[$formFieldName]['tmp_name'];
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
		}

		$allowedTypes = getAllowedFileTypes();
		$allowedTypesDescr = getAllowedFileTypesDescr();

		$originalName = $_FILES[$formFieldName]['name'];
		
		$remoteName = $usename ? $usename : $_FILES[$formFieldName]['name'];
		$remoteName = safeRemoteName($_FILES[$formFieldName]['tmp_name'], $remoteName);
//if(mattOnlyTEST()) {echo 	"safeRemoteName: $remoteName";exit;}
		if(TRUE) {
			$mimeType = enhanced_mime_content_type($_FILES[$formFieldName]['tmp_name']);
			$mimeTypeIsValid = in_array($mimeType, getAllowedMIMETypes());
//if(mattOnlyTEST() && !$mimeTypeIsValid) {echo "BAD MIMETYPE: $mimeType";exit;}
		}
		else { // old, unreliable method
			$dot = strrpos($_FILES[$formFieldName]['name'], '.');
			$extension = strtoupper(substr($_FILES[$formFieldName]['name'], $dot+1));
			$mimeTypeIsValid = in_array($extension, $allowedTypes);
		}
			
		if(!$mimeTypeIsValid)
			$error = "Uploaded file MUST be a $allowedTypesDescr.<br>[$originalName] does not qualify."; //." [".mime_content_type($_FILES[$formFieldName]['tmp_name'])."]";
	//if(mattOnlyTEST() && $failure) {echo $target_path;exit;}  

		if($error || ($error = invalidFileUpload($formFieldName, $target_path))) 
			$error = "The file $originalName could not be used because <br>$error";
		else if($ownertable == 'tblclient') {
			$remoteFileId = uploadClientFile($_FILES[$formFieldName]['tmp_name'], $ownerptr, $remoteName);
			$message = "File $originalName has been uploaded.";
		}
		else if($ownertable == 'tblprovider') {
			$remoteFileId = uploadProviderFile($_FILES[$formFieldName]['tmp_name'], $ownerptr, $remoteName);
			$message = "File $originalName has been uploaded.";
		}
		else if($ownertable == 'office') {
			$remoteFileId = uploadOfficeFile($_FILES[$formFieldName]['tmp_name'], $ownerptr, $remoteName);
			$message = "File $originalName has been uploaded.";
		}
		return array('message'=>$message, 'error'=>$error, 'remotefileid'=>$remoteFileId);
	}
}

function enhanced_mime_content_type($file) { // HACK to distinguish newer Word docs and Excel files
	//$mimeType = mime_content_type($file);  // deprecated.
	$mimeType = localFileMIMEType($file);
	if($mimeType == 'application/zip') {
		$arch = zip_open($file);
		while($entry = zip_read($arch)) {
			//echo zip_entry_name($entry)."<br>";
			$entryName = zip_entry_name($entry);
//if(mattOnlyTEST()) echo "$entryName<p>"	;
			if(strpos($entryName, 'docProps/') === 0) $docProps = true;
			else if(strpos($entryName, 'xl/') === 0) $xl = true;
			else if(strpos($entryName, 'word/') === 0) $word = true;
			else if(strpos($entryName, 'Index/Tables') === 0) $numbers = true;
		}
		if($docProps && $xl)
			$mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		else if($docProps && $word)
			$mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
		else if($numbers)
			$mimeType = 'application/x-iwork-keynote-sffnumbers';
	}
	return $mimeType;
}

function mimeTypeLabel($type) {
	static $labels;
	if(!$labels) {
		$raw = <<<LABELS
application/x-iwork-keynote-sffnumbers|Numbers file
application/msword|Microsoft Word file.
application/octet-stream|Unknown
application/pdf|Adobe Acrobat file
application/postscript|PostScript file
application/rtf|Rich Text Format
application/x-gtar|Compressed Linux file.
application/x-gzip|Compressed Linux file
application/x-java-archive|Java .jar file.
application/x-java-serialized-object|Java .ser file
application/x-java-vm|Java class file
application/x-tar|Compressed Linux file
application/zip|ZIP compressed file
audio/x-aiff|Apple sound file
audio/basic|Basic 8-bit ULAW file
audio/x-midi|MIDI sound file
audio/x-wav|WAV sound file
audio/mpeg|MP3 sound file
audio/mp4|MP4 sound file
image/bmp|Bitmap image
image/gif|GIF Image
image/jpeg|JPEG image
image/png|PNG image
image/tiff|TIFFImage
image/x-xbitmapX bitmap format
multipart/x-gzip|gzip compressed file
multipart/x-zip|ZIP compressed file
text/html|HTML file
text/plain|Plain text file
text/richtext|Rich text format
video/mpeg|MPEG compressed video
video/mp4|MP4 compressed video
video/3gp|3gp compressed video
video/vnd.vivo|VIVO video codec
video/quicktimeApple's QuickTime file
video/x-msvideo|Microsoft's video format
application/vnd.openxmlformats-officedocument.wordprocessingml.document|Microsoft Word Document
application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|Microsoft Excel Spreadsheet
LABELS;
		foreach(explode("\n", $raw) as $line) {
			$pair = explode('|', trim($line));
			$labels[$pair[0]] = $pair[1];
		}
	}
	return $labels[$type] ? $labels[$type] : "[$type]";
}
/***************************************************************
/*
Strategy:
We do not want to store some files indefinitely.  E.g., visit photos

So we set up a database table:
tblfilecache
filecacheid
remoteservice - usually installation settings>defaultRemoteStorageService
localpath - path to file on LeashTime's server (typically under bizfiles)
existslocally - set to true initially
expireslocally: datetime
bucket
remoteid
remotepath - fully qualified path on remote file server.  In AWS, this would take the form of "bucketName|remotepath".  
		E.g., "leashtime|biz_390/clients/390/visitphotos/2394089.jpg"
existsremotely - set to true initially
expiresremotely: datetime


To retrieve a (possibly) cached file we use a filecacheid:
1. We update the expireslocally and expiresremotely dates (to minimize chance of asynchronous deletion)
2. Check to see if (existslocally == 1) and the file actually exists locally and return it if it does
3. Else
3.1 if(remotepath not null) check for file's existence
3.1.2 if(remotepath actually found) copy file to local storage 
3.1.3.1  SET existslocally=1
3.1.3.2  prepare to return file
3.1.3 else set expiresremotely and expireslocally to five minutes ago and return NULL
3.2 checkCacheLimits()
3.3 return file
*/

function remoteCacheAvailable() {
	return in_array('tblfilecache', fetchCol0("SHOW tables"));
}

function noExpirationDate() {
	return "1970-01-01 00:00:00";
}

function fileExpiresOnDate($date) {
	return strtotime($date) > strtotime("2010-01-01 00:00:00");
}

function checkCacheLimits($localCountLimitOverride=null) {
	//1.1 If number of cached files (# rows with existslocally=1) exceeds the limit
	global $fileCacheParameters;
	if(!$fileCacheParameters) getFileCacheParameters();
	if($localCountLimitOverride !== null) 
		$fileCacheParameters['localCountLimit'] = $localCountLimitOverride;
	$cachedFiles = fetchAssociations(
		"SELECT * FROM tblfilecache 
			WHERE existslocally=1 
			ORDER BY expireslocally ASC");
	//1.2 For each cached file over the local limit
	for($i=0;$i<count($cachedFiles)-$fileCacheParameters['localCountLimit'];$i++) {
		$credentials = $credentials ? $credentials : getRemoteStorageCredentials();

		// 1.3   SET existslocally=0
		$cachedFile = array('existslocally'=>'0');
		//1.4 	if(expiresremotely) has passed
		$expiresremotely = $cachedFiles[$i]['expiresremotely'];
		if(FALSE && fileExpiresOnDate($expiresremotely) && strtotime($expiresremotely) < time()) {
			//1.4.1   DELETE REMOTE COPY
			deleteRemoteCachedFile($cachedFiles[$i], $credentials);
			//1.4.2   SET existsremotely = 0
			$cachedFile['existsremotely'] = 0;
		}
		else {
			//1.5 save file remotely
			$cachedFile['existsremotely'] = saveCachedFileRemotely($cachedFiles[$i]) ?  '1' : '0';
		}
		//1.6  delete local copy of oldest file
//if(mattOnlyTEST()) echo "BEFORE {$cachedFiles[$i]['localpath']} exists: [".file_exists($cachedFiles[$i]['localpath'])."]<br>";
		if(file_exists($cachedFiles[$i]['localpath'])) unlink($cachedFiles[$i]['localpath']);
//if(mattOnlyTEST()) echo "AFTER {$cachedFiles[$i]['localpath']} exists: [".file_exists($cachedFiles[$i]['localpath'])."]<p>";
		
		updateTable('tblfilecache', $cachedFile, "filecacheid = '{$cachedFiles[$i]['filecacheid']}'", 1);
	}
}

function correctCachedFileRetrievability($remotePath, $credentials=null) {
	$existsRemotely = checkAWSErrorFAST($remotePath, $credentials) ? 0 : 1;
	updateTable('tblfilecache', array('existsremotely'=>$existsRemotely), "remotepath = '$remotePath'", 1);
}
		

function cachedFileIsRetrievable($checkremotestore=true) {// returns 0=not available, 1=locally available, 2=remotelyavailable
//1.1 if (existslocally=1 AND file at localpath actually exists) return 1
//1.2 else if(existslocally=1 AND file at localpath DOES NOT actually exist) SET existslocally=0 (just in case)
//1.3 if(!remotepath) return 0
//1.4 else 
//1.4.1  if(!$checkremotestore) return 2
//1.4.2  if(remotepath actually found) return 2
//1.4.3  else SET remotepath = NULL and return 0
}
//print_r(getCredentials('AWS'));