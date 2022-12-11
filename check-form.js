// MM_validateForm('NAME','','R',...)
function MM_validateForm() { //v3.0
  return MM_validateFormArgs(MM_validateForm.arguments);
}

function MM_validateEnhancedForm(args) {
	return MM_validateAFormWithAddedAttributes(args); // See bottom of file
}


function MM_validateFormArgs(args) { //v3.0
	var errors = MM_generateErrors(args);
	if(errors)
		alert('Warning:\n'+errors);
	return (errors == '')
}

function MM_validateFormAndContinue(args) { //v3.0
	var errors = MM_generateErrors(MM_validateFormAndContinue.arguments);
	if(errors)
		return confirm('Found these problems.  Continue?\n'+errors);
	return (errors == '')
}

function MM_generateErrors(args) { //v3.0
  var i,p,q,nm,test,num,min,max,errors='', val;
  for (i=0; i<(args.length-2); i+=3) { 
		test=args[i+2]; 
		//alert(test+' '+args[i]);
		if(test == 'MESSAGE') {
			if(args[i] != '' && args[i] != null && args[i] != false ) errors+='- '+args[i]+'\n';
			continue;
		}
			
		val= args[i] ? MM_findObj(args[i]) : null; 
    if (val && !elementIsHidden(val)) { nm=args[i]; 
		if(test.indexOf('inseparable')!=-1) {
			val=elementValue(val);
			val2=MM_findObj(args[i+1]);
		 	if(val2) val2 = elementValue(val2);
			if((val && !val2) || (!val && val2)) errors+='- both '+prettyName(nm)+' and '+prettyName(args[i+1])+' must be supplied, or neither.\n';
		}
    else if(test == 'RRADIO') {
			if(!radioNamedIsChecked(nm))
				errors+='- '+prettyName(nm)+' must be specified.\n';
		}
    else if ((val=elementValue(val))!="") {

      var neg = (""+args[i+1]).toUpperCase() == "NOT";
      if (test.indexOf('isEmail')!=-1) {
        if (!validEmail(val)) {
					if(validEmail(trim(val))) errors+='- '+prettyName(nm)+' must not have leading or trailing spaces.\n';
					else errors+='- '+prettyName(nm)+' must contain an e-mail address.\n';
				}
      }
      else if (test.indexOf('isMultiEmail')!=-1) {
        if (!validMultiEmails(val)) {
					if(validMultiEmails(trim(val))) errors+='- '+prettyName(nm)+' must not have leading or trailing spaces.\n';
					else errors+='- '+prettyName(nm)+' must contain only e-mail addresses, separated by commas.\n';
				}
      }
      
      else if (test.indexOf('isURL')!=-1) {
        if (!validURL(val)) errors+='- '+prettyName(nm)+' must contain a URL.\n';
      }
      else if(test.indexOf('isDateAfter')!=-1) { //isPastDate isDateAfter
        val2=args[i+1];
        neg = test.indexOf('Not') > 0; // isDateAfterNot
        if(neg) {if(isDateAfter(val, val2)) errors+='- '+prettyName(nm)+' must be a date on or before '+args[i+1]+'.\n';}
        else if(!isDateAfter(val, val2)) errors+='- '+prettyName(nm)+' must be a date after '+args[i+1]+'.\n';
      }
      else if(test.indexOf('isDateBefore')!=-1) {
        val2=args[i+1];
        neg = test.indexOf('Not') > 0; // isDateBeforeNot
        if(neg) {if(isPastDate(val, val2)) errors+='- '+prettyName(nm)+' must be a date on or after '+args[i+1]+'.\n';}
        else if(!isPastDate(val, val2)) errors+='- '+prettyName(nm)+' must be a date before '+args[i+1]+'.\n';
      }
      else if(test == 'isDate') {
				var correctFormat = typeof localDateFormat != 'undefined' ? localDateFormat : 'MM/DD/YYYY';
        if(!validateUSDate(val)) errors+='- '+prettyName(nm)+' must contain a date in the form '+correctFormat+'.\n';
      }
      else if(test.indexOf('isPastDate')!=-1) {
        if(neg) {if(isPastDate(val)) errors+='- '+prettyName(nm)+' must be a future date or today.\n';}
        else if(!isPastDate(val)) errors+='- '+prettyName(nm)+' must be a date before today.\n';
      }
      else if(test.indexOf('isFutureDate')!=-1) {
        if(neg) {if(isFutureDate(val)) errors+='- '+prettyName(nm)+' must be a past date or today.\n';}
        else if(!isFutureDate(val)) errors+='- '+prettyName(nm)+' must be a date after today.\n';
      }
      else if(test.indexOf('datesInOrder')!=-1) {
        val2=MM_findObj(args[i+1]);
        val2 = val2 ? val2.value : 0;
        if(!datesInOrder2(val, val2)) errors+='- '+prettyName(nm)+' must be earlier or the same as '+prettyName(args[i+1])+'.\n';
      }
      else if(test.indexOf('datesInOrder2')!=-1) {
        val2=MM_findObj(args[i+1]);
        val2 = val2 ? val2.value : 0;
        if(!datesInOrder2(val, val2)) errors+='- '+prettyName(nm)+' must be earlier or the same as '+prettyName(args[i+1])+'.\n';
      }
      else if(test.indexOf('isDateTime')!=-1) {
        if(!validateUSDateTime(val)) errors+='- '+prettyName(nm)+' must contain a date in the form MM/DD/YYYY hh:mm.\n';
      }
      else if(test.indexOf('>')!=-1) {
        //val = Number(val);
        val2=MM_findObj(args[i+1]);
        val2 = val2 ? Number(val2.value) : 0;
        var equable = test.indexOf('=') > 0 ? "or equal to " : "";
        var result = equable ? (val >= val2) : (val > val2);
        if(!result) errors+='- '+prettyName(nm)+' must be greater than '+equable+prettyName(args[i+1])+'.\n';
      }
      else if(test.indexOf('<')!=-1) {
        val2=MM_findObj(args[i+1]);
        val2 = val2 ? Number(val2.value) : 0;
        val = Number(val);
        var equable = test.indexOf('=') > 0 ? "or equal to " : "";
        var result = equable ? (val <= val2) : (val < val2);
        if(!result) errors+='- '+prettyName(nm)+' must be less than '+equable+prettyName(args[i+1])+'.\n';
      }
      else if(test == 'MIN') {
        val2=args[i+1];
        if(Number(""+val) < Number(""+val2)) errors+='- '+prettyName(nm)+' must be no smaller than '+val2+'.\n';
      }
      else if(test == 'MAX') {
        val2=args[i+1];
        if(Number(""+val) > Number(""+val2)) errors+='- '+prettyName(nm)+' must be no larger than '+val2+'.\n';
      }
      else if(test.indexOf('EQ')!=-1) {
        val2=MM_findObj(args[i+1]);
        if(val2) val2 = val2.value;
        if(val != val2) errors+='- '+prettyName(nm)+' and '+prettyName(args[i+1])+' must be the same.\n';
      }
      else if(test.indexOf('PHONE')!=-1) {
        val2=args[i+1];
        if(!isValidPhoneForm(val,val2)) {
					var constraint = 'at least '+val2+' digits long';
					if(val2 == 'FULL_US') constraint = 'including the area code';
					errors+='- '+prettyName(nm)+' must be a phone number '+constraint+'.\n';
				}
      }
      else if(test.indexOf('MINLEN')!=-1) {
        val2=args[i+1];
        if(val.length < val2) errors+='- '+prettyName(nm)+' must be at least '+val2+' characters long.\n';
      }
      else if(test.indexOf('MAXLEN')!=-1) {
        val2=args[i+1];
        if(val.length > val2) errors+='- '+prettyName(nm)+' must be at MOST '+val2+' characters long.\n';
      }
      else if(test == 'PERCENTORNUMBER') {
        if(!isUnsignedFloatOrPercent(val))
          errors+='- '+prettyName(nm)+' must be a percentage or (unsigned) number.\n';
      }
      else if(test == 'PERCENT') {
        if(!isPercent(val))
          errors+='- '+prettyName(nm)+' must be a percentage.\n';
      }
      else if(test == 'UNSIGNEDFLOAT') {
        if(!isUnsignedFloat(val))
          errors+='- '+prettyName(nm)+' must be a positive number.\n';
      }
      else if(test == 'FLOAT') {
        if(!isFloat(val))
          errors+='- '+prettyName(nm)+' must be a number.\n';
      }
      else if(test == 'UNSIGNEDINT') {
        if(!isUnsignedInt(val))
          errors+='- '+prettyName(nm)+' must be a positive integer or zero.\n';
      }
      else if(test == 'INT') {
        if(!isInt(val))
          errors+='- '+prettyName(nm)+' must be a an integer (no decimal point).\n';
      }
      else if(test.indexOf('validPassword')!=-1) {
        if(!validPasswordForm(val))
          errors+='- '+prettyName(nm)+' must be between 6 and 12 characters, with at least one letter and one digit.\n';
      }
      else if(test =='validCC') {
        if(!validCreditCardNumber(val))
          errors+='- '+prettyName(nm)+' must be a valid credit card number.\n';
      }
      else if(test =='ALPHASPACESONLY') {
        if(!alphaSpacesOnly(val))
          errors+='- '+prettyName(nm)+' must be letters only (spaces allowed).\n';
      }
      else if(test =='ALPHAONLY') {
        if(!alphaOnly(val))
          errors+='- '+prettyName(nm)+' must be letters only.\n';
      }
      else if(test =='NONEMPTY') {
        if(trim(val) == '')
          errors+='- '+prettyName(nm)+' is required.\n';;
      }
      else if (test.charAt(0) != 'R') { num = parseInt(val);
        /*if (val!=''+num && (val!=''+num+'.0')) {
					//alert("Test: ["+test+"] arg: ["+args[i]+"]");
					errors+='- '+prettyName(nm)+' must contain a number.\n';
				}*/
        if (test.indexOf('inRange') != -1) { p=test.indexOf(':');
          min=test.substring(8,p); max=test.substring(p+1);
          if (num<min || max<num) errors+='- '+prettyName(nm)+' must contain a number between '+min+' and '+max+'.\n';
    } } 
    }
    else if (test == 'RIFF') {
			val2=MM_findObj(args[i+1]);
			if(val2) val2 = val2.value;
			if(val2) errors += '- '+prettyName(args[i])+' is required when '+prettyName(args[i+1])+' is supplied.\n';
		}
    else if (test == 'RIFFRADIO') {
			val2=MM_findObj(args[i+1]);
			if(val2) val2 = val2.checked;
			if(val2) errors += '- '+prettyName(args[i])+' is required when '+prettyName(args[i+1])+' is checked.\n';
		}
    else if (test == 'R') errors += '- '+prettyName(nm)+' is required.\n'; }

  } 
  //if (errors) alert('Warning:\n'+errors);
  document.MM_returnValue = (errors == '');
  return errors;
}

