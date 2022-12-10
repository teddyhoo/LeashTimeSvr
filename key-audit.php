<?// key-audit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "key-safe-fns.php";
require_once "comm-fns.php";

/* displays keys held by all providers
	displays a checkbox next to each provider with an email address.
	"Send Audit" emails a message that summarizes the keys held by each provider and
	asks the provider to respond.
*/

// Determine access privs
locked('+ka,+#km');
extract($_REQUEST);

$pageTitle = "Key Audit";
$finalMessage = '';

if($_POST) {
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	$emailsSent = 0;
	foreach(array_keys($_POST) as $param)
		if(strpos($param, 'prov-') === 0) {
			queueAuditMessage(substr($param, strlen('prov-')), $message, $signature);
			$emailsSent++;
		}
	$finalMessage = $emailsSent.' audit notification'.($emailsSent == 1 ? '' : 's').' issued.';
}

function queueAuditMessage($provid, $message, $signature) {
	$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
	
	$prov = getProvider($provid);
	$keys = getProviderKeys($provid);
	$subject = "Key Audit";
	$body = "Dear ".providerShortName($prov).",<p>$message
			<style>.pad {padding-left: 10px;}</style><table>";
	if(!$keys) $body .= "<tr><td>You have no client keys";
	else {
		foreach($keys as $key) $clients[] = $key['clientptr'];
		$clientDetails = getClientDetails($clients, array('pets','dagger'));
		
		$body .= "<tr><th>Key</th><th>Key Hook</th><th>Description</th><th class=pad>Client</th><th class=pad>Pets</th>\n";
		foreach($keys as $key) {
			$detail = $clientDetails[$key['clientptr']];
			$pets = $detail['pets'] ? join(', ', $detail['pets']) : '&nbsp;';
			$body .= "<tr><td>".formattedProviderKeyId($key, $provid);
			$body .= "</td><td>{$key['bin']}";
			$body .= "</td><td>".truncatedLabel($key['description'], 15);
			$body .= "</td><td class=pad>{$detail['clientname']}";
			$body .= "</td><td class=pad>$pets</td></tr>";
		}
	}
	$body .= "</table>\n<p>\nSincerely,<p>$signature";
	//echo $body.'<p>';
	enqueueEmailNotification($prov, $subject, $body, null, null, 'html');
}


include "frame.html";
// ***************************************************************************
if($finalMessage) {
	echo $finalMessage;
	include "frame-end.html";
	exit;
}

$sql = "SELECT tblkey.*, CONCAT_WS(' ',fname, lname) as client 
	FROM tblkey LEFT JOIN tblclient ON clientid = clientptr
	ORDER BY lname, fname";
	
$providerNames = getProviderShortNames('order by name');

echoButton('','Send Audit Email to Selected Sitters', 'sendAudit()');
echo '<p>';
if(TRUE || mattOnlyTEST()) {
	fauxLink('Select All ACTIVE ONLY', 'selectAll(-1)');
	echo ' - ';
}
fauxLink('Select All', 'selectAll(1)');
echo ' - ';
fauxLink('Deselect All', 'selectAll(0)');
echo '<p>';
echo "<style>.pad {padding-left: 10px;}</style>";
echo "<p>Generated: ".shortDateAndTime(time())."</p>";

echo "<table><form name='auditform' method='POST'><tr><td valign='top'>";
echo "<table>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Active Sitters</td></tr>";
listProviders($providerNames, 1);
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Inactive Sitters</td></tr>";
listProviders($providerNames, 0);
//if(mattOnlyTEST()) {
	echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Key Safes</td></tr>";
	listSafes();
//}
echo "</table></td>";
$defaultMessage = "Our records show that you have the following keys in your possession.  Please ".
						"check to see whether this list is accurate and then let us know.  If you have keys that are not listed here, please tell us which keys you have.";
echo <<<HTML
<td valign=top style='padding-left:20px;padding-right:20px;background:lightgrey;'><span style='font-weight:bold;'>Message:</span><p>Dear <i>Sitter</i>,<p>
<textarea rows=10 cols=30 name='message'>$defaultMessage</textarea>
<ul><li><i>Keys listed here</i></ul>
Sincerely,<p>
<input name='signature' size=40 autocomplete='off'>
</td></tr>
HTML;

echo "</form></table>";

