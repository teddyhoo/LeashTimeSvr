<?
// killServices.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_REQUEST);

$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $id");
echo "Deleting services for client $client [$id]:<p>";


foreach(array('tblrecurringpackage','tblservicepackage','tblservice','tblappointment') as $tbl) {
  $sql = "delete from $tbl where clientptr = $id";
  echo "$sql<br>";
  doQuery($sql);
  printf("Records deleted: %d<p>\n", mysqli_affected_rows());
}
?>