function alphaSpacesOnly(val) {
	var regex = /^[A-Za-z ]+$/
	return regex.test(val);
}

function alphaOnly(val) {
	var regex = /^[A-Za-z]+$/
	return regex.test(val);
}

function radioNamedIsChecked(nm) {
	for(var f=0; f<document.forms.length; f++) {
		for(var i=0; i<document.forms[f].elements.length; i++) {
			var el = document.forms[f].elements[i];
			if(el.type=='radio' && el.name == nm && el.checked)
				return true;
		}
	}
}

function isPercent(src) {
	var regex = /^(100|[0-9]{1,2})(\.[0-9]{1,2})?\%$/
	return regex.test(src);
}

function isUnsignedFloatOrPercent(src) {
	return isPercent(src) || isUnsignedFloat(src);
}

function isUnsignedInt(src) {
	var regex = /^[0-9]*$/
	return regex.test(src);
}

function isInt(src) {
	var regex = /^[+-]?[0-9]*$/
	return regex.test(src);
}

function isUnsignedFloat(src) {
	var regex = /^[0-9]*\.?[0-9]+$/
	return regex.test(src);
}

function isFloat(src) {
	var regex = /^[+-]?[0-9]*\.?[0-9]+$/
	return regex.test(src);
}

function validEmail(src) {
  //var emailReg = "^[\\w-_\.+]*[\\w-_\.]\@([\\w]+\\.)+[\\w]+[\\w]$";
  //var regex = new RegExp(emailReg);
  //return regex.test(src);
  //var emailFilter=/^.+@.+\..{2,3,4,6}$/;
  //return emailFilter.test(src);
  var regex = /^[a-zA-Z0-9._%+-`'`&]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,20}$/;  // checks for ' character.  new TLDs such as "photography" and "cancerresearch"
  return regex.test(src)
  				&& src.indexOf('^') == -1
  				&& src.indexOf(',') == -1
  				&& src.indexOf(':') == -1
  				&& src.split('@').length == 2;

}

