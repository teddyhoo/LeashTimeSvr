<?

/***************************************************************
 * 
 * Main Voting Page
 *
 * ! only show if in date range
 * ! show the questions and answers for particular Company Vote
 * ! allow selection of management answers
 * ! only allow num of answers specified
 * ! process the vote (store all, but save latest as final vote)
 * - send results to email if requested
 * - where to put user after vote completed?
 ***************************************************************/

include "include/conf.inc.php";
$page_color="#6299CD";
$page_title="Voting";

// if user has gotten here, they have logged in successfully

// check now for one vote 

$AllowOne=0;
$web_voter_id=$_POST['web_voter_id'];
$web_companyvote_id=$_POST['web_companyvote_id'];

$VoteTypeSQL="Select OneVote from tblCompanyVote where CompanyVoteID='$web_companyvote_id'";
$VoteTypeResult=Perform_Select_Query($VoteTypeSQL, $db);
if ($VoteTypeResult[OneVote][0]) {
	$UserSQL = "select Voted from tblAccount where VoterID='$web_voter_id' and CompanyVotePtr='$web_companyvote_id'";
	$UserResult=Perform_Select_Query($UserSQL, $db);
	if ($UserResult[Voted][0]) header("Location:".HTTPROOT."vote.html?VoteID=".$web_companyvote_id."&multiple_login=1");
}			

if ($_POST['SubmitRecast']) {
	$_SESSION['StepNumber']=1;
}

