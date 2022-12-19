<? //response-token-fns.php

// Functions for creating and consuming response tokens to be issued via email
require_once "common/init_db_common-2.php";

$tokenShelfLifeInDays = 14;
define('SYSTEM_USER', 9999999);
$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));

$baseResponseURL = globalURL("response.php");

function consumeTokenRow($token) {
	if($row = findTokenRow($token)) {
		deleteTable('tblresponsetoken', "token = '$token'", 1);
		logChange(999, 'tblresponsetoken', 'd', "[$token]");
	}
	return $row;
}

function findTokenRow($token) {
	logChange(999, 'tblresponsetoken', 's', "[$token]");
	return fetchFirstAssoc("SELECT * from tblresponsetoken WHERE token = '$token' LIMIT 1");
}

function retireTokensOlderThan($days, $currentDbSettings=null) {
	// $currentDbSettings allows this function to be called from the biz context as well as the petcentral context
	$cutoff = date("Y-m-d", strtotime("-$days day"));
	retireTokens("datetime < '$cutoff", $currentDbSettings);
}

function retireExpiredTokens($currentDbSettings=null) {
	// $currentDbSettings allows this function to be called from the biz context as well as the petcentral context
	retireTokens("now() >= expires", $currentDbSettings);
}

function retireTokens($criteria, $currentDbSettings=null) {
	// $currentDbSettings allows this function to be called from the biz context as well as the petcentral context
	$cutoff = date("Y-m-d", strtotime("-$days day"));
	
	if($currentDbSettings && $currentDbSettings["db"] != 'petcentral') {
		mysqli_close();
		include "common/init_db_common.php";
	}
	deleteTable('tblresponsetoken', $criteria, 1);
	if($currentDbSettings) {
		mysqli_close();
		$lnk = mysqli_connect($currentDbSettings['dbhost'], $currentDbSettings['dbuser'], $currentDbSettings['dbpass']);
		if ($lnk < 1)
			$errMessage="Not able to connect: invalid database username and/or password.";
		else mysqli_select_db($currentDbSettings['db']);

	}
}

// $respondent array(userid, clientid, providerid)
function generateResponseURL($bizptr, $respondent, $redirecturl, $systemlogin, $expires=null, $appendToken=false) {
	global $dbhost, $dbuser, $dbpass, $db, $biz; // $biz may be set by caller cron-queued-msgs-email
	$dbSettings = $_SESSION ? array('dbhost'=>$dbhost, 'dbuser'=>$dbuser, 'dbpass'=>$dbpass, 'db'=>$db) : $biz;
	retireExpiredTokens($dbSettings);
	// for diagnotic purposes, let the token linger several days after the confirmation expires
	$expires = $expires ? date('Y-m-d H:i:s', strtotime("+3 days", strtotime($expires))) : $expires;
	$token = generateResponseToken($bizptr, $respondent, $redirecturl, $systemlogin, $appendToken, $expires);
	return is_array($token) ? $token : globalURL("response.php")."?token=$token";
}
	
function generateResponseToken($bizptr, $respondent, $redirecturl, $systemlogin, $appendToken=false, $expires=null) {
	global $db;
  if(!$respondent) $error = "FAILED RESP TOKEN: No respondent id supplied. db: $db [url: $redirecturl]".($systemlogin ? 'SYSTEM_USER' : '');
  else {
		$respondentptr = $respondent['clientid'] ? $respondent['clientid'] : $respondent['providerid'];
		$respondenttbl = $respondent['clientid'] ? 'tblclient' : 'tblprovider';
		if($systemlogin) $loginuserid = SYSTEM_USER;
		else {
			if(!$respondent['userid']) {
				global $db;
				$error = "FAILED RESP TOKEN: Respondent has no LeashTime login id. [biz: $bizptr $respondenttbl $respondentptr]  [url: $redirecturl]";
			}
			else $loginuserid = $respondent['userid'];
		}
	}
	if($error) {
		if($db) logError($error);
		return array($error);
	}

	global $dbhost, $dbuser, $dbpass, $db, $biz; // $biz may be set by caller cron-queued-msgs-email
	$dbSettings = $_SESSION ? array('db'=>$db, 'dbhost'=>$dbhost, 'dbuser'=>$dbuser, 'dbpass'=>$dbpass) : $biz;
	$dbSettings = array($dbSettings['db'], $dbSettings['dbhost'], $dbSettings['dbuser'], $dbSettings['dbpass']);
//if(!$_SESSION) echo "dbSettings: ".print_r($dbSettings,1)." biz: ".print_r($biz,1)."\n";
	
  $centralDBSelected = $dbSettings['db'] == 'petcentral';
  if(!$centralDBSelected) {
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = $dbSettings;
		include "common/init_db_common.php";
	}
//if(!$_SESSION) echo "centralDBSelected: [$centralDBSelected]: $db_local, $dbhost_local, $dbuser_local, $dbpass_local\n";
  $token =  generateToken($respondentptr, $respondenttbl, $bizptr, $redirecturl, $loginuserid, $appendToken, $expires);
  if(!$centralDBSelected) {
		reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
//if(!$_SESSION) echo "RECONNECTED TO: $db_local, $dbhost_local, $dbuser_local, $dbpass_local\n";
	}
	return $token;
}

