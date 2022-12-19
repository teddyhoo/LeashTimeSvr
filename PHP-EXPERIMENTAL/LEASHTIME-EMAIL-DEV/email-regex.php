<?php

require_once "log-new.php";


function checkPasswordChar($rChar) {
	$request = $rChar;
	$regex_chars = array(
		'!' =>  '&\#33\;', 
		'$' => '&\#36\;', 
		'?' => '&\#63\;',
		'&' => '&\#38\;', 
		'#' => '&\#35\;',
		'%' => '&\#37\;',
		'@' => '&\#64\;',
		'=' => '&\#61\;',
		'+' => '&\#43\;',
		'*' => '&\#42\;',
		'^' => '&\#94\;',
		';' => '&\#59\;',
		'>' => '&\#62\;',
		'<' => '&\#60\;',
		'.' => '&\#46\;',
		':' => '&\#58\;',
	);
	foreach ($regex_chars as $key => $value) {
		if ($key == $rChar) {
			return $value;
		}

	}
	return $rChar;	
}
function passwordEscape($request) {
	$request_array = str_split($request, 1);
	$new_request = '';
	for ($i = 0; $i < count($request_array); $i++) {
		$newChar  = checkPasswordChar($request_array[$i]);
		$new_request .= $newChar;
	}
	return $new_request;
}
function regexCharEscapeASCII($request) {
	$regex_chars = array(
		'!' =>  '&\#33\;', 
		'$' => '&\#36\;', 
		'?' => '&\#63\;',
		//'&' => '&\#38\;', 
		'#' => '&\#35\;',
		'%' => '&\#37\;',
		'@' => '&\#64\;',
		'=' => '&\#61\;',
		'+' => '&\#43\;',
		'*' => '&\#42\;',
		'^' => '&\#94\;',
		//';' => '&\#59\;',
		'>---' => '&\#62\;',
		'<' => '&\#60\;',
		'.' => '&\#46\;',
		':' => '&\#58\;',
	);

	foreach ($regex_chars as $key => $value) {
		if ($key == $request) {
			return $value;
		}
	}
	return $request;	
}
function regexEscapeASCII($request) {
	$request_array = str_split($request, 1);
	$new_request = '';
	for ($i = 0; $i < count($request_array); $i++) {
		$newChar  = regexCharEscapeASCII($request_array[$i]);
		$new_request .= $newChar;
	}
	return $new_request;
}
function quoteEscape($str) {
	$str = preg_replace('/"/', '\"', $str);
	$str = preg_replace('/\'/', '\\\'', $str);
	return $str;
}
function allowedAddresses($adds) {
	if(!$adds) return array();
	$_ALLOWED_ADDRESSES = array();
	if(!$_ALLOWED_ADDRESSES) return $adds;
	foreach($adds as $add => $name) {
		if(!in_array($add, $_ALLOWED_ADDRESSES)) {
			unset($adds[$add]);
			$adds['test@leashtime.com'] = $name;
		}
	}
	return (array)$adds;
}
function getEmailAddress($emailInfo) {
	//$pattern = '/([a-zA-Z0-9.!#$%&’*+=?^_`{|}~-]+)\@([a-zA-Z0-9-]+)(?:\.[a-zA-Z0-9-]+)/';
	$pattern = '/([a-zA-Z0-9.!#$%&’*+=?^_`{|}~-]+)+\@([a-zA-Z0-9-]+)\.([a-zA-Z0-9-]+)\.?([a-zA-Z0-9-]+)?/';
	$matches = array();
	preg_match_all($pattern, $emailInfo,$matches);
	$matchcount = count($matches[0]);
	$multiemail = "";

	if ($matchcount == 1)
		return $matches[0][0];
	else if ($matchcount > 1) {
		foreach ($matches[0] as $email) {
			$multiemail = $multiemail . " " . $email;
		}
		return trim($multiemail);
	}
}

/*$emailInfo = "stacy.goodman@mlis.md.edu  ted@leashtime.com";
$emailInfo2 = "ted@leashtime.com";
$pattern = '/([a-zA-Z0-9.!#$%&’*+=?^_`{|}~-]+)+\@([a-zA-Z0-9-]+)\.([a-zA-Z0-9-]+)\.?([a-zA-Z0-9-]+)?/';
preg_match_all($pattern, $emailInfo,$matches);
if (count($matches) == 1) {
	echo $matches[0][0];
}
foreach($matches as $match) {
	echo $match[0]."\n";
}*/

