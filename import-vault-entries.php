<? // import-vault-entries.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "cc-processing-fns.php";

//echo "HAMSTRUNG!";exit;
if($db != 'themonsterminders') {echo "WRONG DATABASE!";exit;}

$locked = locked('o-');


/*
createTable();
$strm = fopen('/var/data/clientimports/themonsterminders/monster_minders_customer_vault.csv', 'r');
fgetcsv($strm);
while($row = fgetcsv($strm)) 
	doQuery("INSERT INTO tblvaultentry VALUES(0,0,".join(',', array_map('val', $row)).")", 1);
exit;
*/

//reportOnVaultEntryTable();

generateCCsAndACHs();

//reportAllSolverasPaymentSources();

//compareSolverasAndNonSolverasCards();

//findStrandedVaultEntries();

//if($_GET['gogo']) gogo();

function compareSolverasAndNonSolverasCards() {
	$sql = "SELECT tblcreditcard.*, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
						FROM tblcreditcard 
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE gateway IS NULL OR gateway != 'Solveras'
						ORDER BY sortname";
	$clients = fetchAssociations($sql);
//print_r($clients);	
	echo "<h2>Clients with Pre-Solveras Credit Cards </h2>";
	echo "<table border=1 bordercolor=black>";
	echo "<tr><th>Name<th>Card Number<th>Card Co<th>Solveras<th>Account<th>New Card Co";
	foreach($clients as $client) {
		$sql = "SELECT *, last4 as acctnum, 'CC' as ptype FROM tblcreditcard WHERE active AND clientptr = {$client['clientptr']} AND gateway = 'Solveras'";
		$accts = fetchAssociations($sql);
		$sql = "SELECT *, 'ACH' as ptype FROM tblecheckacct WHERE active AND clientptr = {$client['clientptr']} AND gateway = 'Solveras'";
		foreach(fetchAssociations($sql) as $item) $accts[] = $item;
		if(!$accts) $accts[] =  array();
		foreach($accts as $acct) {
			//if($acct['ptype'] == 'CC') $acct['acctnum'] = lt_decrypt($acct['acctnum']);
			$style = $acct['primarypaysource'] ? "style='font-weight:bold;'" : '';
			echo "<tr><td>{$client['name']}<td>{$client['last4']}<td>{$client['company']}"
					 ."<td>{$acct['ptype']}<td $style>{$acct['acctnum']}<td>{$acct['company']}";
		}
	}
	echo "</table>";
}	