function listSafes() {
	$safes = getKeySafes($activeOnly=1);
	$allPets = array();
	$livePets = array();
	foreach($safes as $safeid => $safeName) {
		echo "<tr><td>$safeName</td></tr>";
		require_once "pet-fns.php";
		$keys = getProviderKeys($safeid);
		if(!$keys) echo "<tr><td colspan=2>&nbsp</td><td>Safe holds no keys.</td></tr>";
		else {
			foreach($keys as $key) $clients[] = $key['clientptr'];
			$clientDetails = getClientDetails($clients);
			foreach($clients as $client) 
				if(!$allPets[$client]) {
					foreach(getClientPets($client) as $k => $v) $allPets[$client][$k] = $v['name'];
					foreach(getActiveClientPets($client) as $k => $v) $livePets[$client][$k] = $v['name'];
				}
			foreach($keys as $key) {
				$detail = $clientDetails[$key['clientptr']];
				$pets = $allPets[$key['clientptr']];
				foreach((array)$pets as $i => $pet)
					if(!in_array($pet, (array)$livePets[$key['clientptr']]))
						$pets[$i] = '&dagger;'.$pet;
				$pets = $pets ? join(', ', (array)$pets) : '&nbsp;';
//if($_SESSION['staffuser']) $pets .= "[".print_r($livePets[$key['clientptr']], 1)."]";
				$keyLabel = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'] 
					? $key['description'] 
					: formattedProviderKeyId($key, $provid);
				$keyTitle = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'] 
					? formattedProviderKeyId($key, $provid) 
					: safeValue($key['description']);
				echo "<tr><td>&nbsp</td><td title='$keyTitle'>$keyLabel";
				echo "</td><td class=pad>{$detail['clientname']}";
				echo "</td><td class=pad>$pets</td></tr>";
			}
		}
		
		
	}
}	
function listProviders($providerNames, $active) {
	$allPets = array();
	$livePets = array();
	$class = $active ? 'active' : 'inactive';
	foreach($providerNames as $provid => $name) {
		$prov = getProvider($provid);
		if($prov['active'] != $active) continue;
		$cbid = "prov-{$prov['providerid']}";
		$checkBox = $prov['email'] ? "<input name='$cbid' id='$cbid' type='checkbox' class='$class'>" : "<input type='checkbox' disabled>";
		$label = "{$prov['fname']} {$prov['lname']} ".($prov['nickname'] ? "({$prov['nickname']})" : '');
		echo "<tr><td>$checkBox</td><td style='font-weight:bold;' colspan=3><label for='$cbid'>$label - ".
						($prov['email'] ? $prov['email'] : "<i>No email address</i>")."</label></td></tr>";
						
/*		if($prov['email']) {
			$keys = getProviderKeys($provid);
			if(!$keys) echo "<tr><td>&nbsp</td><td>Sitter has no keys.</td></tr>";
			else {
				foreach($keys as $key) $clients[] = $key['clientptr'];
				$clientDetails = getClientDetails($clients, array('pets'));
				foreach($keys as $key) {
					$detail = $clientDetails[$key['clientptr']];
					$pets = $detail['pets'] ? join(', ', $detail['pets']) : '&nbsp;';
					echo "<tr><td>&nbsp</td><td>".(formattedProviderKeyId($key, $provid));
					echo "</td><td class=pad>{$detail['clientname']}";
					echo "</td><td class=pad>$pets</td></tr>";
				}
			}
		}*/
		
		
		if($prov['email']) {
			require_once "pet-fns.php";
			$keys = getProviderKeys($provid);
			if(!$keys) echo "<tr><td colspan=2>&nbsp</td><td>Sitter has no keys.</td></tr>";
			else {
				foreach($keys as $key) $clients[] = $key['clientptr'];
				$clientDetails = getClientDetails($clients);
				foreach($clients as $client) 
					if(!$allPets[$client]) {
						foreach(getClientPets($client) as $k => $v) $allPets[$client][$k] = $v['name'];
						foreach(getActiveClientPets($client) as $k => $v) $livePets[$client][$k] = $v['name'];
					}
				foreach($keys as $key) {
					$detail = $clientDetails[$key['clientptr']];
					$pets = $allPets[$key['clientptr']];
					foreach((array)$pets as $i => $pet)
						if(!in_array($pet, (array)$livePets[$key['clientptr']]))
							$pets[$i] = '&dagger;'.$pet;
					$pets = $pets ? join(', ', (array)$pets) : '&nbsp;';
//if($_SESSION['staffuser']) $pets .= "[".print_r($livePets[$key['clientptr']], 1)."]";
					$keyLabel = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'] 
						? $key['description'] 
						: formattedProviderKeyId($key, $provid);
					$keyTitle = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'] 
						? formattedProviderKeyId($key, $provid) 
						: safeValue($key['description']);

					echo "<tr><td>&nbsp</td><td title='$keyTitle'>$keyLabel";
					echo "</td><td class=pad>{$detail['clientname']}";
					echo "</td><td class=pad>$pets</td></tr>";
				}
			}
		}
		
		
	}
}	
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('message','Message','signature','Signature');
function selectAll(on) {
	var cbs = document.getElementsByTagName('input');
	var activeOnly = on == -1;
	for(var i=0;i<cbs.length;i++) {
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled
				&& (!activeOnly || (cbs[i].getAttribute('class') == 'active')))
			cbs[i].checked = on ? true : false;
		}
}

function sendAudit() {
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) selCount++;
	var noSelections = '';
	if(selCount == 0)
		noSelections = "Please select at least one sitter first.";
	if(MM_validateForm(
			noSelections, '', 'MESSAGE',
			'message', '' , 'R',
			'signature', '', 'R'))
		document.auditform.submit();
}
</script>

<?

// ***************************************************************************

include "frame-end.html";
?>
