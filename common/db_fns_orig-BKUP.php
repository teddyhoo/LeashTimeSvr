<? // common/db_fns_orig.php

function updateStamp() {
  $datetime = date('Y-m-d H:i:s');
  return ", LastUpdateDate='$datetime', UserUpdatePtr=".(0+$_SESSION["auth_user_id"]);
}

function creationStamp() {
  $datetime = date('Y-m-d H:i:s');
  return ", CreationDate='$datetime', LastUpdateDate='$datetime', UserUpdatePtr={$_SESSION["auth_user_id"]}";
}
// creationCols() creationVals() updateCols() updateVals() creationStamp() updateStamp()
function creationCols() {
  return ", CreationDate, LastUpdateDate, UserUpdatePtr";
}

function creationVals() {
  $datetime = date('Y-m-d H:i:s');
  return ", '$datetime', '$datetime', {$_SESSION["auth_user_id"]}";
}

function updateCols() {
  return ", LastUpdateDate, UserUpdatePtr";
}

function updateVals() {
  $datetime = date('Y-m-d H:i:s');
  return ",'$datetime', {$_SESSION["auth_user_id"]}";
}

function doQuery($sql, $showErrors=1) {
	if(strpos(strtoupper($sql), 'SLEEP(')) return array(); // block against a specific injection attack
	if(strpos(strtoupper($sql), 'UNION ALL')) return array(); // block against a specific injection attack
  $result = mysqli_query ($sql);
  if(!$result) {
    if(mysqli_error() && $showErrors) showSQLError($sql);
    return null;
  }
  return $result;
}

function sqlErrorCode($target=null) {
	return mysqli_errno();
}

function sqlErrorMessage($target=null) {
	return mysqli_error();
}


function showSQLError($sql) {
	global $db;
	if($_SESSION['displayErrorsLoginSetting'])
		echo "SQL: $sql<p><b>ERROR (".mysqli_errno().")</b> ".mysqli_error();
	else {
		echo "Sorry, an error has occurred. [$db] [{$_SESSION['auth_user_id']}] ".date('m/d H:i:s');
	}
	if(!in_array(mysqli_errno(), array(1045)) && (TRUE || mattOnlyTEST())) {
		$textbagid = bagText($_SESSION['auth_user_id'].'|'.mysqli_error().'|'.$sql, $referringtable='tblerrorlog');
		logError($_SESSION['auth_user_id']."|tbag:$textbagid");
	}
	else logError($_SESSION['auth_user_id'].'|'.mysqli_error().'|'.$sql);
	exit;
}

function bagText($body, $referringtable=null) {
	return insertTable('tbltextbag', array('body'=>$body, 'referringtable'=>$referringtable), 1);
}

function fetchFirstAssoc($sql) {
  if(!($result = doQuery($sql))) return null;
  return mysqli_fetch_array($result, MYSQL_ASSOC);
}

function fetchResultAssoc($result) {
  return mysqli_fetch_array($result, MYSQL_ASSOC);
}

function resetResult($result) {
  return mysqli_data_seek($result, 0);
}

function fetchCol0($sql) {
  return fetchColN($sql, 0);
}

function fetchColN($sql, $n) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_row($result))
   $assocs[] = $row[$n];
  return $assocs;
}

function fetchRow0Col0($sql) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_row($result))
   return $row[0];
  return null;
}

function fetchAssociations($sql) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC))
   $assocs[] = $row;
  return $assocs;
}

function fetchKeyValuePairs($sql) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_array($result, MYSQL_NUM))
    $assocs[$row[0]] = $row[1];
  return $assocs;
}

function fetchAssociationsKeyedBy($sql, $keyField) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC))
    $assocs[$row[$keyField]] = $row;
  return $assocs;
}

function fetchAssociationsIntoHierarchy($sql, $keyFieldArray) { // Experimental.  See categorize, below
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC))
  	categorize($assocs, $keyFieldArray, $row);
  return $assocs;
}