function reportAllSolverasPaymentSources() {
	foreach(fetchAssociations(
					"SELECT tblcreditcard.*,  'CC' as ptype, x_card_num as account,
						CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
						FROM tblcreditcard 
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE tblcreditcard.active AND gateway = 'Solveras'") as $item) {
		$sortprim = $item['primarypaysource'] ? 0 : 1;
		$items["{$item['sortname']}_$sortprim"] = $item;
	}
	foreach(fetchAssociations(
					"SELECT tblecheckacct.*, 'ACH' as ptype, acctnum as account,
						CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
						FROM tblecheckacct 
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE tblecheckacct.active AND gateway = 'Solveras'") as $item) {
		$sortprim = $item['primarypaysource'] ? 0 : 1;
		$items["{$item['sortname']}_$sortprim"] = $item;
	}
	ksort($items);
	echo "<h2>Clients with Solveras Pay Sources (".count($items).")</h2>";
	echo "<table border=1 bordercolor=black>";
	echo "<tr><th>Name<th>Type<th>Account<th>Card Co";
	foreach($items as $item) {
		$bold = $item['primarypaysource'] ? "style='font-weight:bold;'" : "";
		if($item['ptype'] == 'CC') $item['account'] = lt_decrypt($item['account']);
		echo "<tr><td>{$item['name']}<td>{$item['ptype']}<td $bold>{$item['account']}<td>{$item['company']}";
	}
	echo "</table>";
	
	$noccs = fetchAssociationsKeyedBy(
					"SELECT 
						CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname, clientid
						FROM tblclient 
						LEFT JOIN tblcreditcard ON clientid = clientptr AND tblcreditcard.active AND gateway = 'Solveras'
						WHERE ccid IS NULL", 'clientid');
//print_r($noccs);						
	$noachs = fetchAssociationsKeyedBy(
					"SELECT 
						CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
						FROM tblclient 
						LEFT JOIN tblecheckacct ON clientid = clientptr AND tblecheckacct.active AND gateway = 'Solveras'
						WHERE acctid IS NULL AND clientid IN (".join(',', array_keys($noccs)).")", 'sortname');
		
	ksort($noachs);
	echo "<h2>Clients without Solveras Pay Sources (".count($noachs).")</h2>";
	echo "<table border=1 bordercolor=black>";
	echo "<tr><th>Name<th>Vault Entries";
	foreach($noachs as $item) {
		$nm = mysql_real_escape_string($item['name']);
		$count = fetchRow0Col0("SELECT count(*) FROM tblvaultentry WHERE check_name = '$nm'"
														." OR CONCAT_WS(' ', first_name, last_name) = '$nm'");												
		echo "<tr><td>{$item['name']}<td>".($count ? $count : '');
	}
	echo "</table>";
	
	
}

function generateCCsAndACHs() {
	$sql = "SELECT * FROM tblvaultentry WHERE ltclientid > 0 ORDER BY updated ASC";
	if(!($result = doQuery($sql))) return null;
	$assocs = array();
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if($row['check_name']) {
			//echo "ACH - {$row['check_name']} ({$row['customerid']}): ";
			$ach = array(
				'clientptr'=>$row['ltclientid'],
				'abacode'=>$row['check_aba'], // masked, last4 clear
				'acctnum'=>$row['account'],
				'acctname'=>$row['check_name'],
				'accttype'=>null,
				'acctentitytype'=>null,
				'x_address'=>$row['address_1'],
				'x_city'=>$row['city'],
				'x_state'=>$row['state'],
				'x_zip'=>$row['postal_code'],
				'x_country'=>$row['country'],
				'x_phone'=>$row['phone'],
				'autopay'=> 1,
				'gateway'=>'Solveras',
				'vaultid'=>$row['customerid'],
				'encrypted'=>0
				);
			// ensure that this vaultid is not already there
			if(fetchRow0Col0("SELECT acctid FROM tblecheckacct WHERE vaultid = {$ach['vaultid']} LIMIT 1"))
				echo "<font color=pink>Vault ID: ACH $vaultid is already there for client {$row['ltclientid']}!</font><br>";
			else {
				$acctid = saveNewACH($ach); // deactivates old acct
				$ach['acctid'] = $acctid;
				saveACHInfo($ach);
				echo "Vault ID: ACH $vaultid has been added  for client {$row['ltclientid']}.<br>";
				
			}
		}
		else {
			$expDate = $row['cc_exp'];  //MMYY
			$expDate = '20'.substr($expDate,2).'-'.substr($expDate, 0, 2).'-01';
			//echo "CC - {$row['first_name']} {$row['last_name']} ({$row['customerid']}): ";
			$cc = array(
				'clientptr'=>$row['ltclientid'],
				'company'=>guessMaskedCreditCardCompany($row['account']),
				'x_card_num'=>$row['account'], // masked, last4 clear
				'x_card_code'=>'XXX',
				'x_exp_date'=>$expDate,
				'x_first_name'=>$row['first_name'],
				'x_last_name'=>$row['last_name'],
				'x_address'=>$row['address_1'],
				'x_city'=>$row['city'],
				'x_state'=>$row['state'],
				'x_zip'=>$row['postal_code'],
				'x_country'=>$row['country'],
				'x_phone'=>$row['phone'],
				'autopay'=> 1,
				'gateway'=>'Solveras',
				'vaultid'=>$row['customerid']
				);
			if(fetchRow0Col0("SELECT ccid FROM tblcreditcard WHERE vaultid = {$cc['vaultid']} LIMIT 1"))
				echo "<font color=pink>Vault ID: CC $vaultid is already there!</font><br>";
			else {
				$ccid = saveNewCC($cc); // deactivates old CC
				$cc['ccid'] = $ccid;
				saveCCInfo($cc);
				echo "Vault ID: CC $vaultid has been added.<br>";
			}
		}
	}
}

function reportOnVaultEntryTable() {
	$sql = "SELECT * FROM tblvaultentry ORDER BY updated ASC";
	if(!($result = doQuery($sql))) return null;
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$key = ($row['email'] ? $row['email'] : "({$row['address_1']} {$row['postal_code']})").$row['account'];
		$name = $row['check_name'] ? $row['check_name'] : "{$row['first_name']} {$row['last_name']}";
		if($accounts[$key]) echo "<font color=lightgrey>Dup for {$accounts[$key]} ($key))</font><br>";
		else {
			$accounts[$key] = $name;
			$vaultid = $row['customerid'];
			$where = $row['email'] ? "email = '{$row['email']}'" : "street1 = '{$row['address_1']}'";
			$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE $where LIMIT 1", 1);
			$source = $row['cc_exp'] ? "CC: {$row['account']}" : "ACH: {$row['account']}";
			if($client) {
				$found++;
				$foundACH += $row['cc_exp'] ? 0 : 1;
				$foundCC += $row['cc_exp'] ? 1 : 0;
				echo "{$client['fname']} {$client['lname']} [=$name] vault id: $vaultid ($source)<br>";
				$clients["{$client['fname']} {$client['lname']}"]++;
				updateTable('tblvaultentry', array('ltclientid'=>$client['clientid']), "vaultentryid = {$row['vaultentryid']} ", 1);
			}
			else {
				$list = explode(' ', $name);
				$pat = array_pop($list);
				//echo "<font color=red>[$name] [$where] vault id: $vaultid not found!</font><br>";
				$missing[] = "<font color=red>[<a target=clients href='client-list.php?pattern=$pat'>$name</a>] [$where] vault id: $vaultid not found!</font><br>";
				$missingCC += $row['cc_exp'] ? 1 : 0;
				$missingACH += $row['cc_exp'] ? 0 : 1;
			}
		}
	}
	echo "<p>Total Found: $found<br>-- CC: $foundCC<br>-- ACH: $foundACH<p>"
				."Matched Clients with multiple non-dup payment accounts (".count($clients).")<p>"
				."No matches found for: (".count($missing).")<br>-- CC: $missingCC<br>-- ACH: $missingACH<p>".join('', $missing);
	echo "<p>Matched Clients with multiple non-dup payment accounts (".count($clients)."):<p>";
	foreach($clients as $name => $n)
		if($n > 1) echo "$name: $n<br>";
}


function createTable() {
doQuery(
"CREATE TABLE IF NOT EXISTS `tblvaultentry` (
  `vaultentryid` int(11) NOT NULL auto_increment,
  `ltclientid` int(11) NOT NULL,
  `customerid` varchar(100) default NULL,
  `first_name` varchar(100) default NULL,
  `last_name` varchar(100) default NULL,
  `account` varchar(100) default NULL,
  `cc_exp` varchar(100) default NULL,
  `check_aba` varchar(100) default NULL,
  `check_name` varchar(100) default NULL,
  `orderid` varchar(100) default NULL,
  `orderdescription` varchar(100) default NULL,
  `company` varchar(100) default NULL,
  `email` varchar(100) default NULL,
  `phone` varchar(100) default NULL,
  `cell_phone` varchar(100) default NULL,
  `fax` varchar(100) default NULL,
  `website` varchar(100) default NULL,
  `address_1` varchar(100) default NULL,
  `address_2` varchar(100) default NULL,
  `city` varchar(100) default NULL,
  `state` varchar(100) default NULL,
  `postal_code` varchar(100) default NULL,
  `country` varchar(100) default NULL,
  `updated` varchar(100) default NULL,
  `created` varchar(100) default NULL,
  `shipping_company` varchar(100) default NULL,
  `shipping_country` varchar(100) default NULL,
  `shipping_postal_code` varchar(100) default NULL,
  `shipping_state` varchar(100) default NULL,
  `shipping_city` varchar(100) default NULL,
  `shipping_address_1` varchar(100) default NULL,
  `shipping_address_2` varchar(100) default NULL,
  `shipping_first_name` varchar(100) default NULL,
  `shipping_last_name` varchar(100) default NULL,
  `shipping_email` varchar(100) default NULL,
  `merchant_defined_field_1` varchar(100) default NULL,
  `merchant_defined_field_2` varchar(100) default NULL,
  `merchant_defined_field_3` varchar(100) default NULL,
  `merchant_defined_field_4` varchar(100) default NULL,
  `merchant_defined_field_5` varchar(100) default NULL,
  `merchant_defined_field_6` varchar(100) default NULL,
  `merchant_defined_field_7` varchar(100) default NULL,
  `merchant_defined_field_8` varchar(100) default NULL,
  `merchant_defined_field_9` varchar(100) default NULL,
  `merchant_defined_field_10` varchar(100) default NULL,
  `merchant_defined_field_11` varchar(100) default NULL,
  `merchant_defined_field_12` varchar(100) default NULL,
  `merchant_defined_field_13` varchar(100) default NULL,
  `merchant_defined_field_14` varchar(100) default NULL,
  `merchant_defined_field_15` varchar(100) default NULL,
  `merchant_defined_field_16` varchar(100) default NULL,
  `merchant_defined_field_17` varchar(100) default NULL,
  `merchant_defined_field_18` varchar(100) default NULL,
  `merchant_defined_field_19` varchar(100) default NULL,
  `merchant_defined_field_20` varchar(100) default NULL,
  PRIMARY KEY  (`vaultentryid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
");
}

function findStrandedVaultEntries() {
	//$clients = fetchKeyValuePairs("SELECT CONCAT_WS(' ', first_name, last_name), ltclientid FROM tblvaultentry WHERE ltclientid > 0 and last_name IS NOT NULL");
	//$stranded = fetchCol0("SELECT CONCAT_WS(' ', first_name, last_name) FROM tblvaultentry 
	//												WHERE ltclientid = 0 AND CONCAT_WS(' ', first_name, last_name) IN ('".join("','", array_keys($clients))."')");
	$clients = fetchKeyValuePairs("SELECT check_name, ltclientid FROM tblvaultentry WHERE ltclientid > 0 and check_name IS NOT NULL");
	//print_r($clients);
	$stranded = fetchCol0("SELECT check_name FROM tblvaultentry 
													WHERE ltclientid = 0 AND check_name IN ('".join("','", array_keys($clients))."')");
	echo join('<br>', array_unique($stranded));
}

/* CCs
210 => 805, // Altman
218 => 808, // Armstrong  ECHECK SELECTED [KEEP SELECTED]
242 => 817, // Bellwoar
352 => 885, // DeMatteis
368 => 887, // Dietrich
427 => 991, // Kat Martin
493 => 836, // Hounsell
504 => 944, // Jeff Jones
516 => 1051, // Sims (Kellogg)
// Kissel?
544 => 977 // Pamela Legg (Landsman)
579 => 999, // MacNaughton
582 => 994, //Maguire ***** "Susan","Maguire","482870******0012","0412","","","","","","bmckeon@phlyins.com"
597 => 988, //Markley
617 => 1002, //Meisel ECHECK SELECTED [KEEP SELECTED]
682 => 1037, // Phelan
720 => 1071, // Sawyer
755 => 1052 // Skrypski  ECHECK NOT SELECTED [KEEP UNSELECTED]
760 => 1055 // Smith
792 => 971, // Danielle Land (Target) 

*/

function gogo() {
	$names = "Alisa Goren
Amy West
Ashley Staller
Britton Keeshan
Bryan Benn
Cathy Carroll
Charly Simpson
Dalmita Benton
Diane Ketelhut
Gabrielle Thorpe
JoAnn Garbin
Kristin Munro
Lorraine Mello
Michelle Anderson
Paige Wolf
Sandra Wintner
Stacy Milan
Stephanie Rupertus
Tiffanie Baldock
Laura Kittell Hoensch
Aaron Skrypski
Andrea Pesce";
	$names = array_map('trim', explode("\n", $names));
	echo "<h2>To Do</h2>";
	echo "<table border=1 bordercolor=black>";
	echo "<tr><th>Vault Entry ID<th>Client<th>Name<th>Vault ID<th>Updated<th colspan=2>Account<th>Exp";
	foreach($names as $name) {
		$clientid = fetchCol0("SELECT clientid FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '$name'");
		if(count($clientid) == 0) $clientid = '?';
		else if(count($clientid) > 1) $clientid = 'X';
		else $clientid = $clientid[0];
		$items = fetchAssociations("SELECT * FROM tblvaultentry WHERE check_name = '$name'");
		foreach(fetchAssociations("SELECT * FROM tblvaultentry WHERE CONCAT_WS(' ', first_name, last_name) = '$name'")
							as $row) $items[] = $row;
		usort($items, 'byupdated');
		foreach($items as $i => $item) {
			$type = $item['check_name'] ? 'ACH' : 'CC';
			$style = $i ? "style='background:lightgrey;'" : '';
			$entryid = $i ? '' : $item['vaultentryid'];
			echo "<tr $style><td>$entryid<td>$clientid<td>$name<td>{$item['customerid']}<td>{$item['updated']}<td>$type<td>{$item['account']}<td>{$item['cc_exp']}";
			if(!$i) updateTable('tblvaultentry', array('ltclientid'=>$clientid), "vaultentryid = $entryid",  1);
			$clientid = '';
		}
	}
	echo "</table>";
}
 function byupdated($a, $b) { return 0 - strcmp($a['updated'], $b['updated']); }