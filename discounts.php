<? // discounts.php
$pageTitle = "Discounts";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "discount-fns.php";
locked('o-');

$max_rows = 999;

$columns = array('label'=>'Discount', 'start'=>'Starts', 'end'=>'Ends', 'amount'=>'Amount', 'duration'=>'Duration (days)', 'active'=>'Active');
$colKeys = array_keys($columns);
$columnSorts = null;
extract(extractVars('sort,newDiscount,deletedDiscount', $_REQUEST));
if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $sort = "$sort_key $sort_dir";
  if($sort_key != 'label') $sort .= ", label ASC";
}
$breadcrumbs = "&nbsp;<a href='discounted-visits.php'>Discounted Visits</a>";
// ***************************************************************************
include "frame.html";

if(isset($newDiscount)) {
	echo "<span class='pagenote'>Discount was successfully added.</span><p>";
}
if(isset($deletedDiscount)) {
	echo "<span class='pagenote'>Discount was deleted.</span><p>";
}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
echoButton('', "Add New Discount", "openConsoleWindow(\"editdiscount\", \"discount-edit.php\",700,500)");

$discounts = getDiscounts(false, $sort);
$data = array();
$rowClasses = array();
if($discounts) foreach($discounts as $discount) {
  $datum = $discount;
  if($discount['editable'])
  	$datum['label'] = fauxLink($datum['label'], "openConsoleWindow(\"editdiscount\", \"discount-edit.php?id={$discount['discountid']}\",700,500)')", 1);
  $datum['active'] = $datum['active'] ? 'Yes' : 'No';
  $datum['amount'] = $datum['ispercentage'] 
  			? $datum['amount'].'%' 
  		: ($datum['unlimiteddollar'] ? '(per visit) ' : '(total value) ').dollarAmount($datum['amount']);
  $datum['start'] = $datum['start'] ? shortDate(strtotime($datum['start'])) : '';
  $datum['end'] = $datum['end'] ? shortDate(strtotime($datum['end'])) : '';
  $data[] = $datum;
	$rowClasses[] = 'futuretask';
}
if(!$data) echo "<p>No Discounts found.";
else tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);


include "refresh.inc";				

?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function update(aspect, value) {
	refresh();
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
include "frame-end.html";
