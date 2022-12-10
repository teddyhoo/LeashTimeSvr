<? // report-archive.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "reports-archive-fns.php";

locked('o-');

$reports = getArchivedReportsOfTypeSummaries($type);
$pageTitle = $_REQUEST['pageTitle'];
include "frame.html";

if(!$reports) {
echo "No reports of 

include "frame-end.html";
