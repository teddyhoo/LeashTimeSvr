<? //native-app-filespace-status.php

// list all active bizzes in the following order:
// that currently use the Native sitter app but are NOT set up to outboard photos
// order all the rest by descending file usage

set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";


// exit;



$locked = locked('z-');

if($_GET['dumplocalcopiesforinactivebiz']) {
	$bizid = $_GET['dumplocalcopiesforinactivebiz'];
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid and activebiz=0", 1);
	if(!$biz) $error = "Biz $bizid not found";
	if(!$error) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
		require_once "remote-file-storage-fns.php";
		if(!remoteCacheAvailable()) { echo "{$_SESSION['preferences']['bizName']} is not yet outboarded."; exit; }
//echo $db.print_r(fetchCol0("SELECT localPath FROM tblfilecache"),1);
//foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {echo "$f: ".(file_exists($f) ? filesize($f) : 0).'<br>';}
//foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {echo "[$f] exists: ".print_r(file_exists($f),1).'<br>';}
//if(fetchRow0Col0("SELECT localPath FROM tblfilecache")) $d = print_r(glob(dirname(fetchRow0Col0("SELECT localPath FROM tblfilecache")).'/*'));
		foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {$orginalFileSize += (file_exists($f) ? filesize($f) : 0);}
		checkCacheLimits(0);
		foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {$finalFileSize += (file_exists($f) ? filesize($f) : 0);}
		$bytes = $orginalFileSize  - $finalFileSize;
  	$sz = 'BKMGTP';
  	$factor = floor((strlen($bytes) - 1) / 3);
  	echo sprintf("%.2f", $bytes / pow(1024, $factor)) . @$sz[$factor]." storage reclaimed. [$orginalFileSize  - $finalFileSize]";
	}
	else echo $error;
	exit;
}
	
