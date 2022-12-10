<? // intake-form-fns.php

function noBooleanValue() {
	return 5439763;
}

function textBox($label, $width=700, $height=100, $displayVal=null) {
	echo formattedLabel($label)
	."<textarea style='display:block;margin-top:0px;width:$width"."px;height:$height"."px;border:solid black 1px;'>$displayVal</textarea>";
}

function oneLineCheckbox($label, $noRow=false, $checked=null) {
	if(!$noRow) lineStart();
	echo "\n<td>".formattedLabel($label)."</td><td>".simpleCheckBox($checked)."</td>\n";
	if(!$noRow) lineEnd();
}

function oneLineBoolean($label, $noRow=false, $checked=5439763) {
	if(!$noRow) lineStart();
	echo "\n<td>".formattedLabel($label)."</td><td>Yes ".simpleCheckBox($checked)."$space No ".simpleCheckBox(!$checked || $checked == noBooleanValue())."</td>\n";
	if(!$noRow) lineEnd();
}

function simpleCheckBox($checked=5439763) {
	if($checked && ($checked != 5439763)) $checked = 'checked';
	$checked = ($checked && $checked !== 5439763) ? 'checked' : '';  // WTF?!! Why wouldn't "!=" work?
	return "$zzz<input type='checkbox' $checked>";
}


function phoneLineEntry($label, $linewidth=300, $noRow=false, $displayVal=null) {
	if(!$noRow) lineStart();
	if($displayVal) {
		$primaryClass = $displayVal[0] == '*' ? 'class="checked"' : '';
		$textClass = textMessageEnabled($displayVal) ? 'class="checked"' : '';
		$displayVal = strippedPhoneNumber($displayVal);
	}
	$displayVal = !$displayVal 
		? "<img src='art/spacer.gif' width=$linewidth height=1>" 
		: "<img src='art/spacer.gif' width=10 height=1> <b>$displayVal</b>";
	echo "\n<td>".formattedLabel($label)."</td><td><span $primaryClass>[primary]</span> <span $textClass>[text]</span> </td><td class='blankline' style='$linewidth"
				."px;$extraLineStyle'>$displayVal</td>";
	if(!$noRow) lineEnd();
}

function oneLineEntry($label, $linewidth=300, $noRow=false, $displayVal=null) {
	if(!$noRow) lineStart();
	$displayVal = !$displayVal 
		? "<img src='art/spacer.gif' width=$linewidth height=1>" 
		: "<img src='art/spacer.gif' width=10 height=1> <b>$displayVal</b>";
	echo "\n<td>".formattedLabel($label)."</td><td class='blankline' style='$linewidth"
				."px;$extraLineStyle'>$displayVal</td>";
	if(!$noRow) lineEnd();
}

function newSection($title) {
	echo "<hr><span class='sectionhead'>$title</span><p>";
}

function formattedLabel($label) {return "<span class='entrylabel'>$label:</span>";}
				
function lineStart() { echo "\n<table class='linetable'><tr>";}
function lineEnd() { echo "\n</tr></table>";}

function getBizLogoImage() {
	global $bizptr;  // in absence of SESSION, $bizptr must be set to the business's id number
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_$bizptr/";
	if($headerBizLogo) {
		$headerBizLogo = getHeaderBizLogo($headerBizLogo);
		if($headerBizLogo) {
			$imgSrc = globalURL($headerBizLogo);
			$headerBizLogo = "<img src='$imgSrc'>";
		}
	}
	return $headerBizLogo;
}
