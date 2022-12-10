<? // migration-load-script.php?list=/var/data/dblist.txt
// takes a directory listing and creates a script to "source" all databases
// usage: 
// On OLD server, become root
// 1. as su, ls > /var/data/dblist.txt
// 2. chmod a+r /var/data/dblist.txt
// 3. replace value of $dir, below to reflect old server's "/var/lib/mysqlbackup/" from new server's POV
// 4. load https://leashtime.com/migration-load-script.php?list=/var/data/dblist.txt
// On NEW Server become root:
// 5. as su, execute path shown at top of results page, as seen from new server

if(!$_SERVER['REMOTE_ADDR'] == '68.225.89.173') {
	echo "This script is for matt only.";
	exit;
}

$file = $_GET['list'];
$dir = "/var/lib/mysqlbackup/";
$strm = fopen(($outfile="/var/www/prod/bizfiles/dbmigration_".date('Y-m-d_H-i-s')), 'w');
echo "<h1>$outfile</h1>";
foreach(file($file) as $f) {
	$f = trim($f);
	if(!strpos($f, 'sql.gz')) continue;
	/*$db = explode('-', $f);
	$db = $db[count($db)-1];
	$db = explode('.', $db);
	$db = $db[0];*/
	$cmd = "gunzip < \"$dir$f\" | mysql -u root -ppass123"; //$db
	echo "$cmd<br>";
	fputs($strm, "$cmd\n");
}
fclose($strm);
chmod($outfile, 0700);
echo "<hr><pre>".file_get_contents($outfile)."</pre>";





//"gunzip < database.sql.gz | mysql -u user -p=pass123 database"