<? // gallery-office.php
// this utility/test page is base on gallery1.php
use Aws\S3\S3Client;
// trial of https://www.jqueryscript.net/gallery/Responsive-Fullscreen-Simple-Scroll-Gallery.html
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "pet-fns.php";
require_once "remote-file-storage-fns.php";
require_once 'aws-autoloader.php';

if(!$_REQUEST['nugget'])
	$locked = locked('o-'); 

extract($_REQUEST);

if($test) {
	//maxDims examples: width_300, height_400, either_800), box_300_500)

	$remoteFile = generateThumbnailRemoteFileForAppt($apptid=$test, 'test', "either_800", $checkFirst=true);
	if($remoteFile) {
		//echo "$remoteFile<hr>";
		$nugget = thumbnailNugget($remoteFile);
//echo "(".($remoteFile ? 'remote' : 'local').") $remoteFile<br>[nugget=$nugget]";exit;
		
		globalRedirect("gallery1.php?nugget=$nugget");
		exit;
		//dumpPhotoNugget($nugget);
	}
	exit;
}

if($nugget) {
//echo "$nugget";exit;
	dumpPhotoNugget($nugget);
	exit;
}

// $id = $_SESSION["clientid"]; // get id from the REQUEST
$client = getClient($id);
$show = $show ? $show : 10;

$error = null;