function validMultiEmails(src) {
	src = ""+src;
	var adds = src.split(',');
	for(var i=0; i< adds.length; i++)
		if(!validEmail(trim(adds[i])))
			return false;
	return true;
}

/*
function isEmailValid($email) {
	$emailpat = "/^[a-zA-Z0-9._%+-`'`&]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/";  // checks for ' character
	return preg_match($emailpat, $email)
					&& strpos($email, '^') === FALSE  // included because the ampersand inexplicably allows circumflexes
					&& count(explode('@', $email)) == 2; // deny multple @'s
}

*/
	
function validURL(src) {
  //var regex = /^(http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:/~\+#]*[\w\-\@?^=%&amp;/~\+#])?$/;
  // what about:
   var regex =  /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i;
	// 'feh
  //var regex = /^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?$/;
  return regex.test(src);

}
	
function elementIsHidden(el) {
	thisEl = el.length ? el[0] : el;
	for(thisEl = el; thisEl; thisEl = thisEl.parentElement) {
	  if (thisEl.style && (thisEl.style.visibility == 'hidden')) return true;
	}
	return false;
}
	

var prettyNames = Array();
function setPrettynames() {
  var i, p=prettyNames.length,args=setPrettynames.arguments;
  for(i=0; i<(args.length-1); i+=2) {
    prettyNames[p] = args[i];
    prettyNames[p+1] = args[i+1];
    p += 2;
  }
}