$mainInfo['from'] = "ted@leashtime.com";
$mainInfo['replyto'] = "\"Ted Hooban\"";
$mainInfo['recipient'] = "teddyhoo@hotmail.com";
$mainInfo['host'] = "smtp.1and1.com";
$mainInfo['username'] = "notice@leashtime.com";
$mainInfo['password'] = passwordEscape('not11ce');
echo ("PASSWORD: " . $mainInfo['password'] . "\n");
$mainInfo['password'] = '"'.$mainInfo['password'].'"';
$mainInfo['subject'] =  passwordEscape("#e!!o &h!tb!rd? k^+mo&@be"); //")  . '"';
echo ("SUBJECT : " . $mainInfo['subject'] . "\n");
$mainInfo['subject'] = '"' . $mainInfo['subject'] . '"';
$mainInfo['body'] =  '
<br>LeashTime
<hr style="page-break-after:always;">
<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />
<style> body {background-image: none;}</style>

<style>
	.right {text-align:right;  font-size: 1.05em;}
	.bigger-right {font-size:1.1em;text-align:right;}
	.bigger-left {font-size:1.1em;text-align:left;}
	.sortableListHeader {
		font-size: 1.05em;
		padding-bottom: 5px; 
		border-collapse: collapse;
	}
	.sortableListCell {
		font-size: 1.05em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
	}