if($_GET['dumpLocalCopiesForInactiveClientsforALLbusinesses']) {
	require_once "remote-file-storage-fns.php";
	foreach(fetchAssociations("SELECT * FROM tblpetbiz") as $biz) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
		if(mysql_error()) continue;
		if(!remoteCacheAvailable()) { echo "{$_SESSION['preferences']['bizName']} is not yet outboarded."; exit; }
//echo $db.print_r(fetchCol0("SELECT localPath FROM tblfilecache"),1);
//foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {echo "$f: ".(file_exists($f) ? filesize($f) : 0).'<br>';}
//foreach(fetchCol0("SELECT localPath FROM tblfilecache") as $f) {echo "[$f] exists: ".print_r(file_exists($f),1).'<br>';}
//if(fetchRow0Col0("SELECT localPath FROM tblfilecache")) $d = print_r(glob(dirname(fetchRow0Col0("SELECT localPath FROM tblfilecache")).'/*'));
		// check each outboarded file.  if it existslocally, check the owner.  if owner is inactive, drop local copy.
echo "<hr>[$db]<br>";
$count += 1;
		foreach(fetchAssociations("SELECT localPath,existslocally,existsremotely,remotepath,filecacheid FROM tblfilecache") as $f) {
			if($f['existslocally']) {
//echo print_r($f,1)."<br>";
				$fname = $f['localPath'];
//echo print_r($fname,1)."<br>";
				$delete = false;
				if($start = strpos($fname, ($pattern = '/photos/appts/'))) {
					$parts = explode('/', $fname); // bizfiles/biz_3/photos/appts/484/175088.png
					$client = fetchFirstAssoc("SELECT clientid, active, lname FROM tblclient WHERE clientid = {$parts[4]}");
					if(!$client) {
						$appt = $parts[5];
						echo "No client {$parts[4]} found for $fname in $db<br>";
					}
					else if($client && !$client['active']) $delete = $fname;
				}
				else if($start = strpos($fname, ($pattern = '/photos/pets/fromClient/'))) {
					$parts = explode('/', $fname); // bizfiles/biz_3/photos/pets/fromClient/3489_9.jpg
					if($end = strpos($parts[5], '_')) $requestid = substr($parts[5], 0, $end);
					else if($end = strpos($parts[5], '.')) $requestid = substr($parts[5], 0, $end);
					if(!$requestid) echo "No request id found for $fname in $db<br>";
					else $client = fetchFirstAssoc(
						"SELECT clientid, tblclient.active, requesttype, tblclient.lname 
							FROM tblclientrequest 
							LEFT JOIN tblclient ON clientid = clientptr
							WHERE requestid = $requestid");
					if(!$client) {
						echo "No client found for $fname (requestid: #$requestid {$client['requesttype']}) in $db<br>";
					}
					//else if($client && $client['active']) echo "Found request for $fname (requestid: #$requestid {$client['requesttype']}) in $db<br>";
					else if($client && !$client['active']) $delete = $fname;
				}
				else if($start = strpos($fname, ($pattern = '/photos/pets/'))) {
					$parts = explode('/', $fname); // bizfiles/biz_3/photos/pets/3489_9.jpg
					if($end = strpos($parts[4], '_')) $petid = substr($parts[4], 0, $end);
					else if($end = strpos($parts[4], '.')) $petid = substr($parts[4], 0, $end);
					if(!$petid) echo "No pet id found for $fname in $db<br>";
					else $client = fetchFirstAssoc(
						"SELECT clientid, tblclient.active, name, lname 
							FROM tblpet 
							LEFT JOIN tblclient ON clientid = ownerptr
							WHERE petid = $petid");
					if(!$client) {
						echo "No client found for $fname (pet: #$petid {$client['name']}) in $db<br>";
					}
					else if($client && !$client['active']) $delete = $fname;
				}
				else if($start = strpos($fname, ($pattern = 'BOINKBOINK/photos/pets/fromClient/'))) {
					$parts = explode('/', $fname); // bizfiles/biz_3/photos/pets/fromClient/3489_9.jpg
					if($end = strpos($parts[5], '_')) $apptid = substr($parts[5], 0, $end);
					else if($end = strpos($parts[5], '.')) $apptid = substr($parts[5], 0, $end);
					if(!$apptid) echo "No appt id found for $fname in $db<br>";
					$client = fetchFirstAssoc(
						"SELECT clientid, active, lname 
							FROM tblappointment 
							LEFT JOIN tblclient ON clientid = clientptr
							WHERE appointmentid = $apptid");
					if(!$client) {
						$appt = $parts[5];
						echo "No client found for $fname (appt: $apptid)in $db<br>";
					}
					else if($client && !$client['active']) $delete = $fname;
				}
				if($delete) {
					if(!file_exists($delete)) {
						echo "$delete not found to delete ($db)<br>";
						$delete = null;
					}
					else {
						if(!$f['existsremotely']) {
							$reallynotthere = checkAWSError($f['remotepath']) ? "[CONFIRMED: {$f['remotepath']}]" : '';
							if($reallynotthere) {
								// try to outboard it
								if(saveCachedFileRemotely($f['filecacheid'])) {
									echo "STORED $fname remotely ($db)<br>";
									updateTable('tblfilecache', array('existsremotely'=>1), "filecacheid = '{$f['filecacheid']}'", 1);
								}
								else {
									echo "FAILED TO STORE $fname remotely ($db)<br>";
									$delete = null;
								}
							}
							else {
								// correct the record
								updateTable('tblfilecache', array('existsremotely'=>1), "filecacheid = '{$f['filecacheid']}'", 1);
								echo "Corrected existsremotely for $fname ($db)<br>";
							}
						}
						if($delete) {
							$sz = filesize($fname);
							$totalSize += $sz;
							$sz = round($sz/1024);
							
							
							$descr['inactivefullsizetotal'] += round(filesize($photo)/1024);
							if($_GET['delete']) {
								updateTable('tblfilecache', array('existslocally'=>1), "filecacheid = '{$f['filecacheid']}'", 1);
								unlink($delete);
								echo "DELETED $fname ($sz K) for inactive client @{$client['clientid']} {$client['lname']} ($db)<br>";
							}
							else echo "Will delete $fname ($sz K) for inactive client @{$client['clientid']} {$client['lname']} ($db)<br>";
						}
					}
				}
			}
		}
	}
	echo "<hr>Total deletion: ".round($totalSize / 1024 / 1024)." MB";
	exit;
}
	
if($_GET['bizid']) {
	$bizid = $_GET['bizid'];
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid");
	if(!$biz) $error = "Biz $bizid not found";
	if(!$error) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		echo "Zapping local cache of {$biz['bizname']} ({$biz['db']})\n";
		require_once "remote-file-storage-fns.php";
		$result = '\nNo action taken';
		foreach(fetchAssociations("SELECT * FROM tblfilecache") as $file) {
			if($file['filecacheid'] /*&& file_exists($file['localpath'])*/) {
				$result = '';
				if($file['existsremotely']) {
					// dump the local copy
					unlink($file['localpath']);
					$cachedFile = array('existslocally'=>0);
					updateTable('tblfilecache', $cachedFile, "filecacheid = '{$file['filecacheid']}'", 1);
					echo "\nDropped local copy of {$file['localpath']}";
				}
				else {
					// outboard it and then dump it
					$cachedFile = array('existslocally'=>0);
					$cachedFile['existsremotely'] = saveCachedFileRemotely($file['filecacheid']) ?  '1' : '0';
					if($cachedFile['existsremotely']) {
						unlink($file['localpath']);
						updateTable('tblfilecache', $cachedFile, "filecacheid = '{$file['filecacheid']}'", 1);
						echo "\nOutboarded {$file['localpath']}";
					}
					else echo "\nFailed to outboard {$file['localpath']}";
				}
			}
		}
	}
	echo $error ? $error : $result;
	exit;
}


$scriptStart = microtime(1);
$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz ", 'db'); // WHERE activebiz=1
foreach($bizzes as $biz) {
	if($biz['db'] == 'leashtimecustomers') $ltBiz = $biz;
	else $allBizzesLeashTimeFirst[] = $biz;
}

// GOLD STARS
$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
foreach($clients as $ltclientid => $garagegatecode) {
	$goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
	if(!$goldstar) unset($clients[$ltclientid]);
}
$goldstars = $clients; // ltclientid => bizid

// FORMER CLIENTS greystar(21), deadlead(8)
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
foreach($clients as $ltclientid => $garagegatecode) {
	$former = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' 
													AND (value like '8|%' OR value like '21|%')");
	if(!$former) unset($clients[$ltclientid]);
}
$formerclients = $clients; // ltclientid => bizid

require "common/init_db_common.php";
// END GOLD STARS



function cmpDb($a, $b) {return strcmp($a['db'], $b['db']);}

usort($allBizzesLeashTimeFirst, 'cmpDb');

$allBizzesLeashTimeFirst = array_merge(array('leashtimecustomers'=>$ltBiz), $allBizzesLeashTimeFirst);

foreach($allBizzesLeashTimeFirst as $bizCount => $biz) {
	//echo "<font color=gray>$bizCount / ".(count($allBizzesLeashTimeFirst)-2)."</font><br>";
	if($bizCount == count($allBizzesLeashTimeFirst)-2) $lastBiz = true;  // why "2"?
	if(!in_array($biz['db'], $databases)) {
		//echo "<br><font color=gray>DB: {$biz['db']} not found.<br></font>";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysql_select_db($db);
	if(mysql_error()) echo mysql_error();
	$tables = fetchCol0("SHOW TABLES");
	$prefs = fetchKeyValuePairs("SELECT value FROM tblpreference");
	$bizName = $baseBizName = $prefs['bizName'] ? $prefs['bizName'] : $db;
	
	$bizName = $biz['activebiz'] ? $bizName : "$bizName ({$biz['bizid']})[inactive]";
	$descr = array('bizName'=>$bizName, 'db'=>$db);
	$descr['outboardsPhotos'] = in_array('tblfilecache', $tables);
	$descr['outboardsPhotos'] = $descr['outboardsPhotos'] ? 'OUTBOARDS' : '';
	$descr['outboardLocalCount'] = $descr['outboardsPhotos'] ? fetchRow0Col0("SELECT COUNT(*) FROM tblfilecache WHERE existslocally = 1") : 0;
	if(!$biz['activebiz']) {
		if(!$descr['outboardsPhotos']) $inactiveUnboardedCount += 1;
		$inactiveCount += 1;
		if($descr['outboardLocalCount'])
			$descr['bizName'] = $baseBizName.' '.fauxLink('[inactive]', "zapInactive({$biz['bizid']})", 1, "ZAP all cached files ({$descr['outboardLocalCount']})");
	}
	if(!$biz['activebiz'] &&	in_array('tblfilecache', $tables)) {
		$fullSizePhotosForClients = fetchCol0(
			"SELECT photo, clientid, tblclient.active
				FROM tblpet
				LEFT JOIN tblclient ON clientid = ownerptr
				WHERE photo IS NOT NULL");
		$descr['inactivefullsizetotal'] = 0;
		foreach($fullSizePhotosForClients as $photo) {
			if(file_exists($photo)) {
				$descr['inactivefullsizetotal'] += round(filesize($photo)/1024);
			}
//if($biz['bizid'] == 484)echo "[business ({$biz['bizid']} {$biz['db']}) {$biz['bizname']}] $photo: ".round(filesize($photo)/1024).' bytes<br>';
			}
	}
	if($descr['inactivefullsizetotal']) $descr['bizName'] .= " <a target='zaplocal' href='native-app-filespace-status.php?dumplocalcopiesforinactivebiz=$bizptr'>Zap local copies</a>";

	// find if ANY appointmentprops have 'button_', or photos
	$descr['usesNativeApp'] = fetchCol0("SELECT appointmentptr FROM tblappointmentprop WHERE property = 'visitphotocacheid' OR property LIKE 'button_%'");
	$descr['usesNativeApp'] = $descr['usesNativeApp'] ? 'NATIVE' : '';
	if($descr['usesNativeApp']) $nativeAppCount += 1;
	
	if($descr['usesNativeApp'] && !$descr['outboardsPhotos']) $descr['firstGroup'] = 1;
	if(!$descr['outboardsPhotos']) $noOutboard += 1;

	$sql = 
	"SELECT (data_length+index_length)/power(1024,1) tablesize
		FROM information_schema.tables
		WHERE table_schema='$db' and table_name='tblgeotrack';";
	
	$photosForInactiveClients = fetchCol0(
		"SELECT photo, clientid, tblclient.active
			FROM tblpet
			LEFT JOIN tblclient ON clientid = ownerptr
			WHERE photo IS NOT NULL AND tblclient.active != 1");
	$f = "bizfiles/biz_$bizptr/photos/pets";
	if($photosForInactiveClients) {
		$descr['inactivetotal'] = 0;
		$descr['inactivedisplay'] = 0;
		foreach($photosForInactiveClients as $photo) {
			if(file_exists($photo)) {
				$descr['fullsizelocalinactivecount'] += 1;			
				$descr['inactivetotal'] += round(filesize($photo)/1024);
			}
			$displayName = "$f/display/".basename($photo);
			if(file_exists($displayName)) $descr['inactivedisplay'] += round(filesize($displayName)/1024);
		}
	}
	$photosForActiveClients = fetchCol0(
		"SELECT photo, clientid, tblclient.active
			FROM tblpet
			LEFT JOIN tblclient ON clientid = ownerptr
			WHERE photo IS NOT NULL AND tblclient.active = 1");
	if($photosForActiveClients) {
		$descr['activetotal'] = 0;
		foreach($photosForActiveClients as $photo) {
			if(file_exists($photo)) {
				$descr['fullsizelocalactivecount'] += 1;			
				$descr['activetotal'] += round(filesize($photo)/1024);
			}
			$displayName = "$f/display/".basename($photo);
			if(file_exists($displayName)) $descr['activedisplay'] += round(filesize($displayName)/1024);
		}
	}
	$descr['photototal'] = $descr['activetotal'] + $descr['inactivetotal'] + $descr['inactivedisplay'] + $descr['activedisplay'];
	$descr['savingpct'] = !$descr['inactivetotal'] ? '' : number_format(round(($descr['inactivetotal'] - $descr['inactivedisplay']) / $descr['inactivetotal']*100), 0).'%';
	$descr['saving'] = !$descr['inactivedisplay'] ? '' : $descr['inactivetotal'] - $descr['inactivedisplay'];
	
	/*if(!$descr['photototal']) {
		require_once "remote-file-storage-fns.php";
		define_tblfilecache();
	}*/
	$descr['geotracks'] = (int)fetchRow0Col0($sql);
	
	$aggregate['geotracks'] += $descr['geotracks'];
	$aggregate['bizcount'] += 1;
	$aggregate['photototal'] += $descr['photototal'];
	$aggregate['inactivetotal'] += $descr['inactivetotal'];
	
	if($descr['fullsizelocalinactivecount'] || $descr['fullsizelocalactivecount']) {
		$descr['totalcount'] = (int)$descr['fullsizelocalinactivecount'] + (int)$descr['fullsizelocalactivecount'];
		$aggregate['totalcount'] += $count;
	}

	$dbs[] = $descr;
//print_r($descr);echo "<BR>";;	
}
$aggregate['bizName'] = "Number of bizzes: ".$aggregate['bizcount'];

function dbsort($a, $b) {
	$res = 0-strcmp($a['firstGroup'], $b['firstGroup']);
	if(!$res) $res = strcmp($a['outboardsPhotos'], $b['outboardsPhotos']);
	//if(!$res) $res = 0-intcmp($a['saving'], $b['saving']);
	
	// inactivefullsizetotal  
	if(!$res)
			$res = $a['inactivefullsizetotal'] == $b['inactivefullsizetotal'] ? 0 : (
							$a['inactivefullsizetotal'] < $b['inactivefullsizetotal'] ? 1 : -1);
	
	if(!$res && $_REQUEST['inactive'])
			$res = $a['inactivetotal'] == $b['inactivetotal'] ? 0 : (
							$a['inactivetotal'] < $b['inactivetotal'] ? 1 : -1);
	
	if(!$res)
			$res = $a['photototal'] == $b['photototal'] ? 0 : (
							$a['photototal'] < $b['photototal'] ? 1 : -1);
	return $res;
}
usort($dbs, 'dbsort');

$dbs = array_merge(array($aggregate), $dbs);

function intcmp($a, $b) {
	$a = (int)$a; $b = (int)$b;
	return $a == $b ? 0 : ($a < $b ? -1 : 1);
}
	
require "frame-bannerless.php";

if($noOutboard) echo "$noOutboard businesses have not been set up to outboard photos.  $inactiveUnboardedCount of them are inactive.<p>";
echo "$inactiveCount businesses are inactive.<p>";

echo "$nativeAppCount businesses use the native app.<p>";
$columns = explodePairsLine('bizName|Biz||outboardsPhotos|Outboarded||usesNativeApp|Native App||photototal|Pet Photos (K)||totalcount|#||inactivefullsizetotal|Full Size (Inactive only)||inactivetotal|Inactive Total|inactivedisplay|Inactive D||saving|Savings||savingpct|Savings %||geotracks|Geotracks(K)');
unset($columns['inactivefullsizetotal']);
tableFrom($columns, $dbs, $attributes='border=1');

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function zapInactive(bizid) {
	if(!confirm("Sure you want to zap the whole file cache?")) return;
	ajaxGetAndCallWith('native-app-filespace-status.php?bizid='+bizid, afterzap, bizid);
}
function afterzap(bizid, response) {
	alert(response);
}
</script>