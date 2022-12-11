<? // import-provider-detail.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once 'field-utils.php';  // for strippedPhoneNumber
require_once "system-login-fns.php";

$inputPatterns = <<<PATTERNS
<input name="
<input style="background-color:
<input type="hidden
<td colspan="2" class="navButts"><input
<td colspan="2" nowrap="nowrap"><input style="background-color:
<td colspan="2"><input name=
<td colspan="2" valign="middle"><input name=
<td colspan="2" class="navButts"><span class="navButts"><input name=
PATTERNS;
$inputPatterns =  array_map('trim', explode("\n", $inputPatterns));


$textareaPatterns = <<<PATTERNS
<textarea name=
<td colspan="2" height="10%"><textarea name=
PATTERNS;
$textareaPatterns =  array_map('trim', explode("\n", $textareaPatterns));


// A Admin, E Full, R Limited, O Owner, I Inactive
$statusOptionPatterns = <<<PATTERNS
<option value="A"  
<option value="E"
<option value="R"
<option value="O"
<option value="I"
PATTERNS;
$statusOptionPatterns =  array_map('trim', explode("\n", $statusOptionPatterns));

if($_POST) {
	extract($_POST);
	$p = modifiedProvider($text);
	if($p) updateTable('tblprovider', $p, "providerid={$p['providerid']}", 1);
}
	
function modifiedProvider($text) {
	$postedProvider = analyzeProvider($text);
	//echo "POSTED: ".print_r($postedProvider, 1)."<p>\n";
	$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE employeeid = '{$postedProvider['str_ID']}' LIMIT 1");
	if(!$provider) {
		echo "No provider with employee ID {$postedProvider['str_ID']} found.";
		return;
	}
	$fieldsOfInterest = "str_phn_pgr,str_eml,str_txtmsg_adrs,str_address1,str_address2,str_city,str_st,str_zip,"
						."str_grphy,dob,ssn,str_cmpnstn,str_rmrk,note,str_eml_ftr,str_cd,str_pswrd,status";
	$fieldsOfInterest = explode(',', $fieldsOfInterest);
	$straightCopies = array('str_phn_pgr'=>'workphone','str_eml'=>'email',
									'str_address1'=>'street1','str_address2'=>'street2','str_city'=>'city','str_st'=>'state','str_zip'=>'zip',
									'ssn'=>'taxid');
									
	foreach($postedProvider as $k => $v) {
		$v = trim($v);
echo "$k: $v<br>";		
		if(!$v) continue;
		if(isset($straightCopies[$k])) $provider[$straightCopies[$k]] = $v;
		else if($k == 'str_grphy') $provider['notes'] = addFieldToNote('Area', $v, $provider['notes']);
		else if($k == 'dob') $provider['notes'] = addFieldToNote('DOB', $v, $provider['notes']);
		else if($k == 'str_rmrk') $provider['notes'] = addFieldToNote('Remarks', $v, $provider['notes']);
		else if($k == 'str_txtmsg_adrs') {
			$num = strippedPhoneNumber($v);
			$match = null;
			foreach(array('homephone','cellphone','workphone','fax') as $k)
				if(strippedPhoneNumber($provider[$k]) == $num) $match = $k;
			if($match) $provider[$match] = "T$v";
			else $provider['notes'] = addFieldToNote('Text Message Address:', $v, $provider['notes']);
		}
		else if($k == 'str_cd') {
			$username = $v;
			$password = trim(isset($postedProvider['str_pswrd']) ? $postedProvider['str_pswrd'] : '');
			$rights = 'p-va,vc,ma,vh,vp';
			$newData = array('loginid'=>$username, 'password'=>$password, 'rights'=>$rights, 
												'bizptr'=>$_SESSION["bizptr"], 'active'=>$provider['active']);

//print_r($newData);exit;

			mysqli_select_db('petcentral');

			$userNamed = findSystemLoginWithLoginId($username, true);
			if($provider['userid']) { // provider already has a login
				$user = findSystemLogin($provider['userid']);
				if(is_string($user)) {
					echo "Error: $user"; // ERROR
					return $provider;
				}
				if($userNamed && $userNamed != $user)  {// ERROR - name already in use
					echo "Error: user name $username is already in use.";
					return $provider;
				}
					
				else updateSystemLogin($newData);
			}
			else {
				if($userNamed) {// ERROR - name already in use
					echo "Error: user name $username is already in use.";
					return $provider;
				}
				else {
					$newUser = addSystemLogin($newData, 'clientOrProviderOnly');
					if(is_string($newuser)) echo $newuser;
					else $provider['userid'] = $newUser['userid'];
				}
			}
			
			mysqli_close();
			mysqli_connect($_SESSION["dbhost"], $_SESSION["dbuser"], $_SESSION["dbpass"]);
			mysqli_select_db($_SESSION["db"]);

		}
	}
	return $provider;


	// str_phn_pgr, str_eml, str_txtmsg_adrs, 
	// str_address1, str_address2, str_city, str_st, str_zip, 
	// str_grphy, dob, ssn, str_cmpnstn
	// str_rmrk, note, str_eml_ftr, str_cd, str_pswrd, status
	
	// special: str_txtmsg_adrs, str_grphy, dob, str_rmrk, str_cd, str_pswrd, status

}

function addFieldToNote($label, $value, $note) {
	if($note) $note .= "\n";
	return $note.trim($label).': '.trim($value);
}

function analyzeProvider($text) {
	global $inputPatterns, $textareaPatterns, $statusOptionPatterns;
	$provider = array();
	foreach(explode("\n", $text) as $line) {
		$line = stripslashes(trim($line));
//echo htmlentities($line).'<p>';
		if(startsWithAnyOf($line, $inputPatterns)) {
			$provider[getAttribute('name="', $line)] = getAttribute('value="', $line);
		}
		else if(startsWithAnyOf($line, $textareaPatterns)) {
			$provider[getAttribute('name="', $line)] = stripLine($line);
		}
		else if(startsWithAnyOf($line, $statusOptionPatterns)) {
			if(getAttribute('selected="', $line)) $provider['status'] = getAttribute('value="', $line);
		}
	}
	return $provider;
}

function startsWith($line, $pattern) {
	//echo "[".htmlentities($pattern)."] ".htmlentities($line).": [".htmlentities(startsWithAnyOf($line, array($pattern)))."]<br>";
	return startsWithAnyOf($line, array($pattern));
}		
	
function startsWithAnyOf($line, $patterns) {
	foreach($patterns as $pattern)
		if(strpos($line, $pattern) === 0) return $pattern;
}		
	
function stripLine($line) {
	$line = strip_tags($line);
	$line = str_replace('&nbsp;', ' ', $line);
	return  trim($line);
}


function getAttribute($attr, $line) {
	if(!$pos = strpos($line, $attr)) return null;
	$pos += strlen($attr);
	return substr($line, $pos, strpos($line, '"', $pos) - $pos);
}

?>
<form method="POST">
<input type="submit"><p>
Paste content here:<br>
<textarea rows=40 cols=80 name='text'></textarea>
</form>