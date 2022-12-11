<? // bqreader.php
// test, import, encrypt and store decrypted data

exit;


set_time_limit(30 * 60);


$update = 1;


set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "common/db_fns.php";
global $installationSettings;
if(!$installationSettings) {
	$scriptFileDirectory = "/var/www/prod";
	$installationSettings = getSettings($scriptFileDirectory);
//echo 	">>> SETTINGS: [$scriptFileDirectory] ".print_r($installationSettings,1)."<p>";
}

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
require_once "login-fns.php";
require "encryption.php";

if(!mattOnlyTEST()) {echo "go away";exit;}

if(!$_SESSION['auth_user_id']) {
	$user = login('maestro', 'tragic99');
	loginUser($user, $_REQUEST['clienttime']);
}

echo "Starting  at ".date('H:i:s').'<p>';
$key = trim(file_get_contents("../../security/.key"));

$bf = new Crypt_Blowfish($key);


$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz ", 'db'); // WHERE activebiz=1
$allBizzesLeashTimeFirst['leashtimecustomers'] = $bizzes['leashtimecustomers'];
unset($bizzes['leashtimecustomers']);
foreach($bizzes as $dbzz => $bizzz) $allBizzesLeashTimeFirst[$dbzz] = $bizzz;

$strm = fopen('/var/olderserver/www/prod/bizfiles/blow.txt', 'r');
$n = 0;
while(($row = fgetcsv($strm)) && count($row)>0) {
	if(strpos($row[0], 'DB:') === 0) {
		$thisBiz = $allBizzesLeashTimeFirst[substr($row[0], strlen('DB:'))];
		//echo "BIZ: ".print_r($thisBiz, 1)."<br>";
	}
	else if(strpos($row[0], 'TABLE:') === 0)
		$thisTable = substr($row[0], strlen('TABLE:'));
	else {
		connectIfNecessary($thisBiz);
		if($thisTable == 'tblpreference') {
			$encr = localEncrypt($row[1]);
			if($update) {
				replaceTable($thisTable, array('property'=>$row[0], 'value'=>$encr), 1);// update the table
				if(!mysqli_error()) echo "Updated property {$row[0]} with $encr<br>";
			}
			else { // validate
				if(count($row) != 2) echo "<font color=red>Bad $thisTable row: $n</n><br>";
				else echo "$thisTable [{$row[0]}] [{$row[1]}] => [$encr] => [".localDecrypt($encr)."]<br>";
			}
		}
		else if($thisTable == 'tblcreditcard') { // id, cardnum, code
			list($cardid, $cardnum, $code) = $row;
			$encr1 = localEncrypt($cardnum);
			$encr2 = localEncrypt($code);
			if($update) {
				// update the table
				updateTable($thisTable, array('x_card_num'=>$encr1, 'x_card_code'=>$encr2), "ccid='$cardid'", 1);
				if(!mysqli_error()) echo "Updated card $cardid<br>";
			}
			else { // validate
				if(count($row) != 3) echo "<font color=red>Bad $thisTable row: $n</n><br>";
				else {
					echo "$thisTable ($cardid) [$cardnum] [$code] => [$encr1] [$encr2] => ["
					.localDecrypt($encr1)."] [".localDecrypt($encr2)."]<br>";
				}
			}
		}
		else if($thisTable == 'tbluserpref') { // id, cardnum, code
			list($userid, $prop, $value) = $row;
			$encr1 = localEncrypt($value);
			if($update) {
				// update the table
				updateTable($thisTable, array('value'=>$encr1), "userptr='$userid' AND property='$prop'", 1);
				if(!mysqli_error()) echo "Updated $prop for $userid<br>";
			}
			else { // validate
				if(count($row) != 3) echo "<font color=red>Bad $thisTable row: $n</n><br>";
				else {
					list($userid, $prop, $value) = $row;
					echo "$thisTable ($userid) [$prop] [$value] => [$encr1] => ["
					.localDecrypt($encr1)."]<br>";
				}
			}
		}
	}
	//print_r($row); echo "{$thisBiz['db']}: $thisTable<br>";

	$n += 1;

	//if($n==100) break;
}
	echo "<p>Done at ".date('H:i:s');

	echo "<p>N: $n";
				
function connectIfNecessary($thisBiz) {
	global $dbhost, $dbuser, $dbpass, $db, $lnk;
	//echo "Connecting: [$db] => {$biz['db']}<br>";
	if($db == $thisBiz['db']) return;
	$dbhost = $thisBiz['dbhost'];
	$dbuser = $thisBiz['dbuser'];
	$dbpass = $thisBiz['dbpass'];
	$db = $thisBiz['db'];
	$lnk = $lnk ? $lnk : mysqli_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysqli_select_db($db);
	if(mysqli_error()) echo mysqli_error();
	echo "<font color=blue>Connected to {$biz['bizname']} ($db)</font><br>";
}	

function localDecrypt($str) {
	global $bf;
	return str_replace("\x0", '', $bf->decrypt(base64_decode($str)));
}

function localEncrypt($str) {
	global $bf;
	return base64_encode($bf->encrypt($str));
}
