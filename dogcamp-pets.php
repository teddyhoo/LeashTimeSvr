<? // dogcamp-pets.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
extract($_GET);
$file = '/var/data/clientimports/dogcampla/pets.csv';

$strm = fopen($file, 'r');
//STERLING,DOG,-,ROBERT BERTRAND,"1664 ANGELUS AVE.  L.A., CA 90026",-,,1,,,-,1

	
$pets = fetchAssociationsGroupedBy(
	"SELECT tblpet.*, UPPER(CONCAT(
												if(fname = '?', '', if(lname <> '?', CONCAT(fname, ' '), fname)),
												if(fname = '?', '', lname))) as owner
			FROM tblpet
			LEFT JOIN tblclient ON clientid = ownerptr",
			
		'owner');	
fgets($strm);
while($row = fgetcsv($strm, 0, ',')) {
	if($group = $pets[strtoupper($row[3])]) {
		echo "{$row[3]}: "; //.print_r($group, 1).'<p>';
		if(count($group) != 1) echo "<font color=red>...".count($group).' pets.  Ignored.</font><p>';
		else if(strtoupper($row[0]) == strtoupper($group[0]['name'])) {
			echo "...I can work with {$group[0]['name']} [{$row[1]}] [{$row[2]}]<p>";
			$type = strpos($row[1], 'AT') ? 'Cat' :(
							strpos($row[1], 'OG') ? 'Dog' :(
							strpos($row[1], 'IZ') ? 'Lizard' : ''));
			updateTable('tblpet', array('type'=>$type, 'breed'=>$row[2]), "petid = {$group[0]['petid']}", 1);
			$types[$row[1]] = 0;
			$breeds[$row[2]] = 0;
			$n++;
		}
	}
	else "<font color=red>Not found: {$row[3]}</font><p>";
}
echo "Can update $n pets.<p>Types: ".join(',', array_keys($types))."<p>Breeds: ".join(',', array_keys($breeds));
		
	