</style>
<table width="95%" border=0 bordercolor=red>
	<tr>
		<td>
			<br><a href="https://paypal.me/leashtime/204.55"><img src="https://leashtime.com/art/paypal_paynow.gif" width="180" height="64" border="0">
			<br>Pay Now with PayPal.</a>  Please put <span style="font-size:1.2em;font-weight:bold;background:lightblue;">@754</span></b> in the note field.
			<br>
			<center>
				<br>
				<br>Please make checks payable to:
				<br>
				<br>LeashTime LLC
				<br>P.O. Box 608
				<br>Vienna, VA 22180
				<br>
			</center>
		</td>
		<td align=right>
			<table width=200>
				<tr  >
	  				<td >Customer Number</td>
	  				<td id="">754</td>
	  			</tr>
				<tr  >
				  <td >Invoice Number</td><td id="">LT33101</td></tr>
				<tr  >
				  <td >Invoice Date</td><td id="">10/31/2022</td></tr>
				<tr  style="border: solid black 1px;">
				  <td >Amount Due:</td><td id="">$ 204.55</td></tr>
				<tr  style="border: solid black 1px;">
				  <td >Date Due:</td><td id="">Upon Receipt</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td>Professional Pet Sitters (Kramer"s Petsittin) (Jessica Abernathy)
				<br>No Address On Record
		</td>
		<td align=right>
			<img src="https://leashtime.com/dev/barcode/image.php?code=LT33101&style=196&type=C128A&width=120&height=60&xres=1&font=5">
		</td>
	</tr>
</table>

<p align=center>Please detach here and return with payment.<p><hr><p align=center><b>INVOICE</b><p>

<table width="95%">
	<tr>
		<td colspan=2>
			<div style="width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;">
			<span style="float:left;">Account Summary</span><span style="float:right;">Customer Number: 754</span>
			</div>
		</td>
	<tr>
		<td style="text-align:left;vertical-align:top;">Professional Pet Sitters (Kramer"s Petsittin) (Jessica Abernathy)
		<br>No Address On Record</td><td align=right><table width=60%><tr  >
	  <td >Previous Balance</td><td id="">$ 29.95</td></tr>
	<tr  >
	  <td >Payments & Credits</td>
	  <td id="">$ 0.00</td>
	</tr>
	<tr  >
	  <td >
	  	<label for="thisInvoiceTD">This Invoice LT33101</label>
	  </td>
	  <td id="thisInvoiceTD">$ 29.95</td>
	</tr>
	<tr  >
	  <td class="taxTD">Tax</td><td id="">$ 0.00</td></tr>
	<tr  style="border: solid black 1px;">
	  <td ><label for="totalAccountBalanceDueTD">Total Account Balance Due</label></td><td id="totalAccountBalanceDueTD">$ 204.55</td></tr>
	<tr  style="border: solid black 1px;">
	  <td >Date Due:</td><td id="">Upon Receipt</td></tr>
	</table></td></tr></table><p><div style="width:95%">
<div style="width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;"><span style="float:left;">Payments, Credits and Refunds since last invoice</span><span style="float:right;"></span></div>No payments, credits or refunds since last invoice.<p></div><table width="95%"><tr><td colspan=2><div style="width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;"><span style="float:left;">Current Invoice Charges as of: 10/01/2022</span><span style="float:right;">Invoice Number: LT33101</span></div></td></tr><tr><td><table WIDTH=100%  >
<thead><tr >
<th class="sortableListHeader">Date</th><th class="sortableListHeader"></th><th class="sortableListHeader">Service</th><th class="sortableListHeader"></th><th class="dollaramountheader_right">Charge</th></tr></thead><tbody>
<tr class="futuretaskEVEN"><td class="sortableListCell" colspan=2></td><td class="sortableListCell" style="font-weight:bold" colspan=2>Miscellaneous Charges</td><td class="right"></td></tr><tr class="futuretaskEVEN"><td class="sortableListCell" colspan=2>09/30/2022</td><td class="sortableListCell" colspan=2>LeashTime Service for October 2022</td><td class="right">$29.95</td><tr><td id="subtotalTD" colspan=5 style="text-align:right;font-weight:bold">Subtotal: $ 29.95</td><tr><tr class="futuretaskEVEN"><td colspan=4 style="text-align:left;"><b>Credits and Payments Applied: </b></td><td class="right">$0.00</td><tr>
</table>
</td></tr></table>
';
$mainInfo['body'] = quoteEscape($mainInfo['body']);
//$mainInfo["body'] = htmlentities("Hey script=thing&func=thing2");
//echo ('PHP BODY: ' . $mainInfo['body']);
$mainInfo['body'] = '"'.$mainInfo['body'].'"';
$mainInfo['html'] = "html";
$mainInfo['body'] = utf8_encode($mainInfo['body']);
$request = 'python3 mail-relay.py ' . 
		$mainInfo['from'] . ' ' . 
		$mainInfo['replyto']. ' ' . 
		$mainInfo['recipient'] . ' ' . 
		$mainInfo['host'] . ' ' . 
		$mainInfo['username'] . ' ' . 
		$mainInfo['password'] . ' ' . 
		$mainInfo['subject'] . ' ' . 
		$mainInfo['body'] . ' ' . 
		$mainInfo['html'];
//$request = utf8_encode($request);
requestWrite("\n----------------------------\nREQUEST\n--------------------------\n" . $request);
//echo ("REQUEST: \n_________\n$request\n");
$request2 = 'python3 mail-relay.py "LeashTime" "support@leashtime.com" "teddyhoo@hotmail.com" smtp.1and1.com notice@leashtime.com "not11ce" "LEASHTIME BILLING - OCTOBER 2022" "<br>Hi Professional Pet Sitters (Kramer\'s Petsittin) (Jessica Abernathy),<br><br>Attached is the invoice for LeashTime usage during OCTOBER 2022. Payments will be processed on  NOVEMBER 1, 2022. <br><br>IF YOU HAVE ELECTRONIC PAYMENTS SET UP, WE ARE GOING TO RUN THE TOTAL AMOUNT OF THIS INVOICE ON NOVEMBER 1, 2022. If you want to sign up for electronic transaction processing (either credit card or electronic check), please contact support@leashtime.com for instructions. Once again, we really appreciate all the support you have given us and we hope to continue to meet your expectations in the future!<br><br>If you prefer to pay via PayPal, payment should be made to <b>ted@leashtime.com</b> (making sure not to include any spaces around the address when you enter it).  Please put the phrase<br><br>LeashTime Service (@754) <br><br>in the note field when you do so, to ensure your
payment is properly registered to your LeashTime account.<br><br>Our mailing address:<br><br><address>LEASHTIME, LLC</address><address> </address><address>601 N BUCHANAN ST</address><address> </address><address>ARLINGTON, VA 22203</address><br><br>Please remember to send support requests to: support@leashtime.com.<br><br>Sincerely,<br><br>LeashTime<hr style=\'page-break-after:always;\'><link rel=\"stylesheet\" href=\"https://leashtime.com/style.css\" type=\"text/css\" />
<link rel=\"stylesheet\" href=\"https://leashtime.com/pet.css\" type=\"text/css\" />
<style> body {background-image: none;}</style>

        <style>
        .right {text-align:right;  font-size: 1.05em;}
        .bigger-right {font-size:1.1em;text-align:right;}
        .bigger-left {font-size:1.1em;text-align:left;}
        .sortableListHeader {
                font-size: 1.05em;
                padding-bottom: 5px;
                border-collapse: collapse;
        }

        .sortableListCell {
                font-size: 1.05em;
                padding-bottom: 4px;
                border-collapse: collapse;
                vertical-align: top;
        }
        </style><table width=\'95%\' border=0 bordercolor=red><tr><td><br><a href=\"https://paypal.me/leashtime/204.55\"><img src=\"https://leashtime.com/art/paypal_paynow.gif\" width=\"180\" height=\"64\" border=\"0\"><br>Pay Now with PayPal.</a>  Please put <span style=\'font-size:1.2em;font-weight:bold;background:lightblue;\'>@754</span></b> in the note field.<br><center><br><br>Please make checks payable to:<br><br>LeashTime LLC<br>P.O. Box 608<br>Vienna, VA 22180<br></center></td><td align=right><table width=200><tr  >
  <td >Customer Number</td><td id=\'\'>754</td></tr>
<tr  >
  <td >Invoice Number</td><td id=\'\'>LT33101</td></tr>
<tr  >
  <td >Invoice Date</td><td id=\'\'>10/31/2022</td></tr>
<tr  style=\'border: solid black 1px;\'>
  <td >Amount Due:</td><td id=\'\'>&#36; 204.55</td></tr>
<tr  style=\'border: solid black 1px;\'>
  <td >Date Due:</td><td id=\'\'>Upon Receipt</td></tr>
</table></td></tr><tr><td>Professional Pet Sitters (Kramer\'s Petsittin) (Jessica Abernathy)<br>No Address On Record</td><td align=right><img src=\'https://leashtime.com/dev/barcode/image.php?code=LT33101&style=196&type=C128A&width=120&height=60&xres=1&font=5\'></td></tr></table><p align=center>Please detach here and return with payment.<p><hr><p align=center><b>INVOICE</b><p><table width=\'95%\'><tr><td colspan=2><div style=\'width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;\'><span style=\'float:left;\'>Account Summary</span><span style=\'float:right;\'>Customer Number: 754</span></div></td><tr><td style=\'text-align:left;vertical-align:top;\'>Professional Pet Sitters (Kramer\'s Petsittin) (Jessica Abernathy)<br>No Address On Record</td><td align=right><table width=60%><tr  >
  <td >Previous Balance</td><td id=\'\'>&#36; 29.95</td></tr>
<tr  >
  <td >Payments &amp; Credits</td><td id=\'\'>&#36; 0.00</td></tr>
<tr  >
  <td ><label for=\'thisInvoiceTD\'>This Invoice LT33101</label></td><td id=\'thisInvoiceTD\'>&#36; 29.95</td></tr>
<tr  >
  <td class=\'taxTD\'>Tax</td><td id=\'\'>&#36; 0.00</td></tr>
<tr  style=\'border: solid black 1px;\'>
  <td ><label for=\'totalAccountBalanceDueTD\'>Total Account Balance Due</label></td><td id=\'totalAccountBalanceDueTD\'>&#36; 204.55</td></tr>
<tr  style=\'border: solid black 1px;\'>
  <td >Date Due:</td><td id=\'\'>Upon Receipt</td></tr>
</table></td></tr></table><p><div style=\'width:95%\'>
<div style=\'width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;\'><span style=\'float:left;\'>Payments, Credits and Refunds since last invoice</span><span style=\'float:right;\'></span></div>No payments, credits or refunds since last invoice.<p></div><table width=\'95%\'><tr><td colspan=2><div style=\'width:100%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;\'><span style=\'float:left;\'>Current Invoice Charges as of: 10/01/2022</span><span style=\'float:right;\'>Invoice Number: LT33101</span></div></td></tr><tr><td><table WIDTH=100%  >
<thead><tr >
<th class=\'sortableListHeader\'>Date</th><th class=\'sortableListHeader\'>&nbsp;</th><th class=\'sortableListHeader\'>Service</th><th class=\'sortableListHeader\'>&nbsp;</th><th class=\'dollaramountheader_right\'>Charge</th></tr></thead><tbody>
<tr class=\'futuretaskEVEN\'><td class=\'sortableListCell\' colspan=2></td><td class=\'sortableListCell\' style=\'font-weight:bold\' colspan=2>Miscellaneous Charges</td><td class=\'right\'></td></tr><tr class=\'futuretaskEVEN\'><td class=\'sortableListCell\' colspan=2>09/30/2022</td><td class=\'sortableListCell\' colspan=2>LeashTime Service for October 2022</td><td class=\'right\'>&#36;&nbsp;29.95</td><tr><td id=\'subtotalTD\' colspan=5 style=\'text-align:right;font-weight:bold\'>Subtotal: &#36; 29.95</td><tr><tr class=\'futuretaskEVEN\'><td colspan=4 style=\'text-align:left;\'><b>Credits and Payments Applied: </b></td><td class=\'right\'>&#36;&nbsp;0.00</td><tr>
</table>
</td></tr></table>
" html';
$output = shell_exec($request);
echo($output);	 
