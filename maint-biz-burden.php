<? // maint-biz-burden.php
require "common/init_session.php";
require "common/init_db_petbiz.php";

$startingDB = $db;

require "common/init_db_common.php";
require_once "gui-fns.php";


if(userRole() != 'z' && $bizid) $locked = locked('o-');
else $locked = locked('z-');

if(substr($_SERVER["SCRIPT_NAME"], 1) == 'maint-biz-burden.php')
	$bizid = $_REQUEST['bizid'];
	
$bizid = intValueOrZero($bizid);

if(!$bizid) exit;
	
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid", 1); // WHERE activebiz=1

if($startingDB != 'leashtimecustomers' && $_SERVER["SCRIPT_NAME"] != '/maint-edit-biz.php') {echo "wrong db"; exit;}

$dbhost = $biz['dbhost'];
$dbuser = $biz['dbuser'];
$dbpass = $biz['dbpass'];
$db = $biz['db'];


$bizptr = $biz['bizid'];
$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
if ($lnk < 1) {
	echo "Not able to connect: invalid database username and/or password.\n";
}
$lnk1 = mysqli_select_db($db);
if(mysqli_error()) echo mysqli_error();


$sql = 
"SELECT SUM((data_length+index_length)/power(1024,1)) as dbsize
	FROM information_schema.tables
	WHERE table_schema='$db';";
$allsizeKB = fetchRow0Col0($sql);
$dbSizeInMB = round($allsizeKB/1024);
$sql = 
"SELECT (data_length+index_length)/power(1024,1) tablesize
	FROM information_schema.tables
	WHERE table_schema='$db' and table_name='#TAB#';";
$msgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessage', $sql));
$msgcount = fetchRow0Col0("SELECT count(*) FROM tblmessage");
$errorcount = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));

$photosKB = round(fileSizeBytes("bizfiles/biz_$bizid/photos")/1024);
$photosMB = round($photosKB/1024);
$totalMB = round(($allsizeKB+$photosKB)/1024);

$data = explodePairsLine("DB Size (MB)|$dbSizeInMB||Messages|$msgcount||Messages (KB)|$msgsizeKB"
												."||Errors|$errorcount||Errors (KB)|$errorsizeKB||Photos|$fileCount||Photos (MB)|$photosMB"
												."||Total (MB)|$totalMB");


function fileSizeBytes($dir) {
	global $fileCount;
	$total = 0;
	foreach(glob("$dir/*") as $f) {
		//echo "$f<br>";
		if(is_dir($f)) $total += fileSizeBytes($f);
		else {
			$total += filesize($f);
			$fileCount += 1;
			//echo "$f: ".filesize($f)."<br>";
		}
	}
	//echo "$dir: ".$total."<br>";
	return $total;
}

function RealFileSize($f)
{
		$fp = fopen($f, 'r');
    $pos = 0;
    $size = 1073741824;
    fseek($fp, 0, SEEK_SET);
    while ($size > 1)
    {
        fseek($fp, $size, SEEK_CUR);

        if (fgetc($fp) === false)
        {
            fseek($fp, -$size, SEEK_CUR);
            $size = (int)($size / 2);
        }
        else
        {
            fseek($fp, -1, SEEK_CUR);
            $pos += $size;
        }
    }

    while (fgetc($fp) !== false)  $pos++;
		fclose($fp);
    return $pos;
}


//$newErrorsStart = '2016-01-01 00:00:00';
//$newErrors = fetchRow0Col0("SELECT count(*) FROM tblerrorlog WHERE time < '$newMessagesStart'");

if(substr($_SERVER["SCRIPT_NAME"], 1) == 'maint-biz-burden.php') {
?>
<h2><?= "{$biz['bizname']} ({$biz['db']}) ID: $bizid" ?></h2>
<? } 
else {
	ob_start();
	ob_implicit_flush(0);
}?>
<table>
<?
foreach($data as $k => $v) echo "<tr><td>$k</td><td>".number_format($v)."</td></tr>";
?>
</table>
<? if(substr($_SERVER["SCRIPT_NAME"], 1) !== 'maint-biz-burden.php') {
	$storageBurden = ob_get_contents();
	ob_end_clean();
}