function elementValue(radioObj) {
	if(!radioObj)
		return "";
	if(typeof radioObj.length == 'undefined')
	  return radioObj.value;
	else if(radioObj.type == 'select-one')
	  return radioObj.options[radioObj.selectedIndex].value;
	var radioLength = radioObj.length;
	if(radioLength == undefined)
		if(radioObj.checked)
			return radioObj.value;
		else
			return "";
	for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked) {
			return radioObj[i].value;
		}
	}
	return "";
}

function prettyName(key) {
  var i;
  for(i=0; i<(prettyNames.length-1); i+=2) {
    if(prettyNames[i] == key) return prettyNames[i+1];
  }
  return key;
}

function isValidPhoneForm(strValue,minDigits) {
	var charsAllowed = "0123456789()-./ ";
	var numDigits = 0;
	minDigits = minDigits == 'FULL_US' ? 10 : minDigits;
	for(var i=0;i<strValue.length;i++) {
		var index = charsAllowed.indexOf(strValue.charAt(i));
		if(index < 0) return false;
		if(index < 10) numDigits++;
	}
	return (numDigits >= minDigits);
}


function validPasswordForm(strValue) {
  if(strValue.length < 6) return false;
  if(strValue.length > 12) return false;
  var num=0,alpha=0;
 for(i=0;i<strValue.length;i++) {
    var c=strValue.charAt(i);
    if((c>='a' && (c <= 'z')) || (c>='A' && (c <= 'Z'))) alpha++;
    else if(c>='0' && (c <= '9')) num++;
    if(num > 0 && (alpha > 0)) return true;
  }
  return false;

/* at least 6 chars, at least
^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}$
*/
  //var objRegExp = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{4,8}$/
  //return objRegExp.test(strValue);
}

