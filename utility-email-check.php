<? // utility-email-check.php
set_time_limit(120);

require_once "smtp-validate-class.php";

function extractVars($keys, $source) {
	// return an array to pass to extract containing only those values
	// from $source named in $keys
	$result = array();
	if(is_string($keys)) $keys = explode(',',$keys);
	foreach($keys as $key)
		if(isset($source[$key]))
			$result[$key] = $source[$key];
	return $result;
}


extract(extractVars('email', $_REQUEST));

$emails = explode("\n", $email);
foreach($emails as $i => $unused) {
	$emails[$i] =  trim($emails[$i]);
	if(!$emails[$i]) unset($emails[$i]);
}
if(!$emails) {
	$error = "No emails supplied.";
}
if(!$error) {
	$redstar = "<font color=red>*</font>";
	$checker = new SMTP_validateEmail;
	$response = $checker->validate($emails, 'notice@leashtime.com');
	if(count($response) > 1) {
		foreach($response as $badEmail => $result)
			if(!$result) {
				$badEmails[] = (strpos(strtolower($badEmail), '@aol') ? $redstar : ''). $badEmail;
			}
	}
	if($badEmails) sort($badEmails);
}
?>
<h2>Email Checker</h2>
<form method='POST' onsubmit='presubmit()'>
<table border=1 bordercolor=black>
<tr>
<td style='vertical-align:top;'>Enter email addresses to check in the box below: <input type=submit><br>
<textarea name=email cols=60 rows=30><?= $email ?></textarea>
</td>
<td style='vertical-align:top;' id=results>
<?
if($email) {
	echo "<b>Results:</b><p>";
	if($error) echo "<font color=red>$error</font>";
	else if(!$badEmails) echo "<font color=darkgreen>These emails all check out.</font>";
	else echo "<font color=red>The following email addresses failed:</span><br><font color=red>* AOL addresses always fail.  Ignore AOL addresses.</font><p>".join("<br>\n", $badEmails);
}

?>
</td>
</table>
</form>
<script language='javascript'>
function presubmit() {
	alert('starting');
	document.getElementById('results').innerHTML = "<font color=darkgreen>Depending on the number of email addresses you entered, this operation may take up to two minutes.</font>";
}
</script>