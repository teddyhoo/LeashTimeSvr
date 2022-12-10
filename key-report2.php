<?
// key-report2.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "key-fns.php";
// Determine access privs
locked('@ka,@ki,@#km');

$sql = "SELECT tblkey.*, CONCAT_WS(' ',fname, lname) as client, IF(active=1,'active','inactive') as status
	FROM tblkey LEFT JOIN tblclient ON clientid = clientptr
	ORDER BY lname, fname";
	
$providerNames = getProviderShortNames();

function quoted($str) {
  return '"'.$str.'"';
}

function oneline($str) {
  return quoted(str_replace("\n",' ', str_replace("\r",' ', $str)));
}

function location($loc) {
	global $safes, $providerNames;
	if(in_array($loc, array('missing', 'client'))) return quoted($loc);
	if(isset($safes[$loc])) return quoted(trim(str_replace('--','', $safes[$loc])));
	return $providerNames[$loc];
}

$result = doQuery($sql, 1);
if(!mysql_num_rows($result)) $out .= "No keys found.";
else {
	$out .= "Key ID,Key Hook,Client,Status,Lock Location,Key Description,Location\n";
	while($key = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$copies = 1;
		while(isset($key["possessor$copies"]) && $key["possessor$copies"]) {
			$label = formattedKeyId($key['keyid'], $copies);
			$out .= $label.','.quoted($key['bin']).','.quoted($key['client']).','.quoted($key['status'])
						.','.quoted($key['locklocation'])
						.','.oneline($key['description']).','.location($key["possessor$copies"]);
			$copies++;
			$out .= "\n";
		}
	}
}
header("Content-Type: text/plain");
header("Pragma: public"); // required
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false); // required for certain browsers
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=keys.csv;" );
header("Content-Transfer-Encoding: binary");
header("Content-Length: ".strlen($out));
echo $out;