if (($_SESSION['StepNumber']==1) && ($_POST['SubmitForConfirmation'])) {
	
	//
	// steps for confirmation:
	// delete any votes from this user on this vote in tblVotesHold table 
	// insert this vote into tblVotesHOld table
	// create email/display message text
	// send user to next step (StepNumber=2)
	//
	
	$EmailMessage="";	
	$answered=0;
	foreach ($_POST as $key=>$val) {
		if (substr($key, 0, 6)=="Answer") {
			$answered=1;
		}
	}
	
	$DeleteHoldSQL="Delete from tblVotesHold where AccountPtr='$WebAccountID'";
	$DeleteHoldResult=Perform_Query($DeleteHoldSQL, $db);
	
	// Process Board Votes
	if ($web_companyvote_id!=63) {
	$Select_SQL="Select * from tblQuestions where CompanyVotePtr='$web_companyvote_id' and BoardMember=1 ";
	if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
	$Select_SQL.=" order by SortOrder, QuestionID";
	$Select_Rows=Get_Rows($Select_SQL, $db);
	$Select_Result=Perform_Select_Query($Select_SQL, $db);
	if (($web_companyvote_id==70) || ($web_companyvote_id==71)) {
		$EmailMessage.="ELECTION OF DELEGATES\n";
	} elseif ($web_companyvote_id==94) {
        $EmailMessage.="2010 IPI BOARD OF DIRECTORS ELECTION\n";
    } else {
        if ($web_companyvote_id==99) {
            $EmailMessage.="ARTICLES OF INCORPORATION\n";
        } elseif (!in_array($web_companyvote_id, array(78, 109, 119))) {
            $EmailMessage.="ELECTION OF DIRECTORS\n";
        }
	}
	for ($b=0;$b<$Select_Rows;$b++) {
		$col_SQL="show columns from tblQuestions";
		$col_Result = Perform_Select_Query ($col_SQL, $db);
		$col_Rows = Get_Rows($col_SQL, $db);
		for ($row=0;$row < $col_Rows; $row++)   {
				$varname=$col_Result[Field][$row];
				$$varname=$Select_Result[$varname][$b];
		}	
		$AnswerName="Answer".$QuestionID;
		if ($$AnswerName=="Accept") {
			$SelectAnSQL="Select AnswerID from tblAnswers where QuestionPtr='$QuestionID'
							and Answer='Accept'";
			$SelectAnResult=Perform_Select_Query($SelectAnSQL, $db);
			$AnswerID=$SelectAnResult[AnswerID][0];
		} elseif (($$AnswerName=="Withhold") || ($$AnswerName=="")) {
			$SelectAnSQL="Select AnswerID from tblAnswers where QuestionPtr='$QuestionID'
							and Answer='Withhold'";
			$SelectAnResult=Perform_Select_Query($SelectAnSQL, $db);
			$AnswerID=$SelectAnResult[AnswerID][0];
		}
		$InsertSQL="Insert into tblVotesHold (
						AccountPtr,
						QuestionPtr,
						AnswerPtr,
						VoteDateTime,
						Latest,
						DateCreated,
						LastUpdate)
						values (
						'$WebAccountID',
						'$QuestionID',
						'$AnswerID',
						'$today',
						'1',
						'$today',
						'$today')";
		$InsertResult=Perform_Query($InsertSQL, $db);
		Debug("Insert Board Vote SQL: ".$InsertSQL);
		$GetForSQL="Select ForYes from tblCompanyVote where CompanyVoteID='$web_companyvote_id'";
		$GetForResult=Perform_Select_Query($GetForSQL, $db);
		$ForYes=$GetForResult[ForYes][0];
		if ($ForYes=="Y") {
			if ($$AnswerName=="Accept") $EmailMessage.="YES: $Question";
			if ($$AnswerName=="Withhold") $EmailMessage.= "NO: $Question";
		} else {
			if ($$AnswerName=="Accept") $EmailMessage.= "FOR: $Question";
			if ($$AnswerName=="Withhold") $EmailMessage.= "WITHHELD: $Question";
		}
		if ($$AnswerName!="") $EmailMessage.="\n";
	}
	$EmailMessage.="\n\n";
	} // end hard-coding for vote 63
	

	// Process Non-Board Votes
	if (($web_companyvote_id!=70) && ($web_companyvote_id!=71)) {
	$Select_SQL="Select * from tblQuestions where CompanyVotePtr='$web_companyvote_id' and BoardMember=0 ";
	if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
	$Select_SQL.=" order by SortOrder, QuestionID";
	$Select_Rows=Get_Rows($Select_SQL, $db);
	$Select_Result=Perform_Select_Query($Select_SQL, $db);
	if (!in_array($web_companyvote_id, array(78,94,114,123))) $EmailMessage.="PROPOSALS\n";
	if ($web_companyvote_id==114) $EmailMessage.="<b>Nominees</b>\n\n";
	if ($web_companyvote_id==123) $EmailMessage.="ELECTION OF DIRECTORS\n\n";

	for ($b=0;$b<$Select_Rows;$b++) {
		$col_SQL="show columns from tblQuestions";
		$col_Result = Perform_Select_Query ($col_SQL, $db);
		$col_Rows = Get_Rows($col_SQL, $db);
		for ($row=0;$row < $col_Rows; $row++)   {
				$varname=$col_Result[Field][$row];
				$$varname=$Select_Result[$varname][$b];
		}	
		$SelectAn_SQL="Select * from tblAnswers where QuestionPtr='$QuestionID'";
		$SelectAn_Rows=Get_Rows($SelectAn_SQL, $db);
		$SelectAn_Result=Perform_Select_Query($SelectAn_SQL, $db);
        if ($_REQUEST['AnswerText'.$QuestionID]) {
            $InsertSQL="Insert into tblVotesHold (
                            AccountPtr,
                            QuestionPtr,
                            AnswerText,
                            VoteDateTime,
                            Latest,
                            DateCreated,
                            LastUpdate)
                            values (
                            '$WebAccountID',
                            '$QuestionID',
                            '".$_REQUEST['AnswerText'.$QuestionID]."',
                            '$today',
                            '1',
                            '$today',
                            '$today')";
            $InsertResult=Perform_Query($InsertSQL, $db);
            Debug("Insert Non-Board Vote SQL: ".$InsertSQL);
            if ($web_companyvote_id==78) { 
                if ($Question!=$HoldQuestion) {
                    $EmailMessage.="<b>ADDITIONAL INDIVIDUAL</b>\n"; 
                    //$EmailMessage.=strtoupper(strip_tags($Question))."\n";
                }
                $EmailMessage.=strtoupper(strip_tags($_REQUEST['AnswerText'.$QuestionID]))."\n";
            } else {
                $EmailMessage.=strtoupper($_REQUEST['AnswerText'.$QuestionID]).": ".$Question."\n";
            }            
        } else {
            if ($SelectAn_Rows>0) {
                if ($web_companyvote_id==78) { 
                    $QuestionArray=split("-", $Question);
                    $EmailMessage.="<b>".strtoupper(strip_tags($QuestionArray[0]))."</b>\n";
                }
		        for ($a=0;$a<$SelectAn_Rows;$a++) {
			        $col_SQL="show columns from tblAnswers";
			        $col_Result = Perform_Select_Query ($col_SQL, $db);
			        $col_Rows = Get_Rows($col_SQL, $db);
			        for ($row=0;$row < $col_Rows; $row++)   {
					        $varname=$col_Result[Field][$row];
					        $$varname=$SelectAn_Result[$varname][$a];
			        }	
                    if ($NumAnswers==1) {
                        $AnswerName="Answer".$QuestionID;
                        $SelectedAnswerID=$$AnswerName;
                        if ($AnswerID!=$SelectedAnswerID) {
                            $AnswerName="";
                            //print "HERE!".$AnswerID."<br>";
                        }
                    } else {
			            $AnswerName="Answer".$QuestionID.$AnswerID;
                    }
			        if ($$AnswerName) {
				        $InsertSQL="Insert into tblVotesHold (
								        AccountPtr,
								        QuestionPtr,
								        AnswerPtr,
								        VoteDateTime,
								        Latest,
								        DateCreated,
								        LastUpdate)
								        values (
								        '$WebAccountID',
								        '$QuestionID',
								        '$AnswerID',
								        '$today',
								        '1',
								        '$today',
								        '$today')";
				        $InsertResult=Perform_Query($InsertSQL, $db);
				        Debug("Insert Non-Board Vote SQL: ".$InsertSQL);
                        if ($web_companyvote_id==78) {
                            $EmailMessage.=strtoupper(strip_tags($Answer))."\n";
                        } elseif ($web_companyvote_id==94) { 
                            //print substr(strip_tags($Question), 0, 5)."<br>\n";
                            $EmailMessage.="<b>";
                            switch (substr(strip_tags($Question), 0, 5)) {
                                case "Affil":
                                    $EmailMessage.="Affiliate/Supplier";
                                    break;
                                case "Comme":
                                    $EmailMessage.="Commercial Operator";
                                    break;
                                case "Hospi":
                                    $EmailMessage.="Hospital/Medical Center";
                                    break;
                                case "Publi":
                                    $EmailMessage.="Public";
                                    break;
                                case "Addit":
                                    $EmailMessage.="Additional Choice";
                                    break;
                                default:
                                    $EmailMessage.="$Question";
                            }
                            $EmailMessage.="</b>\n";
                            $EmailMessage.=strtoupper($Answer)."\n";
                        
                        } elseif ($web_companyvote_id==114) { 
                            //print substr(strip_tags($Question), 0, 5)."<br>\n";
                            $EmailMessage.="<b><u>";
                            switch (substr(strip_tags($Question), 0, 5)) {
                                case "ACADE":
                                    $EmailMessage.="ACADEMIC";
                                    break;
                                case "AIRPO":
                                    $EmailMessage.="AIRPORT";
                                    break;
                                case "CONSU":
                                    if ($consult==0) $EmailMessage.="CONSULTANT";
                                    $consult=1;
                                    break;
                                case "PUBLI":
                                    if ($public==0) $EmailMessage.="PUBLIC";
                                    $public=1;
                                    break;
                                case "SUPPL":
                                    $EmailMessage.="SUPPLIER";
                                    break;
                                default:
                                    $EmailMessage.="$Question";
                            }
                            $EmailMessage.="</u></b>\n";
                            $EmailMessage.=strtoupper($Answer)."\n";
                        } elseif ($web_companyvote_id==123) {
														$EmailMessage.=strtoupper($Answer)"\n";
                        } else {
                            $EmailMessage.=strtoupper($Answer).": ".$Question."\n";
                        }                        
			        }
		        }
            } else {
                if ($web_companyvote_id==78) $EmailMessage.="<b>ADDITIONAL INDIVIDUAL</b>\n";                 
            }
        }
		$EmailMessage.="\n";
        $HoldQuestion=$Question;
	}
	$EmailMessage.="\n\n";
	}
	
	// 
	// insert Email/Display message into session for next step
	//
	$_SESSION['EmailMessage']=$EmailMessage;
	

	$_SESSION['StepNumber']=2;

} elseif (($_SESSION['StepNumber']==2) && ($_POST['SubmitFinal'])) {
	
	//
	// steps for final submission:
	// move votes from tblVotesHold to tblVotes 
	// remove votes from tblVotesHold
	// send email if necessary
	// send user to next step (StepNumber=3)
	//
		
	$SelectConfirmSQL="Select CompanyVoteName, EmailConfirm
					from tblCompanyVote
					where CompanyVoteID='$web_companyvote_id'";
	$SelectConfirmResult=Perform_Select_Query($SelectConfirmSQL, $db);	
	if (!$SelectConfirmResult[EmailConfirm][0]) $Email=0;


	$Select_SQL="Select * from tblQuestions where CompanyVotePtr='$web_companyvote_id'";
	$Select_Rows=Get_Rows($Select_SQL, $db);
	$Select_Result=Perform_Select_Query($Select_SQL, $db);
	for ($b=0;$b<$Select_Rows;$b++) {
		$UpdateOldSQL="Update tblVotes set Latest=0 where QuestionPtr='".$Select_Result['QuestionID'][$b]."' and AccountPtr='$WebAccountID'";
		$UpdateOldResult=Perform_Query($UpdateOldSQL, $db);
	}
	
	$GetVotesHoldSQL="Select * from tblVotesHold where AccountPtr='$WebAccountID'";
	$GetVotesHoldRows=Get_Rows($GetVotesHoldSQL, $db);
	$GetVotesHoldResult=Perform_Select_Query($GetVotesHoldSQL, $db);
	for ($h=0;$h<$GetVotesHoldRows;$h++) {
		$InsertSQL="Insert into tblVotes (
							AccountPtr,
							QuestionPtr,
							AnswerPtr,
							VoteDateTime,
							Latest,
							DateCreated,
							LastUpdate)
							values (
							'".$GetVotesHoldResult[AccountPtr][$h]."',
							'".$GetVotesHoldResult[QuestionPtr][$h]."',
							'".$GetVotesHoldResult[AnswerPtr][$h]."',
							'".$GetVotesHoldResult[VoteDateTime][$h]."',
							'".$GetVotesHoldResult[Latest][$h]."',
							'".$GetVotesHoldResult[DateCreated][$h]."',
							'".$GetVotesHoldResult[LastUpdate][$h]."')";
		$InsertResult=Perform_Query($InsertSQL, $db);
		Debug("Insert Board Vote SQL: ".$InsertSQL);
		$DeleteSQL="Delete from tblVotesHold where VoteHoldID='".$GetVotesHoldResult[VoteHoldID][$h]."'";
		$DeleteResult=Perform_Query($DeleteSQL, $db);
	}
	

	// Send Email if requested
	if ($Email) {
		// get email/display text from database
		
		// send email
		$EmailHead="Here are the Results of your Vote for the ".$SelectConfirmResult[CompanyVoteName][0];
		if (Check_Region_Vote($web_companyvote_id)) $SendEmail.=" (".Get_Region_Name($RegionPtr).")";
		$EmailHead.= ":\n\n";
		$EmailHead.="You voted on: ".$today."\n\n";
		$EmailMessage=$EmailHead.$_SESSION['EmailMessage'];
		$EmailMessage.="If you have any questions about your vote, please contact us at $AdminEmail.\n\n";
		$Send=mail($Email, "Vote Results", $EmailMessage, "From: $AdminEmail\nReply-To:$AdminEmail");
	}
		
	// update voted status
	$UpdateSQL="Update tblAccount set Voted=1 where AccountID='$WebAccountID'";
	$UpdateResult=Perform_Query($UpdateSQL, $db);
	
	$_SESSION['StepNumber']=3;
	
	$UpdateStats=Update_Stats($web_companyvote_id, $WebAccountID);
	
}