function isPastDate(strValue, comparisonDateString) {
  var arrayDate = mdy(strValue);
  if(!arrayDate) return false;
  var today = comparisonDateString ? mdy(comparisonDateString) : todayArray();
  if(today[2] > arrayDate[2]) return true;
  else if(today[2] < arrayDate[2]) return false;
  if(today[0] > arrayDate[0]) return true;
  else if(today[0] < arrayDate[0]) return false;
  if(today[1] > arrayDate[1]) return true;
  else if(today[1] < arrayDate[1]) return false;
  return false;  // it's today
}

function isDateAfter(strValue, comparisonDateString) {
  if(isPastDate(strValue, comparisonDateString)) return false;
  if(""+mdy(comparisonDateString) == ""+mdy(strValue)) return false;
  return true;
}


function isFutureDate(strValue) {
  if(isPastDate(strValue)) return false;
  if(""+todayArray() == ""+mdy(strValue)) return false;
  return true;
}

function todayArray() {
  var date = new Date();
  var today = new Array(3);
  today[0] = date.getMonth()+1;
  today[1] = date.getDate();
  today[2] = date.getFullYear();
  return today;
}

function datesInOrder2OLD(d1String,d2String) { // DOES NOT WORK WITH dd.mm.yyyy
	//alert(d1String+">"+d2String);
	return Date.parse(d1String) <= Date.parse(d2String);
}

function datesInOrder2(d1String,d2String) {
  d1 = mdy(d1String);
  d2 = mdy(d2String);
  if(!d1 || !d2) return true;
  d1 = d1[0]+"/"+d1[1]+"/"+d1[2];
  d2 = d2[0]+"/"+d2[1]+"/"+d2[2];
  return Date.parse(d1) <= Date.parse(d2);
}



function datesInOrder(d1String,d2String) {
  d1 = mdy(d1String);
  d2 = mdy(d2String);
  if(!d1 || !d2) return true;
  if(d2[2] >= d1[2]) return true;
  else if(d2[2] < d1[2]) return false;
  if(d2[0] >= d1[0]) return true;
  else if(d2[0] < d1[0]) return false;
  if(d2[1] >= d1[1]) return true;
  else if(d2[1] < d1[1]) return false;
  return true;
}

function mdy(strValue) {
	// m/d/Y or d.m.Y
  //var objRegExp = /^\d{1,2}(\-|\/|\.)\d{1,2}\1\d{4}$/
  var objRegExp = /^\d{1,2}(\/|\.|-)\d{1,2}\1\d{4}$/
	var japanRegExp = /^\d{4}(\/|\.|-)\d{1,2}\1\d{1,2}$/  // allow for Japanese 2016/12/31
	var japanese = japanRegExp.test(strValue) ? true : false;
  //check to see if in correct format
  if(!objRegExp.test(strValue) && !japanese) {
		return null; //doesn't match pattern, bad date
	}
  else{
    var arrayDate = strValue.split(RegExp.$1); //split date into month, day, year
    arrayDate[0] = parseInt(arrayDate[0],10);
    arrayDate[1] = parseInt(arrayDate[1],10);
    arrayDate[2] = parseInt(arrayDate[2],10);
    if(strValue.indexOf('/') == -1) { // dotted dates: 31.02.2016
			var m = arrayDate[0];
			arrayDate[0] = arrayDate[1];
			arrayDate[1] = m;
		}
		else if(japanese) {
			arrayDate = new Array(arrayDate[1], arrayDate[2], arrayDate[0]);
		}
    return arrayDate;
  }
}

function dbDate(strValue) {
	var arr = mdy(strValue);
	if(arr) 
		return arr[2]+'-'
					 +(arr[0] < 10 ? '0' : '')+arr[0]
					 +'-'+(arr[1] < 10 ? '0' : '')+arr[1];
}

function makeADate(strValue) {
	// strValue is in either  American or World format
	var arr = mdy(strValue);
	var dt = new Date();
	dt.setDate(arr[1]);
	dt.setMonth(arr[0]-1);
	dt.setFullYear(arr[2]);
	return dt;
}