function categorize(&$storage, $indexArray, $association, $depth=0) {
/*
$arr = array();
$indexArray = array('kingdom', 'phylum', 'class', 'order');
$pettypes = array(
	array('label'=>'gerbil', 'kingdom'=>'Animalia', 'phylum'=>'Chordata', 'class'=>'Mammailia', 'order'=>'Rodentia'),
	array('label'=>'bison', 'kingdom'=>'Animalia', 'phylum'=>'Chordata', 'class'=>'Mammailia', 'order'=>'Artiodactyla'),
	array('label'=>'canary', 'kingdom'=>'Animalia', 'phylum'=>'Chordata', 'class'=>'Aves', 'order'=>'Passeriformes')
	);
foreach($pettypes as $type) categorize($arr, $indexArray, $type);
*/
	if(count($indexArray) == $depth+1) $storage[$association[$indexArray[$depth]]][] = $association;
	else {
		if(!isset($storage[$association[$indexArray[$depth]]])) $storage[$association[$indexArray[$depth]]] = array();
		categorize($storage[$association[$indexArray[$depth]]], $indexArray, $association, $depth+1);
	}
}

function fetchAssociationsGroupedBy($sql, $keyField) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC))
    $assocs[$row[$keyField]][] = $row;
  return $assocs;
}

function fetchRows($sql) {
  if(!($result = doQuery($sql))) return null;
  $rows = array();
  while($row = mysqli_fetch_array($result, MYSQL_NUM))
   $rows[] = $row;
  return $rows;
}

function fetchObjects($sql) {
  if(!($result = doQuery($sql))) return null;
  $assocs = array();
  while($row = mysqli_fetch_object($result))
   $assocs[] = $row;
  return $assocs;
}

function leashtime_next_row(&$result) {
	return mysqli_fetch_array($result, MYSQL_NUM);
}

function leashtime_next_assoc(&$result) {
	return mysqli_fetch_array($result, MYSQL_ASSOC);
}

function leashtime_real_escape_string($s) { // adapter for mysql --> PDO switch (mysqli_real_escape_string)
	return mysqli_real_escape_string($s);
}

function leashtime_affected_rows() {
	return mysqli_affected_rows();
}

function leashtime_num_rows($result=null) {
	return $result ? mysqli_num_rows($result) : mysqli_num_rows();
}

function kval($art_array, $key) {
  if(array_key_exists($key, $art_array)) return "'".mysqli_real_escape_string(stripslashes($art_array[$key]))."'";
  return "NULL";
}

function val($val) {
  if(is_int($val)) return $val;
  if($val != null) {
   return "'".mysqli_real_escape_string(stripslashes($val))."'";
  }
  return "NULL";
}

function noNullVal($val) {
  if($val !== null) {
   if(is_int($val)) return $val;
   return "'".mysqli_real_escape_string(stripslashes($val))."'";
  }
  return "''";
}

function updateTable($table, $data, $where, $showErrors=0) {
  $sql = "UPDATE $table SET ";
  $first = true;
  foreach($data as $key => $val) {
		//if($val && is_string($val) && $val[0] == '[' && substr($val, -1) == ']')
		//  $val = substr($val, 1, -1);
		if(isSqlVal($val)) $val = unpackSqlVal($val);
		else $val = val($val);
    $sql .= ($first ? '' : ', ')."$key=".$val;
    $first = false;
	}
	$sql .= " WHERE $where";
	return doQuery($sql, $showErrors); 
}

function sqlVal($str) {
	return array($str);
}

function isSqlVal($val) {
	return $val && is_array($val);
}

function unpackSqlVal($val) {
	return $val[0];
}

function isBracketedBy($str, $left, $right) {
	return strpos((string)$str, $left) === 0
					&& (strrpos((string)$str, $right)+strlen($right) == strlen((string)$str));
}					

function deleteTable($table, $where, $showErrors=0) {
  $sql = "DELETE FROM $table WHERE $where";
	return doQuery($sql, $showErrors); 
}

function insertTable($table, $data, $showErrors=0) {
  foreach($data as $key => $val) {
		if(isSqlVal($val)) $data[$key] = unpackSqlVal($val);
		else $data[$key] = val($val);
	}
  $sql = "INSERT INTO $table  (".join(', ',array_keys($data)).") VALUES (";
  //$sql .= join(', ',array_map('val',$data)).")";
  $sql .= join(', ', $data).")";
  if(doQuery($sql, $showErrors)) return mysqli_insert_id(); 
}

function insertTableMultipleRows($table, $rows, $showErrors=0) {
  $sql = "INSERT INTO $table  (".join(', ',array_keys($rows[0])).") VALUES (";
  foreach($rows as $row) $data[] = join(', ',array_map('val',$row));
  $sql .= join(', ', $data).")";
  if(doQuery($sql, $showErrors)) return mysqli_insert_id(); 
}

