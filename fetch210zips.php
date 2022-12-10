<?  // fetch210zips.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$raw="20001
20002
20003
20004
20005
20006
20007
20008
20009
20010
20011
20016
20024
20036
20037
22201
22203
22204
22205
22207
22209
22213
30097";

//foreach(explode("\n", $raw) as $zip) insertTable('tblzipcodeslocal', array('zip'=>trim($zip)));