function MM_findObj(n, d) { //v3.0
	if(n == null) return null;
  var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
alert(n+': ['+p+'] parent.frames.length='+parent.frames.length);
    d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
  if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
  for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document); return x;
}

function validTime(t) {
	var parts = t.split(':');
	var intregex = /^[0-9]*$/
	return (parts.length == 2
		 && intregex.test(parts[0])
		 && intregex.test(parts[1])
		 && parts[1].length == 2
		 && parseInt(parts[0]) < 24
		 && parseInt(parts[1]) < 60);
}

function militaryTime(t) {
	// assume t is valid time
	if(t == null || t == '') return t;
	t = t.toUpperCase();
	if(t.indexOf('AM') == -1 && t.indexOf('PM') == -1) return t;
	var miltime = t.substring(0, t.indexOf(' '));
	var parts = miltime.split(':');
	if(t.indexOf('AM') != -1 && parts[0] == 12) parts[0] = '00';
	else if(t.indexOf('PM') != -1 && parts[0] < 12) parts[0] = parseInt(parts[0])+12;
	if(parseInt(parts[0]) < 10) parts[0] = '0'+parseInt(parts[0]);
	return ''+parts[0]+':'+parts[1];
}

function validateUSDate( strValue ) {
/************************************************
DESCRIPTION: Validates that a string contains only
    valid dates with 2 digit month, 2 digit day,
    4 digit year. Date separator can be ., -, or /.
    Uses combination of regular expressions and
    string parsing to validate date.
    Ex. mm/dd/yyyy or mm-dd-yyyy or mm.dd.yyyy

PARAMETERS:
   strValue - String to be tested for validity

RETURNS:
   True if valid, otherwise false.

REMARKS:
   Avoids some of the limitations of the Date.parse()
   method such as the date separator character.
*************************************************/
  var arrayDate = mdy(strValue);
  //check to see if in correct format
  if(!arrayDate) {
    return false; //doesn't match pattern, bad date
	}
  else{
    var intDay = arrayDate[1];
    var intYear = arrayDate[2];
    var intMonth = arrayDate[0];

	//check for valid month
	if(intMonth > 12 || intMonth < 1) {
		return false;
	}

    //create a lookup for months not equal to Feb.
    //var arrayLookup = { 1 : 31,3 : 31, 4 : 30,5 : 31,6 : 30,7 : 31, 8 : 31,9 : 30,10 : 31,11 : 30,12 : 31}
    var arrayLookup = new Array(13);
    arrayLookup[0] = 99 ;
    arrayLookup[1] = 31;
    arrayLookup[3] = 31;
    arrayLookup[4] = 30;
    arrayLookup[5] = 31;
    arrayLookup[6] = 30;
    arrayLookup[7] = 31 ;
    arrayLookup[8] = 31;
    arrayLookup[9] = 30;
    arrayLookup[10] = 31;
    arrayLookup[11] = 30;
    arrayLookup[12] = 31;

    //check if month value and day value agree
    if(arrayLookup[arrayDate[0]] != null) {
      if(intDay <= arrayLookup[arrayDate[0]] && intDay != 0)
        return true; //found in lookup table, good date
    }

    //check for February
	var booLeapYear = (intYear % 4 == 0 && (intYear % 100 != 0 || intYear % 400 == 0));
    if( ((booLeapYear && intDay <= 29) || (!booLeapYear && intDay <=28)) && intDay !=0)
      return true; //Feb. had valid number of days
  }
  return false; //any other values, bad date
}

function validateUSDateTime( strValue ) {
	var dd, tt;
	if(strValue.indexOf(' ') > 0) {
		dd = strValue.substring(0, strValue.indexOf(' '));
		tt = strValue.substring(strValue.indexOf(' ')+1);
		return validateUSDate(dd) && isValidMilTime(tt);
	}
	else return validateUSDate(strValue);
}

function isValidMilTime(strValue) {
	var objRegExp = /^([0-9]|[0-1][0-9]|2[0-3])[:][0-5][0-9]$/;
	return objRegExp.test(strValue) ? true : false;
}

