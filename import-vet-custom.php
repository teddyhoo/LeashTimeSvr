<? // import-vet-custom.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

exit;


if($db !== 'goldcoastpetsau') {echo 'NOT GOLD COAST';exit;}

$str = <<<STR
Animal Options Ormeau//(07) 5546 6756//8/29 Blanck Street//Ormeau//QLD//4208
Anvet Coomera//07 5580 6360//at River Meadows Shopping Centre, Cnr Reserve & Hargraves Rds//Upper Coomera//QLD//4209
Animal Welfare League Coomababah//07 5594 0111//Shelter Rd//Coombabah//QLD//4216
Oxenford Veterinary Surgery (Chris Dixon)//07 5573 1414//100-106 Old Pacific Highway//Oxenford//QLD//4210
Coast Vet 
Currumbin Vet Clinic 
Labrador Veterinary Surgery (Dr Chris)//5591 2255//1 Clayton Street//Labrador (Chirn Park)//QLD//4215
Gold Coast Vet Surgery
Gold Coast Veterinary Service//07 5538 5909//2800 Gold Coast Hwy (Cnr of Monte Carlo Avenue) Broadbeach//Surfers Paradise//QLD//4217
Greencross Mudgeeraba//07 5530 5555//Cnr Mudgeeraba & Worongary Rd (PO Box 13)//Mudgeeraba//QLD//4213 
Greencross Runaway Bay//(07) 5537 3611//Bayview St//Runaway B//QLD//4216
Greencross Southport//07 5531 2573//168 Nerang St//Southport/QLD//4215
Greencross Robina//07 55930300//Robina quays shopping centre Robina Parkway//Robina//QLD//4226
Greencross Nerang//(07)5596 4899//79 Price St//Nerang//QLD//4211
Mountain Tamaborine Vet Clinic
Robina Veterinary Surgery//07 5593 2055//Corner of Commerce Drive & Ron Penhaligon Way//Robina//QLD//4226 
Tallebudgera Vet Clinic//07 5522 4566//Shop 1, Man On The Bike Shopping Centre, Corner Trees and Tallebudgera Connection Road//Tallebudgera//Qld//4228
Currumbin Valley Veterinary Surgery//5533 0381//'Piedmont' 1596 Currumbin Creek Road//Currumbin Valley//QLD//4223
Vetcall Burleigh Waters//5593 5557//Unit 2/2/ Executive Drive//Burleigh Waters//QLD//4220
Vetcall Ashmore//5539 4133//Cnr of Ashmore Rd and Heeb St//Ashmore//QLD//4214
Vetcall Mermaid Waters (Mark)//(07) 5572 4331//Q Supercentre Bermuda St//Mermaid Waters//QLD//4128
Vetcall Benowa
Vet Lounge (Upper Coomera)//07 5502 3333//5/2 Sierra Pl//Upper Coomera//QLD//4209 
STR;
$vets = explode("\n", $str);
sort($vets);
echo "<table border=1>";
foreach($vets as $line) {
	$vet = explode('//', trim($line));
	echo "<tr><td>".join('<td>', $vet);
	insertTable('tblclinic', array('clinicname'=>$vet[0],
	'officephone'=>$vet[1],'street1'=>$vet[2],'city'=>$vet[3],'state'=>$vet[4],'zip'=>$vet[5],), 1);
}
echo "</table>";