<? // kramers-invoices.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "frame-bannerless.php";

$perpage=40;
$start = $_GET['start'] ? $_GET['start'] : '0';
$sql = "SELECT count(*) 
				FROM tblmessage 
				WHERE (inbound = 0 OR inbound IS NULL)
					AND subject LIKE '%invoice%'
					AND correstable = 'tblclient'
					AND body NOT LIKE '%hooban%'
				ORDER BY datetime
				";
$count = fetchRow0Col0($sql);
$sql = str_replace("count(*)", "datetime, body", $sql)."LIMIT $start, $perpage";
$result = doQuery($sql);

echo "<style>
@media print
{
.newpage {page-break-before:always;}
}
</style>";
echo "<span style='size:50%'>$count found</span><p>";
$nextStart = $_GET['start']+$perpage;
if($nextStart < $count) echo "<a href='kramers-invoices.php?start=$nextStart'>Show next $perpage invoices starting with #$nextStart</a><hr>";


$result = doQuery($sql);

while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	echo substr($row['body'], strpos($row['body'], '</style>')+strlen('</style>'));
	echo "<p class='newpage'>";
}