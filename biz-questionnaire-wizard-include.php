<? // biz-questionnaire-wizard-include.php // included from wizard.  $source and $fieldname are defined

if($fieldname == 'otherSurcharges') {  
	$satChecked = $source['surchargeTypes_1_sat'] ? 'CHECKED' : ''; // WEEKEND
	$sunChecked = $source['surchargeTypes_1_sun'] ? 'CHECKED' : '';
	$surchargeTypes_1_defaultcharge = safeValue($source['surchargeTypes_1_defaultcharge']);
	$surchargeTypes_1_defaultrate = safeValue($source['surchargeTypes_1_defaultrate']);
	$surchargeTypes_1_automatic = $source['surchargeTypes_1_automatic'] ? 'CHECKED' : '';
	$surchargeTypes_1_pervisit = $source['surchargeTypes_1_pervisit'] ? 'CHECKED' : '';
	$surchargeTypes_2_after = safeValue($source['surchargeTypes_2_after']); // LATE NIGHT
	$surchargeTypes_2_defaultcharge = safeValue($source['surchargeTypes_2_defaultcharge']);
	$surchargeTypes_2_defaultrate = safeValue($source['surchargeTypes_2_defaultrate']);
	$surchargeTypes_2_automatic = $source['surchargeTypes_2_automatic'] ? 'CHECKED' : '';
	$surchargeTypes_2_pervisit = $source['surchargeTypes_2_pervisit'] ? 'CHECKED' : '';
	$surchargeTypes_3_before = safeValue($source['surchargeTypes_3_before']);
	$surchargeTypes_3_defaultcharge = safeValue($source['surchargeTypes_3_defaultcharge']);
	$surchargeTypes_3_defaultrate = safeValue($source['surchargeTypes_3_defaultrate']);
	$surchargeTypes_3_automatic = $source['surchargeTypes_3_automatic'] ? 'CHECKED' : '';
	$surchargeTypes_3_pervisit = $source['surchargeTypes_3_pervisit'] ? 'CHECKED' : '';
	$size = 'size=7';
	echo <<<OTHERSURCHARGES
<table style='margin-bottom:15px;'>
<tr><th>Surcharge</th><th>&nbsp;</th><th>&nbsp;</th><th>Price</th><th>Pay Rate</th><th>Automatic</th><th>Per Visit</th></tr>
<tr><td>Weekend</td>
<td><input type='hidden' name='surchargeTypes_1_date' value='-1'><input type='checkbox' name='surchargeTypes_1_sat' id='surchargeTypes_1_sat' $satChecked>&nbsp;Sat</td>
<td><input type='checkbox' name='surchargeTypes_1_sun' id='surchargeTypes_1_sun' $sunChecked>&nbsp;Sun</td>
<td><input $size name='surchargeTypes_1_defaultcharge' id='surchargeTypes_1_defaultcharge' value='$surchargeTypes_1_defaultcharge'></td>
<td><input $size name='surchargeTypes_1_defaultrate' id='surchargeTypes_1_defaultrate' value='$surchargeTypes_1_defaultrate'></td>
<td><input type='checkbox' name='surchargeTypes_1_automatic' id='surchargeTypes_1_automatic' $surchargeTypes_1_automatic></td>
<td><input type='checkbox' name='surchargeTypes_1_pervisit' id='surchargeTypes_1_pervisit' $surchargeTypes_1_pervisit></td>
</tr>
<tr><td>Late Night</td><td>After:</td>
<td><input type='hidden' name='surchargeTypes_2_date' value='-1'><input $size name='surchargeTypes_2_after' id='surchargeTypes_2_after' value='$surchargeTypes_2_after'></td>
<td><input $size name='surchargeTypes_2_defaultcharge' id='surchargeTypes_2_defaultcharge' value='$surchargeTypes_2_defaultcharge'></td>
<td><input $size name='surchargeTypes_2_defaultrate' id='surchargeTypes_2_defaultrate' value='$surchargeTypes_2_defaultrate'></td>
<td><input type='checkbox' name='surchargeTypes_2_automatic' id='surchargeTypes_2_automatic' $surchargeTypes_2_automatic></td>
<td><input type='checkbox' name='surchargeTypes_2_pervisit' id='surchargeTypes_2_pervisit' $surchargeTypes_2_pervisit></td>
</tr>
<tr><td>Early Morning</td><td>Before:</td>
<td><input type='hidden' name='surchargeTypes_3_date' value='-1'><input $size name='surchargeTypes_3_before' id='surchargeTypes_3_before' value='$surchargeTypes_3_before'></td>
<td><input $size name='surchargeTypes_3_defaultcharge' id='surchargeTypes_3_defaultcharge' value='$surchargeTypes_3_defaultcharge'></td>
<td><input $size name='surchargeTypes_3_defaultrate' id='surchargeTypes_3_defaultrate' value='$surchargeTypes_3_defaultrate'></td>
<td><input type='checkbox' name='surchargeTypes_3_automatic' id='surchargeTypes_3_automatic' $surchargeTypes_3_automatic></td>
<td><input type='checkbox' name='surchargeTypes_3_pervisit' id='surchargeTypes_3_pervisit' $surchargeTypes_3_pervisit></td>
</tr>
</table>
OTHERSURCHARGES;
}

