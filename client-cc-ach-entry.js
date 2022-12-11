function checkCC(el) {
	var ccnum = ccNumLooksValid(el.value);
	if(!ccnum) {
		alert("Credit card number is invalid.");
		return;
	}
	el.value = ccnum;
	if(document.getElementById('company').value) return;
	var v = el.value.replace(/[^0-9]+/g, '');
	var guess = guessCreditCardCompany(v);
	if(guess && confirm('Is this a '+guess+'?'))
		document.getElementById('company').value = guess;
}

function warnIfCCFormatInvalid(el) {
	if(!ccNumLooksValid(el.value)) {
		alert("Credit card number is invalid.");
		return;
	}
}

function ccNumLooksValid(str) {
  var objRegExp;
  str = str.replace(/[ -]+/g, '');
  objRegExp = /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/;
	return objRegExp.test(str) ? str : false;
}


function guessCreditCardCompany(str) {
/*
		* Visa:  All Visa card numbers start with a 4. New cards have 16 digits. Old cards have 13.
    * MasterCard:  All MasterCard numbers start with the numbers 51 through 55. All have 16 digits.
    * American Express: ^3[47][0-9]{13}$ American Express card numbers start with 34 or 37 and have 15 digits.
    * Diners Club: ^3(?:0[0-5]|[68][0-9])[0-9]{11}$ Diners Club card numbers begin with 300 through 305, 36 or 38. All have 14 digits. There are Diners Club cards that begin with 5 and have 16 digits. These are a joint venture between Diners Club and MasterCard, and should be processed like a MasterCard.
    * Discover: ^6(?:011|5[0-9]{2})[0-9]{12}$ Discover card numbers begin with 6011 or 65. All have 16 digits.
    * JCB: ^(?:2131|1800|35\d{3})\d{11}$ JCB cards beginning with 2131 or 1800 have 15 digits. JCB cards beginning with 35 have 16 digits. 	
 */
  var objRegExp;
  str = numbersOnly(str);
  
  objRegExp = /^4[0-9]{12}(?:[0-9]{3})?$/;
 	if(objRegExp.test(str)) return 'Visa';
  objRegExp = /^5[1-5][0-9]{14}$/;
 	if(objRegExp.test(str)) return 'MasterCard';
  objRegExp = /^3[47][0-9]{13}$/;
 	if(objRegExp.test(str)) return 'American Express';
  objRegExp = /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/;
 	if(objRegExp.test(str)) return 'Diners Club';
  objRegExp = /^6(?:011|5[0-9]{2})[0-9]{12}$/;
 	if(objRegExp.test(str)) return 'Discover';
  objRegExp = /^(?:2131|1800|35\d{3})\d{11}$/;
 	if(objRegExp.test(str)) return 'JCB';
  return '';
}

function useHomeAddress() {
	var keys = 'address,city,state,zip,phone'.split(',');
	for(var i=0;i<keys.length;i++)
		document.getElementById('x_'+keys[i]).value = document.getElementById('h_'+keys[i]).value;
}

function replaceCC() {
	if(document.getElementById('ccformtable').style.display=='none') {
		document.getElementById('ccformtable').style.display='inline';
		document.getElementById('replaceCCButton').value='Save New Credit Card';
		document.getElementById('replaceCCButton').onclick=replaceCC;
	}
	else if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('ccAction').value='replace';
		document.cceditor.submit();
	}
}

function createCC() {
  if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('ccAction').value='create';
		document.cceditor.submit();
	}
}

var clientOwnAccountURL = 'client-own-account.php?';

function dropCC() {
	// changing form action in IE9 is busted, so drop was reworked
	//document.getElementById('ccAction').value='drop';
	//document.cceditor.action = 'client-own-account.php'; // will be set differently for SOlveras
	//document.cceditor.submit();
	document.location.href = clientOwnAccountURL+'ccAction=dropCC';
}

function dropACH() {
	// changing form action in IE9 is busted, so drop was reworked
	//document.getElementById('ccAction').value='dropACH';
	//document.acheditor.action = 'client-own-account.php'; // will be set differently for SOlveras
	//document.acheditor.submit();
	document.location.href = clientOwnAccountURL+'ccAction=dropACH';
}

function ccFormArgsToTest() {
	var args;
	var guess = guessCreditCardCompany(document.getElementById('x_card_num').value);
	document.getElementById('x_card_num').value = numbersOnly(document.getElementById('x_card_num').value);
	var ccCompanyTest = guess == elementValue(document.getElementById('company')) ? '' : 'The wrong credit card company is selected';
	if(true/*!document.getElementById('ccid').value*/)
	  args = [
		  'x_card_num', '', 'R',
		  'x_card_num', '', 'validCC',
		  //'x_card_code', '', 'R',
		  'x_card_code', '3', 'MINLENGTH',
		  'x_card_code', '4', 'MAXLENGTH'
		  ];
	else args = [];
	var extraArgs = [
		  'x_exp_date', '', 'R',
		  'expmonth', '', 'R',
		  'expyear', '', 'R',
		  'company', '', 'R',
		  ccCompanyTest, '', 'MESSAGE'];
	setPrettynames(
					'x_card_num', 'Credit Card Number', 'x_card_code', 'Credit Card Verification Number',
					'x_exp_date','Expiration','expmonth','Expiration Month', 'expyear', 'Expiration Year', 'company', 'Credit Card Company');
		  
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	if(true /*!document.getElementById('useclientinfo').checked*/) {
		setPrettynames('x_first_name','First Name','x_last_name','Last Name', 'x_address', 'Address', 'x_city', 'City',
										'x_state', 'State', 'x_zip', 'ZIP', 'x_country', 'Country', 'x_phone', 'Phone');

		extraArgs = [
				'x_first_name', '', 'R',
				'x_last_name', '', 'R',
				'x_address', '', 'R',
				'x_city', '', 'R',
				'x_state', '', 'R',
				'x_zip', '', 'R',
				'x_country', '', 'R',
				'x_phone', '', 'R'
				];
		for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	}
	
	
	
	return args;
}