function modifyToken($tokenid, $token) {
	global $dbhost, $dbuser, $dbpass, $db, $biz; // $biz may be set by caller cron-queued-msgs-email
	$dbSettings = $_SESSION ? array('db'=>$db, 'dbhost'=>$dbhost, 'dbuser'=>$dbuser, 'dbpass'=>$dbpass) : $biz;
	$dbSettings = array($dbSettings['db'], $dbSettings['dbhost'], $dbSettings['dbuser'], $dbSettings['dbpass']);
	
  $centralDBSelected = $dbSettings['db'] == 'petcentral';
  if(!$centralDBSelected) {
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = $dbSettings;
		include "common/init_db_common.php";
	}

  updateTable('tblresponsetoken', $token, "tokenid = $tokenid");
  if(!$centralDBSelected) {
		reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);

	}
	return $token;
}

function generateToken($respondentptr, $respondenttbl, $bizptr, $redirecturl, $loginuserid, $appendToken=false, $expires=null, $useonce=0) {
  $success = false;
  $date = date('Y-m-d H:i:s');
  while(!$success) {
    $token = randomToken();
    $row = array('datetime'=>$date, 'token'=>$token, 'respondentptr'=>$respondentptr, 'respondenttbl'=>$respondenttbl,
    							'bizptr'=>$bizptr, 'url'=>$redirecturl, 'loginuserid'=>$loginuserid, 'useonce'=>$useonce);
    if($expires) $row['expires'] = $expires;
		insertTable('tblresponsetoken', $row); // do not report this error onscreen , 1
    
    if($errNum = mysqli_errno()) {
      // if Duplicate entry try again
      if($errNum == 1062) ; // do nothing
      // else if the problem is not an error with a duplicate key, we have a problem
      else if($errNum != 1062) return null;
    }
    // if no error, success!  else try again
    $success = !mysqli_error();
  }
  if($success && $appendToken) {

		
		updateTable('tblresponsetoken', array('url'=>"$redirecturl$token"), "token = '$token'", 1);
	}
  return $token;
}

function generateSecurityToken($bizptr, $redirecturl='no url') {
	$loginuserid = SYSTEM_USER;
  $respondentptr = SYSTEM_USER;
  $respondenttbl = 'no table';
  return generateToken($respondentptr, $respondenttbl, $bizptr, $redirecturl, $loginuserid);
}

function randomToken() { // generate a random five-letter token based on a range of base26 numbers
  $reallyBadWordFrags = explode(',','alla,amci,anus,arse,ass,bast,biatc,bitc,blow,boio,bollo,boob,bone,buce,bull,butt,cabro,cawk,chri,chinc,chink,chine,choad,chode,coon,clit,cock,cooc,coot,cum,cunt,dago,damn,daygo,dego,deggo,dick,dike,dild,dook,douc,dumb,dum,dyke,ejac,faece,fag,fann,fat,feces,felch,feltch,fellat,flamer,fuck,fuk,gay,god,gook,gring,guido,hardo,hell,hoe,homo,hump,jap,jerk,jesu,jiga,jigg,jiz,kike,kyke,kunt,kooch,koot,lesb,lez,mick,minge,muff,nig,nip,negr,paki,peck,peni,piss,poon,poop,pric,prik,pud,punta,puss,puto,quee,rimj,schlon,scrot,shit,shiz,skank,skeet,slut,smut,spic,sploo,suck,tard,teat,teet,testi,testy,teste,tit,twat,vag,vaj,wank,wetba,whor,wop,yed');
  //sort($unacceptableWords);
  //echo join(',',$unacceptableWords);
  $token = null;
  while(!$token) {
    $base26Num = base_convert(''.mt_rand(5000000, 10000000+5000000),10,26);
    $token = tokenize($base26Num);
    foreach($reallyBadWordFrags as $frag)
      if(strpos($token, $frag) !== false) {
         $token = null;
         break;
      }
  }
  return $token;
}

function tokenize($base26Num) { //'0' == 48, // 'a' == 97
  $token = '';
  for($i=0;$i<strlen($base26Num);$i++) {
    $n = ord(substr($base26Num,$i,1));
    if($n<97) $n += 49; // - 48 + 97 -- turn '0' into 'a'
    else $n += 10;  // turn 'a' into 'k'
    $token .= chr($n);
  }
  return $token;
}