function validCreditCardNumber(strValue) {
/*
    * Visa: ^4[0-9]{12}(?:[0-9]{3})?$ All Visa card numbers start with a 4. New cards have 16 digits. Old cards have 13.
    * MasterCard: ^5[1-5][0-9]{14}$ All MasterCard numbers start with the numbers 51 through 55. All have 16 digits.
    * American Express: ^3[47][0-9]{13}$ American Express card numbers start with 34 or 37 and have 15 digits.
    * Diners Club: ^3(?:0[0-5]|[68][0-9])[0-9]{11}$ Diners Club card numbers begin with 300 through 305, 36 or 38. All have 14 digits. There are Diners Club cards that begin with 5 and have 16 digits. These are a joint venture between Diners Club and MasterCard, and should be processed like a MasterCard.
    * Discover: ^6(?:011|5[0-9]{2})[0-9]{12}$ Discover card numbers begin with 6011 or 65. All have 16 digits.
    * JCB: ^(?:2131|1800|35\d{3})\d{11}$ JCB cards beginning with 2131 or 1800 have 15 digits. JCB cards beginning with 35 have 16 digits. 

If you just want to check whether the card number looks valid, without determining the brand, you can combine the above six regexes into 
^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$. 

*/
  var objRegExp = /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/
  //check to see if in correct format
  if(!objRegExp.test(strValue))
    return null; //doesn't match pattern, bad date
	return true;
}
/*
(?=\d)^(?:(?!(?:10\D(?:0?[5-9]|1[0-4])\D(?:1582))|(?:0?9\D(?:0?[3-9]|1[0-3])\D(?:1752)))((?:0?[13578]|1[02])|(?:0?[469]|11)(?!/31)(?!-31)(?!\.31)|(?:0?2(?=.?(?:(?:29.(?!000[04]|(?:(?:1[^0-6]|[2468][^048]|[3579][^26])00))(?:(?:(?:\d\d)(?:[02468][048]|[13579][26])(?!\x20BC))|(?:00(?:42|3[0369]|2[147]|1[258]|09)\x20BC))))))|(?:0?2(?=.(?:(?:\d\D)|(?:[01]\d)|(?:2[0-8])))))([-.\/])(0?[1-9]|[12]\d|3[01])\2(?!0000)((?=(?:00(?:4[0-5]|[0-3]?\d)\x20BC)|(?:\d{4}(?!\x20BC)))\d{4}(?:\x20BC)?)(?:$|(?=\x20\d)\x20))?((?:(?:0?[1-9]|1[012])(?::[0-5]\d){0,2}(?:\x20[aApP][mM]))|(?:[01]\d|2[0-3])(?::[0-5]\d){1,2})?$
*/

function MM_validateAFormWithAddedAttributes(inputArgs) {
	var args = new Array();
	for(var i=0; i < inputArgs.length; i++) 
		args[args.length] = inputArgs[i];
	var allInputs = document.getElementsByTagName('input');
	for(var i=0; i<allInputs.length; i++) {
		if(allInputs[i].getAttribute('required')) {	

			args[args.length] = allInputs[i].getAttribute('id');
			args[args.length] = '';
			args[args.length] = 'R';
			addPrettyname(allInputs[i].getAttribute('id'));
		}
	}
	return MM_validateFormArgs(args);
}

function addPrettyname(id) {
	var el = document.getElementById(id);
	var prettyname;
	if(!(prettyname = el.getAttribute('prettyname'))) return;
  for(var i=0; i<prettyNames.length; i+=2)
    if(prettyNames[i] == prettyname) return;
  prettyNames[prettyNames.length] = id;
  prettyNames[prettyNames.length] = prettyname;
}


function ltrim(str) { 
	for(var k = 0; k < str.length && isWhitespace(str.charAt(k)); k++);
	return str.substring(k, str.length);
}
function rtrim(str) {
	for(var j=str.length-1; j>=0 && isWhitespace(str.charAt(j)) ; j--) ;
	return str.substring(0,j+1);
}
function trim(str) {
	return ltrim(rtrim(str));
}
function isWhitespace(charToCheck) {
	var whitespaceChars = " \t\n\r\f";
	return (whitespaceChars.indexOf(charToCheck) != -1);
}
