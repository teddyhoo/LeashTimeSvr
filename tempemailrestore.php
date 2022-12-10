<? // tempemailrestore.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";


$emails = fetchAssociations("SELECT * FROM tempClientEmails");

foreach($emails as $a) echo "UPDATE tblclient SET email='{$a['email']}' where clientid = {$a['clientptr']};<br>";