function replaceTable($table, $data, $showErrors=0) {
  foreach($data as $key => $val) {
		if(isSqlVal($val)) $data[$key] = unpackSqlVal($val);
		else $data[$key] = val($val);
	}	
  $sql = "REPLACE INTO $table  (".join(', ',array_keys($data)).") VALUES (";
  $sql .= join(', ', $data).")";  
  if(doQuery($sql, $showErrors)) 
  	return mysqli_insert_id() ? mysqli_insert_id() 
  					: mysqli_affected_rows(); 
}

function tableExists($table) {
	return in_array($table, fetchCol0("SHOW TABLES"));
}

//  LOGGING FUNCTIONS
/*
ALTER TABLE tblappointment ADD COLUMN
  (`created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL);
  
ALTER TABLE tblservice ADD COLUMN
  (`created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL);
  
ALTER TABLE tblservicepackage ADD COLUMN
  (`created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL);
  
ALTER TABLE tblrecurringpackage ADD COLUMN
  (`created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL);
  
CREATE TABLE IF NOT EXISTS `tblchangelog` (
  `itemtable` varchar(30) NOT NULL,
  `itemptr` int(11) NOT NULL,
  `operation` varchar(1) NOT NULL COMMENT 'c(reate),m(od),d(el)',
  `user` int(11) NOT NULL,
  `time` datetime NOT NULL,
  `note` varchar(60) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

  
*/

function addCreationFields(&$arr) {
	$arr['created'] = date('Y-m-d H:i:s');
	$user = isset($_SESSION) ? $_SESSION["auth_user_id"] : 0;
	$arr['createdby'] = $user;
	return $arr;
}

function addModificationFields(&$arr) {
	$arr['modified'] = date('Y-m-d H:i:s');
	$user = isset($_SESSION) ? $_SESSION["auth_user_id"] : 0;
	$arr['modifiedby'] = $user;
	return $arr;
}

function withModificationFields($arr) {
	addModificationFields($arr);
	return $arr;
}

function logChange($itemptr, $itemtable, $operation, $note='') {
	$arr['time'] = date('Y-m-d H:i:s');
	$user = isset($_SESSION) ? $_SESSION["auth_user_id"] : 0;
	if(!$user) $user = 0;  // not sure why this is necessary: kept getting field 'user' may not be null
	insertTable('tblchangelog', array('itemptr'=>$itemptr, 'itemtable'=>$itemtable, 
							'time'=>date('Y-m-d H:i:s'),
							'operation'=>$operation, 'note'=>$note, 'user'=>$user), 1);
}

function changeOperationLabel($change) {
	$ops = array(
		'm'=>'modified',
		'c'=>'created',
		'a'=>'activated',
		'd'=>'deleted',
		'f'=>'failed',
		'p'=>'epayment',
		'i'=>'impersonation',
		'e'=>'end impersonation',
		'b'=>'branch logout',
		'g'=>'google calendar export',
		'k'=>'session killed',
		'e'=>'event note');
	return $ops[$change['operation']];
}

function bagScreenLog($tableOrNote) {
	global $screenLog;
	if(!mattOnlyTEST()) { return; }
	bagText($screenLog, $tableOrNote);
}

function screenLog($str) {
	global $screenLog;
	if(!mattOnlyTEST()) { return; }
	$screenLog .= $str.'<p>';
	//logError($str);
}

function screenLogPageTime($str) {
	global $page_start_time;
	if(!$page_start_time) pageTimeOn();
	screenLog($str.': '.((microtime(1) - $page_start_time)*1000).' ms<p>');
}

function pageTimeOn() {
	global $page_start_time;
	$page_start_time = microtime(1);
}

function pageTimeOff() {
	global $page_start_time;
	if($page_start_time)
		screenLog('Page creation time: '.((microtime(1) - $page_start_time)*1000).' ms');
}

function logError($message) {
	insertTable('tblerrorlog', array('time'=>date('Y-m-d H:i:s'), 'message'=>$message));
}

function logLongError($message) {
	$n = 1;
	if(strlen($message) > 255) $message = "[".sprintf('%03d', $n)."] $message";
	while(strlen($message)) {
		$chunk = substr($message, 0, min(255, strlen($message)));
		insertTable('tblerrorlog', array('time'=>date('Y-m-d H:i:s'), 'message'=>$chunk));
		if(strlen($chunk) < strlen($message))
			$message = "[".sprintf('%03d', ++$n)."]...".substr($message, strlen($chunk));
		else $message = "";
	}
}

