<? // abstract-merchant-gateway.php


class AbstractMerchantGateway
{
	function ccValidationTests() {
		// return javascript validation tests specific to this gateway
		/* e.g., 
		var nameTest = '';
		var fullname = ""+document.getElementById('x_first_name').value+' '+document.getElementById('x_first_name').value;
		if(fullname.length > 61) nameTest = "Name on card may not be any longer than 61 characters.";
		*/
		return '';
	}
	
	function ccValidationExtraArgs() {
		// return javascript extra validation arguments for  specific to this gateway
		// must start with a comma
		/* e.g., 
		,
		nameTest, '', 'MESSAGE'
		*/
		return '';
	}
	
	function supportsACH() {
		return false;
	}
	
	function supportsCC() {
		return true;
	}
}