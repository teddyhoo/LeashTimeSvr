<? //appointment-discount.php
// ids may be one id or id1,id2,...
// callers: calendar-package-irregular.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "discount-fns.php";

locked('o-');

$appts = fetchAssociations("SELECT * FROM tblappointment WHERE appointmentid IN ({$_GET['ids']})");

$currentDiscount = getCurrentClientDiscount($appts[0]['clientptr']);
$discount = $_GET['discount'];
if(strpos($discount, '|')) $discount = substr($discount, 0, strpos($discount, '|'));
if($discount != $currentDiscount['discountptr'])
	$scheduleDiscount = 
		array('clientptr'=>$appts[0]['clientptr'], 'discountptr'=>$discount, 'start'=>date('Y-m-d'), 'memberid'=>$_GET['memberid']);
else  $scheduleDiscount = $currentDiscount;

$numDiscounts = applyScheduleDiscountWhereNecessary($appts);
$msg = !is_numeric($numDiscounts) ? "No discount applied: $numDiscounts" : "Discount applied to $numDiscounts visit".($numDiscounts == 1 ? '.' : 's.');
echo "MESSAGE:$msg";