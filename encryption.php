<? // encryption.php
/* MCRYPT VERSION
function lt_encrypt($str) {
	$key = trim(file_get_contents("../../security/.key"));
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $str, MCRYPT_MODE_ECB, $iv));
}

function lt_decrypt($str) {
	$key = trim(file_get_contents("../../security/.key"));
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	return str_replace("\x0", '', mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($str), MCRYPT_MODE_ECB, $iv));
}
*/
/*
$test = '0000111122223333';
echo "Test: [$test]".strlen($test)." chars<p>";
echo "Encrypted: [".($encrypted = lt_encrypt($test))."]".strlen($encrypted)." chars<p>";
echo "Decrypted: [".lt_decrypt($encrypted)."] ".strlen(lt_decrypt($encrypted))." chars<p>";
*/

require "Crypt/Blowfish.php";

function salted($str, $extra=null) {
	return ($extra ? md5("$extra") : '').trim(file_get_contents("../../security/.key2"))."$str";
}

function lt_encrypt($str) {
	//$bf =& Crypt_Blowfish::factory('cbc');
	$key = trim(file_get_contents("../../security/.key"));
	$bf = new Crypt_Blowfish($key);
	if (PEAR::isError($bf)) {
	     echo $bf->getMessage();
	     exit;
 	}
	return base64_encode($bf->encrypt($str));
}

function lt_decrypt($str) {
	$key = trim(file_get_contents("../../security/.key"));
	
	$bf = new Crypt_Blowfish($key);
	if (PEAR::isError($bf)) {
	     echo $bf->getMessage();
	     exit;
 	}
 	
//if(mattOnlyTEST()) {print_r($str);exit;}

	return str_replace("\x0", '', $bf->decrypt(base64_decode($str)));
}

function lt_encryptB($str) {
	//$bf =& Crypt_Blowfish::factory('cbc');
	$key = trim(file_get_contents("../../security/.key"));
	$bf = new Crypt_Blowfish($key);
	if (PEAR::isError($bf)) {
	     echo $bf->getMessage();
	     exit;
 	}
	return $bf->encrypt($str);
}

function lt_decryptB($str) {
	$key = trim(file_get_contents("../../security/.key"));
	
	$bf = new Crypt_Blowfish($key);
	if (PEAR::isError($bf)) {
	     echo $bf->getMessage();
	     exit;
 	}
 	
//if(mattOnlyTEST()) {print_r($str);exit;}

	return str_replace("\x0", '', $bf->decrypt($str));
}

function nuggetize($str) {
	return urlencode(lt_encrypt("$str"));
}

function denuggetize($str) {
	return lt_decrypt("$str");
}

