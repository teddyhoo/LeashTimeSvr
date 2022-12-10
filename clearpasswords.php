<? // clearpasswords.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "encryption.php";

$locked = locked('o-,cm');

if($db != 'romppetcare') return;
if(date('Y-m-d') > '2011-08-25') return;
$ccs = fetchAssociations("SELECT * FROM tblcreditcard WHERE ccid IN (363,364,365,366)");
foreach($ccs as $cc) {
	echo lt_decrypt($cc['x_card_num'])."<p>";
}