//
//
// End Submission Section
//
//

//
//
// Begin Display Section
//
//

include "top.php";
$SelectNameSQL="Select CompanyVoteName, CompanyName, CompanyLogo, VoteText, LandingPageText, 
				ViewOnly, ForYes, BallotProxy, ManRecText, EmailConfirm, OnlyFor, MaxBoardVotes, NumBoardVotes,
				Step1BottomText, Step2BottomText
				from tblCompanyVote, tblCompany
				where CompanyPtr=CompanyID
				and CompanyVoteID='$web_companyvote_id'";
$SelectNameResult=Perform_Select_Query($SelectNameSQL, $db);


if ($_SESSION['StepNumber']==1) {
	// 
	// initial voting page
	// display the vote page
	// allow user to submit vote 
	// goes to next page for confirmation
	//
	?>
	<script language="JavaScript" src="<? print HTTPROOT; ?>reset.js"></script>
	
	<script language ="JavaScript">
	<!-- Hide from non-JavaScript aware browsers
	
	
	
	
	function FillManBoard() {
		<?
		$Select_SQL="Select tblAnswers.* from tblAnswers, tblQuestions
					 where CompanyVotePtr='$web_companyvote_id' and BoardMember=1
					 and QuestionPtr=QuestionID and Management=1";
		if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
		$Select_Rows=Get_Rows($Select_SQL, $db);
		$Select_Result=Perform_Select_Query($Select_SQL, $db);
		for ($s=0;$s<$Select_Rows;$s++) {
			if ($Select_Result[Answer][$s]=="Accept") {
				print "document.VoteForm.Answer".$Select_Result[QuestionPtr][$s]."[0].checked=true;";
			} elseif ($Select_Result[Answer][$s]=="Withhold") {
				print "document.VoteForm.Answer".$Select_Result[QuestionPtr][$s]."[1].checked=true;\n";
			}
		}
		?>
		
	}
	
	function FillManNonBoard() {
		<?
		$Select_SQL="Select tblAnswers.*, tblQuestions.NumAnswers from tblAnswers, tblQuestions
					 where CompanyVotePtr='$web_companyvote_id' and BoardMember=0
					 and QuestionPtr=QuestionID";
		if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
		$Select_Rows=Get_Rows($Select_SQL, $db);
		$Select_Result=Perform_Select_Query($Select_SQL, $db);
		for ($s=0;$s<$Select_Rows;$s++) {
	        if ($Select_Result[NumAnswers][$s]==1) {
	            if ($Select_Result[Management][$s]) {
			print "if (!document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].".length) { \n";
	                print "     if (document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].".value=='".$Select_Result[AnswerID][$s]."')  {\n";
	                print "         document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].".checked=true;\n";
	                print "     } else {    \n";
	                print "         document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].".checked=false;\n";
	                print "     }\n";
					print "} else { \n";
	                print "for (var i=0; i<document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].".length; i++)  { \n";
	                print "     if (document.VoteForm.Answer".$Select_Result[QuestionPtr][$s]."[i].value=='".$Select_Result[AnswerID][$s]."')  {\n";
	                print "         document.VoteForm.Answer".$Select_Result[QuestionPtr][$s]."[i].checked=true;\n";
	                print "     } else {    \n";
	                print "         document.VoteForm.Answer".$Select_Result[QuestionPtr][$s]."[i].checked=false;\n";
	                print "     }\n";
	                print "}\n";                
			print "}\n";
	            }
	        } else {
			    if ($Select_Result[Management][$s]) {
				    print "document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].$Select_Result[AnswerID][$s].".checked=true;\n";
			    } else {
				    print "document.VoteForm.Answer".$Select_Result[QuestionPtr][$s].$Select_Result[AnswerID][$s].".checked=false;\n";
			    }
	        }
	    }
		?>
	}
	
	<?
		$Select_SQL="Select * from tblQuestions
					 where CompanyVotePtr='$web_companyvote_id' and BoardMember=0 ";
		if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
		$Select_Rows=Get_Rows($Select_SQL, $db);
		$Select_Result=Perform_Select_Query($Select_SQL, $db);
		for ($s=0;$s<$Select_Rows;$s++) { 
		
			print "function anyCheck".$Select_Result[QuestionID][$s]."(field) {\n ";
			print "var total = 0;\n ";
			$SelectAn_SQL="Select * from tblAnswers where QuestionPtr='".$Select_Result[QuestionID][$s]."'";
			$SelectAn_Rows=Get_Rows($SelectAn_SQL, $db);
			$SelectAn_Result=Perform_Select_Query($SelectAn_SQL, $db);
			for ($a=0;$a<$SelectAn_Rows;$a++) {
				print "if (eval(\"document.VoteForm.Answer".$Select_Result[QuestionID][$s].$SelectAn_Result[AnswerID][$a].".checked\") == true) {\n ";
				print "    total += 1;\n ";
				print "}\n ";
			}
			print "if (total>".$Select_Result[NumAnswers][$s].") {\n";
			print "alert(\"You have selected more answers than are allowed.\");\n ";
			print "field.checked=false;\n";
			print "}\n";
			print "}\n ";
		}
		?>
		
	<?
		$Select_SQL="Select * from tblQuestions
					 where CompanyVotePtr='$web_companyvote_id' and BoardMember=1 ";
		if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
		$Select_Rows=Get_Rows($Select_SQL, $db);
		$Select_Result=Perform_Select_Query($Select_SQL, $db);
		if ($SelectNameResult[MaxBoardVotes][0]>0) {
			print "function anyCheck(field) {\n ";
			print "var total = 0;\n ";
			for ($s=0;$s<$Select_Rows;$s++) { 
				print "if (eval(\"document.VoteForm.Answer".$Select_Result[QuestionID][$s].".checked\") == true) {\n ";
				print "    total += 1;\n ";
				print "}\n ";
			}		
			print "if (total>".$SelectNameResult[MaxBoardVotes][0].") {\n";
			print "alert(\"You have selected more names than are allowed.\");\n ";
			print "field.checked=false;\n";
			print "}\n";
			print "}\n ";
		}
		if ($SelectNameResult[NumBoardVotes][0]>0) {
			print "function anyCheckTotal(field) {\n ";
			print "var total = 0;\n ";
			for ($s=0;$s<$Select_Rows;$s++) { 
				print "if (eval(\"document.VoteForm.Answer".$Select_Result[QuestionID][$s].".checked\") == true) {\n ";
				print "    total += 1;\n ";
				print "}\n ";
			}		
			print "if (total!=".$SelectNameResult[NumBoardVotes][0].") {\n";
			print "alert(\"You must select exactly ".$SelectNameResult[NumBoardVotes][0]." names.\");\n ";
			print "return false;\n";
			print "} else {\n";
			print "return true;\n";
			print "}\n ";
			print "}\n ";
		
		
		}
		?>	
	//-->
	
	
	function MM_openBrWindow(theURL,winName,features) { //v2.0
	  window.open(theURL,winName,features);
	}
	
	
	function clearForm(oForm) {
	   
	  if (confirm("Are you sure you want to clear your ballot?")) {
		  var elements = oForm.elements;
		   
		  oForm.reset();
		
		  for(i=0; i<elements.length; i++) {
		     
			  field_type = elements[i].type.toLowerCase();
			 
			  switch(field_type) {
			 
			    case "text":
			    case "textarea":
			      elements[i].value = "";
			      break;
			       
			    case "radio":
			    case "checkbox":
			        if (elements[i].checked) {
			          elements[i].checked = false;
			      }
			      break;
			
			    case "select-one":
			    case "select-multi":
			                elements[i].selectedIndex = -1;
			      break;
			
			    default:
			      break;
			  	}
		    }
		} else {
		return false;
	 }
		
	}	
		
	</script>
	
	<?
	
	// grab any votes in tblVotesHold - if any are there, the user hit "recast vote"
	if ($_POST['SubmitRecast']) {
		$GetHoldVotesSQL="Select * from tblVotesHold where AccountPtr='$WebAccountID'";
		$GetHoldVotesRows=Get_Rows($GetHoldVotesSQL, $db);
		$GetHoldVotesResult=Perform_Select_Query($GetHoldVotesSQL, $db);
	}	
	
	print "<center>";
	
	
	if ($SelectNameResult[CompanyLogo][0]) {
		print "<img src='pics/".$SelectNameResult[CompanyLogo][0]."'><br>";
	}
	if ($web_companyvote_id!="114") print "<b>".$SelectNameResult[CompanyName][0]."</b>";
	print "<br><br>";
	print "<b>".$SelectNameResult[CompanyVoteName][0]."</b><br>";
	if (Check_Region_Vote($web_companyvote_id)) print "<b>".Get_Region_Name($RegionPtr)."</b><br>";
	print "<br>";
	print "<table class='bodycopy'>";
	if ($SelectNameResult[LandingPageText][0]) {
		print "<tr><td>Click <a href='#' onClick=\"MM_openBrWindow('landing.html?CompanyVoteID=$web_companyvote_id&pop=1','','width=600,height=500,scrollbars=yes')\">here</a> for detailed information about this vote.<br><br></td></tr>";
	}
	print "<tr><td>".get_page($SelectNameResult[VoteText][0])."</td></tr>";
	print "</table>";
	
	print "<form action='$PHP_SELF' method=POST name=VoteForm>";
	print "<input type=hidden name=SubmitForConfirmation value=1>";
	print "<input type=hidden name=web_voter_id value='".$_POST['web_voter_id']."'>";
	print "<input type=hidden name=web_companyvote_id value='".$_POST['web_companyvote_id']."'>";
	
	
    if ($SelectNameResult[ViewOnly][0]!=1) {
        print "<table width=90% class=bodycopy cellpadding=5 cellspacing=0>";
        print "<tr><td colspan=3 align=center>";
        if ($web_companyvote_id!="114") {
            print "<a href='javascript:void(0)' onClick=\"FillManBoard();FillManNonBoard();\">";
            print $SelectNameResult[ManRecText][0];
            print "</a><br>";
        }
        //print "When you are finished voting, you must click the \"Review Ballot\" button at the bottom of this page to move to the next step.<br>(Go to <a href='#bottom'>bottom</a>)";
        print "</td></tr>";
        print "</table>";
    }

	// Show Board Members
	
	$Select_SQL="Select * from tblQuestions where CompanyVotePtr='$web_companyvote_id' and BoardMember=1 ";
	if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
	$Select_SQL.=" order by SortOrder, QuestionID";
	$Select_Rows=Get_Rows($Select_SQL, $db);
	$Select_Result=Perform_Select_Query($Select_SQL, $db);

	if ($Select_Rows) {
		print "<table width=90% class=bodycopy cellpadding=5 cellspacing=0>";
	    if ($web_companyvote_id==98) print "<tr><td colspan=5 align=center><span class='title-orange'><b>Election of Directors</b></span></td></tr>";
		print "<tr class='bodycopybig'><td bgcolor='#FFCC00'>Name</td>";
		if ($SelectNameResult[ViewOnly][0]!=1) {
			if ($SelectNameResult[ForYes][0]=="Y") {
				print "<td align=center bgcolor='#FFCC00'>Yes</td>";
				if (!$SelectNameResult[OnlyFor][0]) print "<td align=center bgcolor='#FFCC00'>No</td>";
			} else {
				print "<td align=center bgcolor='#FFCC00'>For</td>";
				if (!$SelectNameResult[OnlyFor][0]) print "<td align=center bgcolor='#FFCC00'>Withhold</td>";
			}			
		}
		print "</tr>";
		for ($b=0;$b<$Select_Rows;$b++) {
			$col_SQL="show columns from tblQuestions";
			$col_Result = Perform_Select_Query ($col_SQL, $db);
			$col_Rows = Get_Rows($col_SQL, $db);
			for ($row=0;$row < $col_Rows; $row++)   {
			        $varname=$col_Result[Field][$row];
			        $$varname=$Select_Result[$varname][$b];
			}	
			print "<tr bgcolor='FFFFFF'><td>$Question</td>";
			if ($SelectNameResult[ViewOnly][0]!=1) {
				print "<td valign=top align=center>";
				if (!$SelectNameResult[OnlyFor][0]) {
					print "<input type=radio  name='Answer".$QuestionID."' value='Accept' ";
	                for ($h=0;$h<$GetHoldVotesRows;$h++) {
	                	if (($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) && (Get_Answer($GetHoldVotesResult[AnswerPtr][$h])=="Accept")) {
	                		print " CHECKED ";
	                		break;
						}
					}
					print ">";
				} else {
					print "<input type=checkbox  name='Answer".$QuestionID."' value='Accept' ";
	                for ($h=0;$h<$GetHoldVotesRows;$h++) {
	                	if (($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) && (Get_Answer($GetHoldVotesResult[AnswerPtr][$h])=="Accept")) {
	                		print " CHECKED ";
	                		break;
						}
					}
					if ($SelectNameResult[MaxBoardVotes][0]>0) print " onClick=\"anyCheck(document.VoteForm.Answer".$QuestionID.")\";";
					print ">";
				}				
				print "</td>";
				if (!$SelectNameResult[OnlyFor][0]) {
					print "<td valign=top align=center><input type=radio  name='Answer".$QuestionID."' value='Withhold' ";
	                for ($h=0;$h<$GetHoldVotesRows;$h++) {
	                	if (($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) && (Get_Answer($GetHoldVotesResult[AnswerPtr][$h])=="Withhold")) {
	                		print " CHECKED ";
	                		break;
						}
					}
					print "></td>";
				}
			}
			print "</tr>";	
	        print "<tr><td colspan='3'><img src='images/spacer-gray.gif' width='100%' height='2'></td></tr>";
	
		}
		print "</table>";
	}
	
	print "<br>";
	
	// Show Questions
	
	$Select_SQL="Select * from tblQuestions where CompanyVotePtr='$web_companyvote_id' and BoardMember=0 ";
	if (Check_Region_Vote($web_companyvote_id)) $Select_SQL.=" and RegionPtr='$RegionPtr' ";
	$Select_SQL.=" order by SortOrder, QuestionID";
	
	$Select_Rows=Get_Rows($Select_SQL, $db);
	$Select_Result=Perform_Select_Query($Select_SQL, $db);
	
	if ($Select_Rows) {
		if ($web_companyvote_id=="114") {
	        print "<span class=bodycopy>Please vote for a total of seven (7) individuals as follows:</span><br>";
	        print "<span class='title-orange'><b>Nominees</b></span><br>";
	  }
	  else if($web_companyvote_id=="123") ; // Omit "Proposals for Ocean Spray 2012 Election of Directors
	  else {
	        print "<span class='title-orange'><b>Proposals</b></span><br>"; 
	  }
		print "<table width=90% class=bodycopy cellpadding=5 cellspacing=0>";
		if ($SelectNameResult[ViewOnly][0]!=1) {
			/*
	        print "<tr><td colspan=3 align=center><a href='#' onClick=\"FillManNonBoard();\">";
			print $SelectNameResult[ManRecText][0];
			print "</a>";
			print "</td></tr>";
	        */
		}
	
		print "<tr class='bodycopybig'><td bgcolor='#FFCC00' colspan=2>";
	    if ($web_companyvote_id==98) {
	        print "Management Proposals";   
	    }
	    else if($web_companyvote_id=="123") {
	        print "Election of Directors";
	    }
	    else {
	        print "Proposal";
	    }
	    print "</td>";
		if ($SelectNameResult[ViewOnly][0]!=1) print "<td bgcolor='#FFCC00'>Vote</td>";
		print "</tr>";
		for ($b=0;$b<$Select_Rows;$b++) {
			$col_SQL="show columns from tblQuestions";
			$col_Result = Perform_Select_Query ($col_SQL, $db);
			$col_Rows = Get_Rows($col_SQL, $db);
			for ($row=0;$row < $col_Rows; $row++)   {
			        $varname=$col_Result[Field][$row];
			        $$varname=$Select_Result[$varname][$b];   
			}	
	        $SelectAn_SQL="Select * from tblAnswers where QuestionPtr='$QuestionID' order by AnswerID";
	        $SelectAn_Rows=Get_Rows($SelectAn_SQL, $db);
	        $SelectAn_Result=Perform_Select_Query($SelectAn_SQL, $db);
	        if ($SelectAn_Rows==0) $SelectAn_Rows=1;
	        if ($web_companyvote_id==98) {
	            if (($Stock==0) && (substr($Question, 0, 11)=="Stockholder")) {
	                print "<tr class='bodycopybig'><td bgcolor='#FFCC00' colspan=2>";
	                print "Stockholder Proposals";   
	                print "</td>";
	                if ($SelectNameResult[ViewOnly][0]!=1) print "<td bgcolor='#FFCC00'>Vote</td>";
	                print "</tr>";
	                $Stock=1;
	            }
	        }
	        
	        print "<tr bgcolor='FFFFFF'><td valign=top rowspan=".($SelectAn_Rows+1).">";
	        if ($web_companyvote_id=="114") {
	            print "$Question";
	        } else {
	            print "<span class='bodycopybold'>$Question</span>";
	        }
	        if ($SelectNameResult[ViewOnly][0]!=1) {
	            if ($NumAnswers>1) {
	                if ($web_companyvote_id!=94) print "<br> <span class='bodycopyitalic'>(Up to $NumAnswers responses allowed)</span>";
	            } elseif ($NumAnswers==1) {
	                if (!in_array($web_companyvote_id, array(94, 123))) print "<br> <span class='bodycopyitalic'>(Only one response allowed)</span>";
	            }
	            print "</td></tr>";
	            if ($TextAnswer) {
	                print "<tr><td>&nbsp;&nbsp;</td>";
	                print "<td><textarea name='AnswerText".$QuestionID."' rows=2 cols=30>";
	                for ($h=0;$h<$GetHoldVotesRows;$h++) {
	                	if ($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) {
	                		print $GetHoldVotesResult[AnswerText][$h];
	                		break;
						}
					}
	                print "</textarea></td>";
	                print "</tr>";   
	            } else {
				    for ($a=0;$a<$SelectAn_Rows;$a++) {
					    $col_SQL="show columns from tblAnswers";
					    $col_Result = Perform_Select_Query ($col_SQL, $db);
					    $col_Rows = Get_Rows($col_SQL, $db);
					    for ($row=0;$row < $col_Rows; $row++)   {
					            $varname=$col_Result[Field][$row];
					            $$varname=$SelectAn_Result[$varname][$a];
					    }	
	                    print "<tr>";
	                    print "<td>&nbsp;&nbsp;</td><td valign=top align=left>";
	                    if ($NumAnswers==1) {
	                        print "<input type=radio name='Answer".$QuestionID."' value='$AnswerID'";
			                for ($h=0;$h<$GetHoldVotesRows;$h++) {
			                	if (($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) && ($GetHoldVotesResult[AnswerPtr][$h]==$AnswerID)) {
			                		print " CHECKED ";
			                		break;
								}
							}
							print " >$Answer";
	                    } else {
	                        print "<input type=checkbox name='Answer".$QuestionID.$AnswerID."' value='1' ";
			                for ($h=0;$h<$GetHoldVotesRows;$h++) {
			                	if (($GetHoldVotesResult[QuestionPtr][$h]==$QuestionID) && ($GetHoldVotesResult[AnswerPtr][$h]==$AnswerID)) {
			                		print " CHECKED ";
			                		break;
								}
							}
	                        print "onClick=\"anyCheck".$QuestionID."(document.VoteForm.Answer".$QuestionID.$AnswerID.")\";>$Answer";
	                    }
	                    print "</td>";
					    print "</tr>";	
				    }
			    }
	        }
	        print "<tr><td colspan='3'><img src='images/spacer-gray.gif' width='100%' height='2'></td></tr>";
			
		}
		print "</table><br>";
	}
	
	if ($SelectNameResult[ViewOnly][0]!=1) {
		print "<span  class='bodycopy'>";
		
		if (!$UseMaster) {
			print $SelectNameResult[Step1BottomText][0];
			print "<br><br><a name=bottom><input type=image src='images/reviewballot.gif'>";
			print "&nbsp;&nbsp;<input type=image src='images/clearballot.gif' onclick='clearForm(this.form);return false;'>";
			print "</span>";
		}
	}
	print "</form>";
	
} elseif ($_SESSION['StepNumber']==2) {
	// 
	// User has voted
	// Show confirmation and allow to return to previous step
	// or submit vote
	//
	
	?>
	<script type="text/javascript">
	function sendOff(){
	   //if ((document.VoteForm.Email.value ==
	   //     document.VoteForm.EmailCheck.value)&&(good)){
	      // This is where you put your action
	      // if name and email addresses are good.
	      // We show an alert box, here; but you can
	      // use a window.location= 'http://address'
	      // to call a subsequent html page, 
	      // or a Perl script, etc.
	      //alert("Name and email address fields verified good.")
	   //}     
	   var check;
	   if (document.VoteForm.Email.value !=
	          document.VoteForm.EmailCheck.value){
	          alert('Both e-mail address entries must match.');
			  return false;
	   } else {
		   <? 
		  	if ($SelectNameResult[NumBoardVotes][0]>0) {
		   ?>
			check=anyCheckTotal();
		   <? } else { ?>
			   check=true;
			<? } ?>
		   return check;
	   }
	}
	</script>
	
	<?
	print "</div></div><table width=100% cellspacing=5 cellpadding=5 class='bodycopy'><tr><td>";
	
	print "Below are the results of your ballot. <b>You must click 'Submit Ballot' at the bottom of the page for your vote to be recorded.</b> <br><br>";
	
	
	print "<span class='bodycopy'>";
	print get_page($_SESSION['EmailMessage']);
	print "</span></td></tr></table>";

	print "<hr noshade height=1>";
	print "<center>";
	print "<span class='bodycopy'>";
	print "If you wish to change your vote, please click the 'Recast Ballot' button (do NOT click your browser's BACK button!).<br><br>";
	print "<form action='$PHP_SELF' method=POST>";
	print "<input type=hidden name=SubmitRecast value=1>";
	print "<input type=hidden name=web_voter_id value='".$_POST['web_voter_id']."'>";
	print "<input type=hidden name=web_companyvote_id value='".$_POST['web_companyvote_id']."'>";
	print "<input type=image src='images/recastballot.gif'>";
	print "</form>";
	print "</span>";
	
	if ($SelectNameResult[ViewOnly][0]!=1) {
		print "<form action='$PHP_SELF' method=POST name=VoteForm  onSubmit='return sendOff();'>";
		print "<span  class='bodycopy'>";
		if ($SelectNameResult[EmailConfirm][0]) {
			print "<table class='bodycopy'><tr><td>Enter Email Address if you wish to receive your vote results via email:</td> ";
			print "<td><input type=text size=30 name=Email value='$WebEmail'></td></tr>";
			print "<tr><td>Confirm Email Address:</td><td><input type=text size=30 name=EmailCheck value='$WebEmail'></td></tr></table>";
		}
	    if ($web_companyvote_id==94) {
	       //print "<b>In the final selection, please choose <font color=red>one (1) additional</font> individual (not previously selected)<br>from any category above for six (6) total votes.</b><br><br>";
	    }
		if ($SelectNameResult[BallotProxy][0]=="B") {
			print $SelectNameResult[Step2BottomText][0];
		} else {
			print "By clicking the \"Submit\" button you are authorizing ";
	        if ($web_companyvote_id==98) { 
	            print "each of James R. Moffett, <br>";
			    print "Richard C. Adkerson and Kathleen L. Quirk as proxies ";
	        } elseif ($web_companyvote_id==96) { 
	            print "the Proxy Holders ";
	        } else {
	            print "the Board of Director's<br>";
	            print "proxy committee (as identified in the Board's proxy statement) ";
	        }
	        print "to act on<br>
			your behalf as you have directed on these matters and in their discretion on<br>
			other matters that might come before the meeting.";
		}
	    if ($web_companyvote_id==83) {
	        print "<br><br><b>When finished voting please click submit and be patient while all items are updated.</b>";
	    } else {
	        print "<br><br><b>When finished voting please click \"Submit Ballot\" <u>once</u> and be patient while all nominee votes are updated.</b>";
	    
	    }

		
		print "</span><br><br>";
		print "<input type=hidden name=SubmitFinal value=1>";
		print "<input type=hidden name=web_voter_id value='".$_POST['web_voter_id']."'>";
		print "<input type=hidden name=web_companyvote_id value='".$_POST['web_companyvote_id']."'>";
		print "<input type=image src='images/submitballot.gif'>";
		print "</form>";
	}
	print "</center>";
	

} elseif ($_SESSION['StepNumber']==3) {
	// 
	// final submission page
	// cannot return to previous page
	// show a printer-friendly link
	//	
	print "</div></div><table width=100% cellspacing=5 cellpadding=5 class='bodycopy'><tr><td><b>";
	if ($web_companyvote_id==78) {
	    print "Thank you for casting your ballot and supporting the International Parking Institute. Your
	ballot has been submitted and recorded. ";
	} elseif  ($web_companyvote_id==94) {
	    print "Thank you for taking the time to cast your votes for the IPI 2010 Board of Directors election.  Your ballot has been submitted and recorded.";
	    //print "<br><br><b>For your voting records - please print this page</b><br><br>"; 
	} else {
	    print "Thank you, your ballot has been submitted and recorded.";
	}
	print "</b><br><br>";
	if  ($web_companyvote_id!=94)print "<b>FOR YOUR VOTING RECORDS - PLEASE PRINT THIS PAGE</b><br><br>";
	print "<span class='bodycopy'>";
	
	print get_page($_SESSION['EmailMessage']);
	print "</span></td></tr>";
	//print "<tr><td><a href='print_vote.html' target='_blank'>Print this page</a>";
	print "</table>";
	//$_SESSION['StepNumber']=0;
} else {
	//
	// Error handling
	// just redirect to a blank vote login page
	//
	header("location:vote.html");
}

include "bottom.php"; 
?>
