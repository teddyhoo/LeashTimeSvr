<? // qrcode-fns.php

function getQRCodeFileName($string) {
	if(!$string) return null;
	$hash = hash('MD5' , $string);
	return fetchRow0Col0("SELECT CONCAT(hash, '.png') FROM tblqrcode WHERE hash = '$hash' LIMIT 1");
}

function getQRCode($string, $forcecreation=0) {
	if($forcecreation || !($file = getQRCodeFileName($string)) || !file_exists("qrcodes/$file"))
		$file = makeQRCode($string);
	return "<img src='qrcodes/$file' title=''>";
}

function makeQRCode($string) {
	$hash = hash('MD5' , $string);
	deleteTable('tblqrcode', "hash = '$hash'", 1);
	require_once "phpqrcode/qrlib.php";
	QRcode::png($string, "qrcodes/$hash.png", $level = QR_ECLEVEL_L, $size = 2, $margin = 0, $saveandprint=false);
	insertTable('tblqrcode', array('string'=>$string, 'hash'=>$hash), 1);
	return "$hash.png";
}