if(!$returnVisitPhotos) {
	$photos = fetchAssociations(
		"SELECT name as caption, photo, petid 
			FROM tblpet
			WHERE ownerptr = $id AND active=1 AND photo IS NOT NULL");

	foreach($photos	as $i => $photo) {
		//$photos[$i]['photo'] = "pet-photo.php?id={$photo['petid']}";
		$photos[$i]['photo'] = publicPetPhotoURL($photo['petid']);
		$photos[$i]['thumb'] = "pet-photo.php?id={$photo['petid']}&version=display";
	}
}
	
if($returnPetPhotos) {
	if($photos) {
		//$result['totalphotos'] = count($visits);
		//$result['finishplusone'] = $finish + 1;
		$result['photos'] = $photos;
		echo json_encode($result);
	}
	else echo json_encode(array('nophotos'=>1));
	exit;
}

$visits = fetchAssociations(
	"SELECT appointmentptr, pets, date
		FROM tblappointmentprop
		LEFT JOIN tblappointment ON appointmentid = appointmentptr
		WHERE property = 'visitphotocacheid' AND clientptr = $id
		ORDER BY date desc", 1);
		
$allPets = getClientPetNames($id, $inactiveAlso=false, $englishList=true);
		
if($returnVisitPhotos) {
	// $returnVisitPhotos e.g., 21-5 => first index=21, return 5 photos
	list($start, $blocksize) = explode('-', $returnVisitPhotos);
	$finish = $start + $blocksize - 1;
	$finish = min($finish, count($visits)-1);
	for($i = $start; $i <= $finish; $i++) {
		$visit = $visits[$i];
		$apptid = $visit['appointmentptr'];
//if(!$apptid) {echo "($returnVisitPhotos) [$start => $finish, $i]\n";print_r($visits);exit;}

		$remoteFile = generateThumbnailRemoteFileForAppt($apptid, 'test', "either_300", $checkFirst=true);
		if(!$remoteFile) continue;
		$nugget = thumbnailNugget($remoteFile);
		
		$pets = $visit['pets'] == 'All Pets' ? $allPets : $visit['pets'];

		$shown += 1;
		$photos[] = array(
			'visitid'=>$visit['appointmentptr'],
			'caption'=>shortDate(strtotime($visit['date'])).' '.$pets,
			'photo'=>globalURL("gallery1.php?nugget=$nugget"),
			'fullsize'=>globalURL("appointment-photo.php?id={$visit['appointmentptr']}")
			//"appointment-photo.php?id={$visit['appointmentptr']}"
			);
	}
	if($photos) {
		//$result['totalphotos'] = count($visits);
		//$result['finishplusone'] = $finish + 1;
		if($finish + 1 < count($visits))
			$result = array('nextStart'=>($finish + 1));
		$result['photos'] = $photos;
		echo json_encode($result);
	}
	else echo json_encode(array('nophotos'=>1));
	exit;
}


$visitsShown = 0;
foreach($visits as $visit) {
	if($visitsShown >= $show) break;
	$apptid = $visit['appointmentptr'];
	$remoteFile = generateThumbnailRemoteFileForAppt($apptid, 'test', "either_300", $checkFirst=true);
	if(!$remoteFile) continue;
	$nugget = thumbnailNugget($remoteFile);

	$visitsShown += 1;
	
	$pets = $visit['pets'] == 'All Pets' ? $allPets : $visit['pets'];

	$photos[] = array(
		'id'=>"visit_{$visit['appointmentptr']}",
		'caption'=>shortDate(strtotime($visit['date'])).' '.$pets,
		'photo'=>globalURL("gallery1.php?nugget=$nugget"),
		'fullsize'=>globalURL("appointment-photo.php?id={$visit['appointmentptr']}")
		//"appointment-photo.php?id={$visit['appointmentptr']}"
		);
}

	/*$dims = fetchRow0Col0(
		"SELECT value 
			FROM tblappointmentprop 
			WHERE appointmentptr = $apptid
				AND property = 'photodims'
			LIMIT 1", 1);
//if(!$dims) {echo print_r($visit, 1);exit;}
			
	if(!$dims) {
		$filecacheid = getAppointmentProperty($apptid, 'visitphotocacheid');
		$localPath = getCachedFileAndUpdateExpiration($filecacheid);
		if($localPath) {
			$dims = getimagesize($localPath);
			$dims = "{$dims[0]},{$dims[1]}";
			insertTable('tblappointmentprop', array('appointmentptr' => $apptid, 'property'=>'photodims', 'value'=>$dims), 1);	
		}
	}
	list($width, $height) = explode(",", $dims);
	$pets = $visit['pets'] == 'All Pets' ? $allPets : $visit['pets'];
	$photos[] = array(
		'caption'=>shortDate(strtotime($visit['date'])).' '.$pets,
		'photo'=>"appointment-photo.php?id={$visit['appointmentptr']}",
		'width'=>$width,
		'height'=>$height
		);
	*/


if(!$photos) $message = 'There are no photos in your gallery yet.';

function scaledDown($width, $height) {
	if(!$width || !$height) return "";
	$maxDims = explode('x', '340x340');
	$maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
	$percent = $maxDim / max($width, $height);
	$newwidth = round($width * $percent);
	$newheight = round($height * $percent);
	$height = number_format($height);
	$width = number_format($width);
	return "height=$newheight width=$newwidth";
}

function findThumbnailRemoteFileNameForAppt($apptid, $subdir) {
	// find out if a visit photo thumbnail in the designated subdir exists
	// e.g.,
	// for visit 231467 belonging to client 45 we have photo
	// bizfiles/biz_3/photos/appts/45/231467.png
	// do we have the w200 (200px wide) version of it?
	// check for bizfiles/biz_3/photos/appts/45/w200/231467.png
	$filecacheid = getAppointmentProperty($apptid, 'visitphotocacheid');
	if(!$filecacheid) return null;
	$remotePath = fetchRow0Col0("SELECT remotepath FROM tblfilecache WHERE filecacheid = $filecacheid LIMIT 1", 1);
	if(!$remotePath) return null;
	$base = basename($remotePath);
	$dir = dirname($remotePath);
	$target = "$dir/$subdir/$base";
	static $credentials;
	$credentials = $credentials ? $credentials : getRemoteStorageCredentials();	
	
	$descr = remoteObjectDescription($target, $credentials);
	if(!$descr) return null;
	else return $target;
}

function generateThumbnailRemoteFileForAppt($apptid, $subdir, $maxDims, $checkFirst=false) {
	//$maxDims = "width_height" or array(width, height)
	if($checkFirst && ($fname = findThumbnailRemoteFileNameForAppt($apptid, $subdir)))
		return $fname;
	$filecacheid = getAppointmentProperty($apptid, 'visitphotocacheid');
	if(!$filecacheid) return null;
	$filecache = getCachedFileEntry($filecacheid);
	if(!$filecache || !($file = getCachedFileAndUpdateExpiration($filecache))) return null;
	$remotePath = $filecache['remotepath'];
	$dir = dirname($remotePath);
	// get image and scale it down
	$ext = substr($remotePath, strrpos($remotePath, '.'));
	$bizid = $_SESSION["bizptr"];
	if(file_exists($filecache['localpath'])) {
		$sourcePath = $filecache['localpath'];
		$sourceFound = true;
	}
	else {
		$tmpFile = "$dir/tmp_$apptid$ext";
		$sourcePath = $tmpFile;
		static $credentials;
		$credentials = $credentials ? $credentials : getRemoteStorageCredentials();	
		$sourceFound = restoreAWS($tmpFile, $remotePath, $credentials);
	}
	$destPath = "$dir/tmp2_$apptid$ext";
	
	if($sourceFound) {
//$targetSize = targetSize($sourcePath, explode('_', $maxDims)); echo "target size (".print_r($maxDims, 1)."): [".print_r($targetSize, 1)."]";exit;
		dumpResizedVersion($sourcePath, $destPath, $maxDims, $cacheResizedVersion=false, $rotation=0);
		// save the image
		$base = basename($remotePath);
		$dir = dirname($remotePath);
		$target = "$dir/$subdir/$base";
		//saveFileRemotely($destPath, $target, $credentials);
		//saveAWS($destPath, $target, $credentials, $contentType=null);
		saveAWS($destPath, $target, $credentials, $contentType=enhanced_mime_content_type($destPath));

//echo "<hr>".($tmpFile ? 'remote' : 'local')." $destPath > $target ".print_r($maxDims, 1)."   ".print_r($success, 1);exit;
	}
	if($tmpFile && file_exists($tmpFile)) unlink($tmpFile);
	if(file_exists($destPath)) unlink($destPath);
	return $target;
}
	
function thumbnailNugget($remoteFile) {
	require_once "encryption.php";
	return urlencode(lt_encrypt($remoteFile));
}

function publicPetPhotoURL($id) {
	require_once "encryption.php";
	$nugget = array('id'=>$id, 'bizid'=>$_SESSION['bizptr'], 'bizfiledirectory'=>$_SESSION['bizfiledirectory']);
	$nugget = lt_encrypt(json_encode($nugget));
	return globalURL('pet-photo-public.php?nugget='.urlencode($nugget));
}

function dumpPhotoNugget($nugget) {
	// decrypt a nugget, which will have been decoded already, and then dump the remote image
	require_once "encryption.php";
	$remoteFile = lt_decrypt($nugget);
//echo "nugget => $remoteFile<br>";exit;
	returnToBrowser($remoteFile, $download=false, $_SESSION['bizptr']);
	//dumpResizedVersion($remoteFile, $outName=null, $maxDims=null, $cacheResizedVersion=false, $rotation=0);
}
	

// ***************************************************************************

//$ssgHome = "responsiveclient/assets/js/libs/Story-Show-Gallery-master";

//$extraHeadContent .= "\n<link rel=\"stylesheet\" href=\"{$ssgHome}ssg.css\" />\n<script src=\"{$ssgHome}ssg.js\"></script>\n";


foreach($photos as $photo) {
	$caption = safeValue($photo['caption']);
	$thumDims = scaledDown($photo['width'], $photo['height']);
	$thumb = $photo['thumb'] ? $photo['thumb'] : $photo['photo'];
	$fullSize = $photo['fullsize'] ? $photo['fullsize'] : $photo['photo'];
	$anchors[] = 
		"<a id='{$photo['id']}' href='$fullSize' target='petphoto'><img src='$thumb' title='$caption' alt='$caption' $thumDims></a>";
}	

$GALLERY = "<section class=\"gallery\">".join("\n", $anchors)."</section>";

// ***************************************************************************

$breadcrumbs = fauxLink(fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $id LIMIT 1"),
												"document.location.href=\"client-edit.php?id=$id\"", 1, "Return to the client's profile.");

if($_SESSION["responsiveClient"]) {
	$extraHeadContent = "
	<style>
	body {font-size:1.2em;} 
	.leashtime-content {font-size:1.0em;}
	td {font-size:1.0em;}  /* 1.8 /
	input.Button {font-size:1.0em;} /* 2.8 /
	</style>";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else {
	include "frame-client.html";
	$frameEndURL = "frame-end.html";
}

if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	if(!$pop) include $frameEndURL;
	exit;
}

$spacer = "<img src='art/spacer.gif' width=1 height=145>";

echo $GALLERY;

if($visitsShown) {
	//echo "<p><input type='button' id='' name='' value='Show More' class='btn btn-success pull-right btn-raised ' onclick='document.location.href=\"gallery1.php?show=$show\"'>";
	echo "<p><input type='button' id='showMoreButton' name='' origvalue='Show More' value='Show More' class='btn btn-success pull-right btn-raised ' onclick='fetchMore()'>";
}


?>

<script language='javascript'>
var visitsShown = <?= $visitsShown ?>;
var show = <?= $show ?>;
<? //echo "/*".count($visits)." = ".$visitsShown."\n".print_r($visits, 1)."\n"; ?>
<? if(count($visits) <= $visitsShown) echo "$('#showMoreButton').toggle();"; ?>
<? //echo "*/" ?> 
function fetchMore() {
	$('#showMoreButton').val('Please wait...');
	$('#showMoreButton').attr('disabled', true);
	$.ajax({
			url: 'gallery-office.php?<?= "id=$id" ?>&returnVisitPhotos='+visitsShown+"-"+show,
			dataType: 'json', // comment this out to see script errors in the console
			type: 'get',
			//contentType: 'application/json',
			//data: JSON.stringify(data),
			processData: false,
			success: showMore,
			error: <?= mattOnlyTEST() ? 'submitFailed' : 'showMore' // until I figure this out...Figured it out! ?>
			});
}
function showMore(data, textStatus, jQxhr) {
//alert(data);data = JSON.parse(data);
	$('#showMoreButton').val($('#showMoreButton').attr('origvalue'));
	$('#showMoreButton').attr('disabled', false);
	if(typeof data.nextStart == 'undefined') 
		$('#showMoreButton').toggle()
	if(data.nophotos) {
		alert('No more visit photos found.');
		return;
	}
	visitsShown = data.nextStart;

	if(typeof data.photos == 'undefined') alert('problem encountered.');
	else data.photos.forEach(function(photo, i, arr) {
		/* photo = array(
			'visitid'=>$visit['appointmentptr'],
			'caption'=>shortDate(strtotime($visit['date'])).' '.$pets,
			'photo'=>globalURL("gallery1.php?nugget=$nugget"),
			'fullsize'=>globalURL("appointment-photo.php?id={$visit['appointmentptr']}")
			//"appointment-photo.php?id={$visit['appointmentptr']}"
			)
		*/
		$('.gallery').append(
					"<a id='visit_"+photo.visitid+"' href='"+photo.fullsize+"' target='petphoto'><img src='"+photo.photo+"' title='"+photo.caption+"' alt='"+photo.caption+"'></a>"
			);
	});
}
function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
	$('#working').toggle();

}

</script>
<?
// ***************************************************************************
if(!$pop) include $frameEndURL;

