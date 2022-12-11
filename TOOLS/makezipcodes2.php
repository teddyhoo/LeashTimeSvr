<? // makezipcodes2.php
set_time_limit(600);

include "common/init_db_common.php";

$handle = fopen("ZIP_CODES.txt", "r");


// "00501","+40.922326","-072.637078","HOLTSVILLE","NY","SUFFOLK","UNIQUE"
$n = 0;
while($line = fgets($handle)) {
	if(!$n % 10) echo $line;
	$line = explode(',', trim($line));
	$vals = array();
	foreach(array('zipcode','lat','lon','city','state','county','zipclass') as $i => $field)
	  $vals[$field] = !$line[$i] ? '' : substr($line[$i], 1, strlen($line[$i]) - 2);
	insertTable('zipcodes2', $vals, 1);
}
fclose($handle);