function getSettings($key) {
	$installations = parse_ini_file('/var/security/.installations', true);
//echo "BING! ".print_r(file('/var/security/.installations'),1)."<p>";
//echo "BANG! ".print_r($installations, 1)."<p>";
	
	return $installations[$key];
}
	
	
function globalRedirect($url) {  // no slash before url
	session_write_close();
	header("Location: ".globalURL($url));
}
	
function globalURL($url) {  // no slash before url
	global $baseURL;
	$settings = ensureInstallationSettings();
	if($settings) {
		$baseURL = $settings['baseURL'];
		if($_SESSION) $_SESSION['baseURL'] = $baseURL;
	}
	return "$baseURL/$url";
}

function ensureInstallationSettings($forParent=false) {
	global $installationSettings;
	if(!$installationSettings) {
		refreshInstallationSettings($forParent);
//echo 	">>> SETTINGS: [$scriptFileDirectory] ".print_r($installationSettings,1)."<p>";
	}
	return $installationSettings;
}

function refreshInstallationSettings($forParent=false) {
	global $installationSettings;
	$installationSettings = fetchInstallationSettings($forParent);
}

function fetchInstallationSettings($forParent=false) {
	// global $installationSettings;
	$scriptFileDirectory = dirname($_SERVER['SCRIPT_FILENAME']);
	if($forParent) $scriptFileDirectory = dirname($scriptFileDirectory);
	$scriptFileDirectory = $scriptFileDirectory ? $scriptFileDirectory  : $_ENV["PWD"];
	return  getSettings($scriptFileDirectory);
}

function ensureInstallationSettingsWithThisDirectory($scriptFileDirectory) {
	global $installationSettings;
	if(!$installationSettings) {
		$installationSettings = getSettings($scriptFileDirectory);
//echo 	">>> SETTINGS: [$scriptFileDirectory] ".print_r($installationSettings,1)."<p>";
	}
	return $installationSettings;
}

if(!function_exists('reconnectPetBizDB')) {

function reconnectPetBizDB($dbN=null, $dbhostN=null, $dbuserN=null, $dbpassN=null, $force=false) {
	global $dbhost, $dbuser, $dbpass, $db, $lnk;
	
	if($lnk) { // avoid switching if possible
		$currentSettings = array($dbhost, $dbuser, $dbpass, $db);
		$newSettings = array($dbhostN, $dbuserN, $dbpassN, $dbN);

		if(!$force && array_intersect($currentSettings, $newSettings) == $currentSettings)
			return;
		@mysqli_close();
	}

	if($dbhostN) $dbhost = $dbhostN;
	if($dbuserN) $dbuser = $dbuserN;
	if($dbpassN) $dbpass = $dbpassN;
	if($dbN) $db = $dbN;
	//echo 	"dbhost: $dbhost, dbuser: $dbuser, dbpass: $dbpass, db: $db<p>";;

	if(isset($dbhost) && isset($dbuser) && isset($dbpass) && isset($db)) {
		$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);

		if ($lnk < 1) {
			$errMessage ="Not able to connect: invalid database username and/or password.";
			echo $errMessage;
		}

		if(!mysqli_select_db($db)) echo "Failed to select [$db]: ".mysqli_error();
	}
	else ;  // the session was shut down
}
}

// internationalization
function getI18NPropertyFile($country) {
	$file = $country == 'AU' ? 'i18n-EN-AU.txt' : (
					$country == 'CA' ? 'i18n-EN-CA.txt' : (
					$country == 'UK' ? 'i18n-EN-UK.txt' : (
					$country == 'NZ' ? 'i18n-EN-NZ.txt' : (
					$country == 'IE' ? 'i18n-EN-IE.txt' : (
					$country == 'BE' ? 'i18n-EN-BE.txt' : (
					$country == 'JP' ? 'i18n-EN-JP.txt' : (
					$country == 'TR' ? 'i18n-EN-TR.txt' : 
					'i18n-EN-US.txt')))))));
	return $file;
}

function getI18NProperties($country=null) {
	if($country) $file = getI18NPropertyFile($country);
	else if($_SESSION) $file = $_SESSION['i18nfile'];
	if(!$file) $file = getI18NPropertyFile(null);
	return parse_ini_file($file, true);
}

