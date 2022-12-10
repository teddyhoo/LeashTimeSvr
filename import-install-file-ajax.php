<? // import-install-file-ajax.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');

$dir = '../../data/clientimports/'.$_SESSION['bizptr'];
$filekind = $_REQUEST['kind'];

if(!file_exists($dir)) $error = "$dir does not exist.";
else {
	$files = glob($dir.'/'.$filekind.'.*');
	$error = null;
	if(count($files) > 1) $error = "Too many $filekind files in $dir";
	else if(count($files) == 0) "No $filekind file has been uploaded.";
}
if($error) {
	echo $error;
	exit;
}

$ext = substr($files[0], strrpos($files[0], '.'));

if($filekind == 'vets') {
	/*if($ext == '.xls') {echo 'You must convert the file to CSV first.'; exit;}
	else */if($ext == '.csv' || $ext == '.xls') {
		$map = "map-bluewave-clinics.csv";
		$file = "$dir/$filekind.csv";
		include "import-vet-clinics.php";
	}
	else if(in_array($ext, array('.htm','.html'))) {
		$file = "{$_SESSION['bizptr']}/$filekind$ext";
		include "import-vets-html-bw-new.php";
	}
	else echo "Don't know what to do with *$ext files.";
}
else if($filekind == 'sitters') {
	if(in_array($ext, array('.htm','.html'))) {
		$file = "{$_SESSION['bizptr']}/$filekind$ext";
		include "import-providers.php";
	}
	else echo "Don't know what to do with *$ext files.";
}
else if($filekind == 'clients') {
	/*if($ext == '.xls') {echo 'You must convert the file to CSV first.'; exit;}
	else */if($ext == '.csv' || $ext == '.xls') {
		$rawReferralTypes = $_REQUEST['extraData'];
		if(!$rawReferralTypes) {
			echo "<font color=red>No Referral Types Specified.  Quitting.</font>";
			exit;
		}
		$map = "map-bluewave-clients.csv";
		$file = "{$_SESSION['bizptr']}/$filekind$ext";
		//
		//$addNewClientsOnly = true;
		//
		$initializeBWCustomFields = 1;
		include "import-clients.php";
	}
	else echo "Don't know what to do with *$ext files.";
}

else if($filekind == 'clientdetails') {
	if(in_array($ext, array('.htm','.html','.zip'))) {
		$file = "{$_SESSION['bizptr']}/$filekind$ext";
		//$_REQUEST['privatealarm'] = '1';
		include "import-clients-details-bluewave.php";
	}
	else echo "Don't know what to do with *$ext files.";
	echo print_r(array('.htm','.html','.zip'), 1);
}

else if($filekind == 'pets') {
	/*if($ext == '.xls') {echo 'You must convert the file to CSV first.'; exit;}
	else */if($ext == '.csv' || $ext == '.xls') {
		$rawPetTypes = $_REQUEST['extraData'];
		if(!$rawPetTypes) {
			echo "<font color=red>No Pet Categories Specified.  Quitting.</font>";
			exit;
		}
		$map = "map-bluewave-pets.csv";
		$file = "{$_SESSION['bizptr']}/$filekind$ext";
		$initializeBWPetCategories = 1;
		//$ignorePetsForAllClientsButThese = "650,668,666,670,681,691,696,698,697,707,709,711,713,716,708,680,644,646,678,676,657,677,655,685,684,671,672,682,714,654,647,648,645,652,706,649,653,658,661,673,651,660,662,663,664,665,718,656,659,667,674,679,683,689,669,686,687,688,690,675,700,701,692,693,695,703,704,694,699,712,715,710,717";
		include "import-pets.php";
	}
	else echo "Don't know what to do with *$ext files.";
}

else if($filekind == 'historical') {
	/*if($ext == '.xls') {echo 'You must convert the file to CSV first.'; exit;}
	else */if($ext == '.csv' || $ext == '.xls') {
		if($_REQUEST['extraDataFile']) {
			$file = "{$_SESSION['bizptr']}/itemlist.htm";
			$debug = 0;
			include "import-item-list-bluewave.php";
			foreach($items as $item) { // $items is defined in import-item-list-bluewave.php
				$rawItemCategories[] = "{$item['code']},{$item['internal']}";
			}
			$rawItemCategories = join(',', (array)$rawItemCategories);
		}
		else $rawItemCategories = $_REQUEST['extraData'];
		if(!$rawItemCategories) {
			echo "<font color=red>No Item List Specified.  Quitting.</font>";
			exit;
		}
		$uf = "{$_SESSION['bizptr']}/$filekind$ext";
		include "setup/import-historical-data-bw.php";
	}
	else echo "Don't know what to do with *$ext files.";
}