function getI18Property($prop, $default=null) {
	global $NO_SESSION;
	$props = $_SESSION ? getI18NProperties() : ($NO_SESSION ? $NO_SESSION['i18n'] : null);
	
	if(strpos($prop, '|')) {
		$prop = explode('|', $prop);
		$val = $props[$prop[0]][$prop[1]];
	}
	else $val = $props[$prop];
	return $props ? $val : $default;
}

function getCurrencyMark() {
	static $currencyMark, $country;
	global $NO_SESSION;
	if($NO_SESSION && (!$country || $country != getI18Property('country')))
		$currencyMark = null;  // fetch currency mark afresh
	if(!$currencyMark) {
		if(function_exists('getI18Property')) $currencyMark = getI18Property('currencysymbol', '$');
		else $currencyMark = '$';
		if(strpos($currencyMark, 'SYMBOL:') === 0) $currencyMark = '&'.substr($currencyMark, strlen('SYMBOL:')).';';
		if($currencyMark == '#36') $currencyMark = '$';
	}
	return $currencyMark;
}

function conservativeDate($format, $time='now') {
	return FALSE;
	global $db;
	if($db != 'dogslife') {
		return $time == 'now' ? date($format) : date($format, $time);
	}
}

function month3Date($time='now') { // Jan 5
	if($d = conservativeDate('M j', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('month3dateformat', 'M j');
	return $time == 'now' ? date($format) : date($format, $time);
}

function longDate($time='now') {
	if($d = conservativeDate('F j, Y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('longdateformat', 'F j, Y');
	return $time == 'now' ? date($format) : date($format, $time);
}

function longDayAndDate($time='now') {
	if($d = conservativeDate('l, F j', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('longdayanddateformat', 'l, F j');
	return $time == 'now' ? date($format) : date($format, $time);
}

function longerDayAndDate($time='now') {
	if($d = conservativeDate('l, M j, Y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('longerdayanddateformat', 'l, M j, Y');
	return $time == 'now' ? date($format) : date($format, $time);
}

function longestDayAndDate($time='now') {
	if($d = conservativeDate('l, F j, Y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('longestdayanddateformat', 'l, F j, Y');
	return $time == 'now' ? date($format) : date($format, $time);
}

function longestDayAndDateAndTime($time='now') {
	if($d = conservativeDate('l, F j, Y g:i a', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('longestdayanddateandtimeformat', 'l, F j, Y g:i a');
	return $time == 'now' ? date($format) : date($format, $time);
}

function shortDateAndDay($time='now') {
	return shortDate($time).' '.($time == 'now' ? date('D') : date('D', $time));
}
	
function shortDate($time='now') {
	if($d = conservativeDate('m/d/Y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('shortdateformat', 'm/d/Y');
	return $time == 'now' ? date($format) : date($format, $time);
}

function shortNaturalDate($time='now', $noYear=false) {
	if($d = conservativeDate('n/j/Y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('shortnaturaldateformat', 'n/j/Y');
	if($noYear) {
		$date = $time == 'now' ? date($format) : date($format, $time);
		$yearFirst = strpos($format, 'Y') === 0 || strpos($format, 'y') === 0;
		if($yearFirst) return substr($date, 5);
		else return substr($date, 0, -5);
	}
	return $time == 'now' ? date($format) : date($format, $time);
}

function shortestDate($time='now', $noYear=false) {
	if($d = conservativeDate('n/j/y', $time)) return $d;
	
	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('shortestdateformat', 'n/j/y');
	if($noYear) {
		$date = $time == 'now' ? date($format) : date($format, $time);
		$yearFirst = strpos($format, 'Y') === 0 || strpos($format, 'y') === 0;
		if($yearFirst) return substr($date, 3);
		return substr($date, 0, -3);
	}
	return $time == 'now' ? date($format) : date($format, $time);
}

function shortDateAndTime($time='now', $military=false) {
	if($d = conservativeDate(($military ? 'm/d/Y H:i' : 'm/d/Y g:i a'), $time)) return $d;

	static $format; // use static value if $_SESSION, not otherwise because of changing dbs
	$format = $_SESSION && $format ? $format : getI18Property('shortdateandtimeformat', 'm/d/Y g:i a');
	$timeFormat = $military ? str_replace('g:i a', 'H:i', $format) : $format;
	return $time == 'now' ? date($timeFormat) : date($timeFormat, $time);
}

function relativeDateTime($aDateAndTime) {
	$date = date('Y-m-d', ($time = strtotime($aDateAndTime)));
	if($date == date('Y-m-d')) $dtprefix = '';
	else if($date == date('Y-m-d', strtotime("-1 day"))) $dtprefix = 'yesterday ';
	else if($date > date('Y-m-d', strtotime("-7 days"))) $dtprefix = date('l', $time).' ';
	else if(substr($date, 0, 4) == date('Y')) $dtprefix = date('l', $time).' '.month3Date($time).' ';
	else $dtprefix = longerDayAndDate($time).' ';
	return date('g:i a', $time).' '.$dtprefix;
}



function tzAdjustedSql($sql) {
	$sql = str_replace('CURDATE()', "'".date('Y-m-d')."'", $sql);  // timezone fix
	return str_replace('CURTIME()', "'".date('H:i:s')."'", $sql);
}

// TIME ZONE FNS
		
function guessZoneForState($state) {	
	static $LTZoneStates = array(
		'Pacific'=>'WA,OR,NV,CA,AZ',
		'Arizona'=>'AZ',
		'Mountain'=>'MT,ID,WY,SD,UT,CO,NM',
		'Central'=>'ND,MN,WI,IA,IL,KS,MI,OK,AR,TN,MS,AL,TX,LA',
		'Hawaii'=>'HI',
		'Alaska'=>'AK',
		'Samoa' => 'AS',
		'Guam' => 'GU',
		'Puerto_Rico' => 'PR',
		'Virgin Islands' => 'VI',
		'Wake' => 'Wake'
		);
	return $LTZoneStates[$state];
}

function getLTZones() {
	static $LTZones;
	if($LTZones) return $LTZones;
	$LTZones = array(
			'USA - Eastern (America/New_York)'=>'America/New_York',
			'USA - Central (America/Chicago)'=>'America/Chicago',
			'USA - Mountain (America/Boise)'=>'America/Boise',
			'USA - Pacific (America/Los_Angeles)'=>'America/Los_Angeles',
			'USA - Alaska (America/Juneau)'=>'America/Juneau',
			'USA - Hawaii (Pacific/Honolulu)' =>'Pacific/Honolulu',
			'USA - Arizona (America/Phoenix)' => 'America/Phoenix',
			'USA - Guam (Pacific/Guam)' => 'Pacific/Guam',
			'USA - Puerto_Rico (America/Puerto_Rico)' => 'America/Puerto_Rico',
			'USA - Samoa (Pacific/Samoa)' => 'Pacific/Samoa',
			'USA - Virgin Islands (America/St_Thomas)' => 'America/St_Thomas',
			'USA - Wake (Pacific/Wake)' => 'Pacific/Wake'
			);	
	$oz = array('Australia/ACT','Australia/Adelaide','Australia/Brisbane','Australia/Broken_Hill','Australia/Canberra',
	'Australia/Currie','Australia/Darwin','Australia/Eucla','Australia/Hobart','Australia/LHI',
	'Australia/Lindeman','Australia/Lord_Howe','Australia/Melbourne','Australia/North','Australia/NSW',
	'Australia/Perth','Australia/Queensland','Australia/South','Australia/Sydney','Australia/Tasmania',
	'Australia/Victoria','Australia/West','Australia/Yancowinna', 'Europe/London', 'Asia/Hong_Kong', 'Asia/Tokyo',
	'Europe/Istanbul');
	foreach($oz as $tz) $LTZones[$tz] = $tz;
	//print_r($LTZones);
	//setLocalTimeZone();
	if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {
	$nz = array('New Zealand/Auckland'=>'Pacific/Auckland', 'New Zealand/Wellington'=>'Pacific/Wellington');
	foreach($nz as $label => $tz) $LTZones[$label] = $tz;
	}
	return $LTZones;
}

function getZoneByLabel($label) {
	$z = getLTZones();
	return $z[$label];	
}
	
function setLocalTimeZone($zoneLabel=null) {
	//global $LTZones, $LTZoneStates;
	$zoneLabel = $zoneLabel ? $zoneLabel : $_SESSION['preferences']['timeZone'];
//echo "X: $zoneLabel [".print_r($LTZoneStates,1)."]";exit;	
	if($zoneLabel) $tz = $zoneLabel; //getZoneByLabel($zoneLabel);
	else if($bizAddress = $_SESSION['preferences']['bizAddress']) {
		$bizAddress = explode(' | ', $bizAddress);
		if($state = $bizAddress[5]) {
			$tz = getZoneByLabel(guessZoneForState($state));
			if($tz) $_SESSION['preferences']['timeZone'] = $tz;
		}
	}
//echo 	$tz;exit;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "X: $zoneLabel [".getZoneByLabel($zoneLabel)."]"; echo 	$tz;exit; }

	date_default_timezone_set($tz ? $tz : 'America/New_York');
	//echo date('Y-m-d');exit;
}

$timeZoneStates = array(
	'PT'=>'WA,OR,NV,CA,AZ',
	'MT'=>'MT,ID,WY,SD,UT,CO,NM',
	'CT'=>'ND,MN,WI,IA,IL,KS,MI,OK,AR,TN,MS,AL,TX,LA',
	'HT'=>'HI',
	'AKT'=>'AK');
	
function getAllTimeZoneOffsetsFromET() {  // private
	return array('Eastern'=>'-00:00', 'Central'=>'-01:00', 'Mountain'=>'-02:00', 'Pacific'=>'-03:00', 'Alaska'=>'-04:00', 'Hawaii'=>'-06:00',
											'AET'=>'14:00');
}

function getTimeZoneSymbol() {
	if(!$_SESSION['timeZone'] && ($bizAddress = $_SESSION['preferences']['bizAddress'])) {
		$bizAddress = explode(' | ', $bizAddress);
		if($state = $bizAddress[5])
			$_SESSION['preferences']['timeZone'] = defaultTimeZone($state);
	}
	return $_SESSION['preferences']['timeZone'] ? $_SESSION['preferences']['timeZone'] : 'ET';
}

function defaultTimeZone($state, $city=null) {  // private
	global $timeZoneStates;
	foreach($timeZoneStates as $tz => $states)
		if(strpos($states, strtoupper($state)) !== FALSE)
			return $tz;
	return 'ET';
}

function tzNowSQL($tzSymbol=null) { // unused 11/8/2010
	$timeZones = getAllTimeZoneOffsetsFromET();
	$tzSymbol = $tzSymbol ? $tzSymbol : getTimeZoneSymbol();
	return "CONVERT_TZ(NOW(), '{$timeZones['ET']}', '{$timeZones[$tzSymbol]}')";
}




// =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
function getLocalTime($tzSymbol=null) {  // called in appointment-fns.php, homepage_owner.php,incomplete-appts-section.php, menu-owner.html
	return time();// + getLocalOffsetSeconds($tzSymbol);
}

function getLocalOffsetSeconds($tzSymbol=null) { // private
	$offset = explode(':', getLocalOffset($tzSymbol));
	return $offset[0] * 3600 + ($offset[1] * 60 * ($offset[0] < 0 ? -1 : 1));
}
	
function getLocalOffset($tzSymbol=null) {
	$timeZones = getAllTimeZoneOffsetsFromET();
	$tzSymbol = $tzSymbol ? $tzSymbol : getTimeZoneSymbol();
	return $timeZones[$tzSymbol];
}
// =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

function recordInFutureSQL($datefield, $timefield) {  // commented out in appointment-fns.php
	$localNow = getLocalTime();
	$today = date('Y-m-d', $localNow);
	$now = date('H:i:s', $localNow);
	return "($datefield > '$today' OR ($datefield = '$today' AND $timefield > '$now'))";
}
	
function recordInPastSQL($datefield, $timefield) { // unused 11/8/2010
	$localNow = getLocalTime();
	$today = date('Y-m-d', $localNow);
	$now = date('H:i:s', $localNow);
	return "($datefield < '$today' OR ($datefield = '$today' AND $timefield < '$now'))";
}

function tableHasColumn($table, $field) {
	foreach(fetchAssociations("DESCRIBE $table") as $col)
	 if($col['Field'] == $field) return true;
}


// DOES NOT REALLY BELONG HERE SINCE IT REFERS TO A PARTICULAR TABLE...
function setUserActive($userid, $active) {
	if(!$userid) return;
	global $dbhost, $db, $dbuser, $dbpass;
	if($db != 'petcentral') list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	updateTable('tbluser', array('active'=>($active ? 1 : '0')), "userid = $userid", 1);
	$numrows = mysqli_affected_rows();
	if($db1) reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $numrows;
}

function checkUserStatus($userids) {
	if(!$userids) return;
	$userids = is_array($userids) ? join(',', $userids) : $userids;
	global $dbhost, $db, $dbuser, $dbpass;
	if($db != 'petcentral') list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$result = fetchKeyValuePairs("SELECT userid, active FROM tbluser WHERE userid IN ($userids)", 1);
	if($db1) reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $result;
}

// TEST FNS

function dbTEST($names) {
	global $db;
	$names = is_array($names) ? $names : explode(',', $names);
	return in_array($db, $names);
}

function useridsOnlyTEST($names) {
	return useridsOnlyTEST($names);
}

function loginidsOnlyTEST($names) {
	$names = is_array($names) ? $names : explode(',', $names);
	$names = array_map('strtoupper', $names);
	return in_array(strtoupper($_SESSION["auth_login_id"]), $names);
}


function mattBang($str) {
	if(mattOnlyTEST()) {echo "<hr>BANG!<br>$str</hr>";exit;}
}



function tedOnlyTEST() {
	return $_SERVER['REMOTE_ADDR'] == '173.73.2.113';
}

function IPAddressTEST($ips) {
	$ips = is_array($ips) ? $ips : explode(',', $ips);
	return in_array($_SERVER['REMOTE_ADDR'], $ips);
}

function userTEST($ids) {
	$ids = is_array($ids) ? $ids : explode(',', $ids);
	return in_array($_SESSION["auth_user_id"], $ids);
}

function getHeaderBizLogo($dir) {
	if(file_exists($dir.'logo.jpg')) $dir .= 'logo.jpg';
	else if(file_exists($dir.'logo.gif')) $dir .= 'logo.gif';
	else if(file_exists($dir.'logo.png')) $dir .= 'logo.png';
	else $dir = '';
	return $dir;
}

function killSwitch() {
	// Kill the current session if auth_user_id is marked for killing
	if($_SESSION['auth_user_id'] && $db) { // $db may be null, even when user logged in.  E.g., login page.
		if(fetchRow0Col0("SHOW tables LIKE 'tblpreference'"))
			if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'killswitch_{$_SESSION['auth_user_id']}'")) {
				deleteTable('tblpreference', "property = 'killswitch_{$_SESSION['auth_user_id']}'", 1);
				logChange($_SESSION['auth_user_id'], 'killswitch', 'k', $note="killed session [{$_SESSION['auth_login_id']}]");
				session_unset();
				session_destroy();
				return 1;
			}
	}
}

function setKillSwitch($userid, $loginid=null) {
	if(fetchRow0Col0("SHOW tables LIKE 'tblpreference'")) {
		replaceTable('tblpreference', array('property'=>"killswitch_$userid", 'value'=>1), 1);
		logChange($userid, 'killswitch', 'c', $note="set kill switch [$userid] [$loginid]");
		return 1;
	}
}

function getManagers($ids=null, $ltStaffAlso=false) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	if(!$ids) {
		$ltStaffAlso = $ltStaffAlso ? "" : "AND ltstaffuserid = 0";
		$managers = fetchAssociationsKeyedBy(
			"SELECT *, CONCAT_WS(' ', fname, lname) as name
				FROM tbluser
				WHERE bizptr = {$_SESSION["bizptr"]}
					$ltStaffAlso
					AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
				ORDER BY SUBSTRING(rights FROM 1 FOR 1) DESC, lname ASC, fname ASC", 'userid');
	}
	else {
		$managers = fetchAssociationsKeyedBy(
			"SELECT *, CONCAT_WS(' ', fname, lname) as name
				FROM tbluser
				WHERE userid IN (".join(',', $ids).")", 'userid');
	}
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);	
	return $managers;
}

function getUserByID($userid) {
	if(!$userid) return $userid;
	global $dbhost, $db, $dbuser, $dbpass;
	static $recentUsers;
	if($recentUsers[$userid]) return $recentUsers[$userid];
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require  "common/init_db_common.php";
	$recentUsers[$userid] = fetchFirstAssoc("SELECT userid, bizptr, lname, fname, loginid, email FROM tbluser WHERE userid = $userid LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	return $recentUsers[$userid];
}

function tallyPage($page) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$oldTally = fetchRow0Col0("SELECT tally FROM tblpagetally WHERE page='$page' AND db='$db1' AND date='".date('Y-m-d')."' LIMIT 1");
	replaceTable('tblpagetally', array('page'=>$page, 'db'=>$db1, 'date'=>date('Y-m-d'), 'tally'=>$oldTally+1), 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
}
	
?>