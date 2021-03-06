<?php
/* $Id$*/
/*ProcessSerialItems.php takes the posted variables and adds to the SerialItems array
 in either the cartclass->LineItems->SerialItems or the POClass->LineItems->SerialItems */

/********************************************
		Added KEYED Entry values
********************************************/
if ( (isset($_POST['AddBatches']) and $_POST['AddBatches']!='')) {

	for ($i=0;$i < 10;$i++){
		if(isset($_POST['SerialNo' . $i]) and mb_strlen($_POST['SerialNo' . $i])>0){
			if ($ItemMustExist){
				$ExistingBundleQty = ValidBundleRef($StockID, $LocationOut, $_POST['SerialNo' . $i]);
				if ($ExistingBundleQty >0 or ($ExistingBundleQty==1 and $IsCredit=true)){
					$AddThisBundle = true;
					/*If the user enters a duplicate serial number the later one over-writes
					the first entered one - no warning given though ? */
					if (filter_number_format($_POST['Qty' . $i]) > $ExistingBundleQty){
						if ($LineItem->Serialised ==1){
							echo '<br />';
							prnMsg ( $_POST['SerialNo' . $i] . ' ' .
								 _('has already been sold'),'warning' );
							$AddThisBundle = false;
						} elseif ($ExistingBundleQty==0) { /* and its a batch */
							echo '<br />';
							prnMsg ( _('There is none of') . ' '. $_POST['SerialNo' . $i] .
								' '. _('remaining').'.', 'warn');
							$AddThisBundle = false;
						} else {
							echo '<br />';
						 	prnMsg (  _('There is only'). ' ' . $ExistingBundleQty .
									' '._('of') . ' ' . $_POST['SerialNo' . $i] . ' '. _('remaining') . '. ' .
									_('The entered quantity will be reduced to the remaining amount left of this batch/bundle/roll'),
									'warn');
							$_POST['Qty' . $i] = $ExistingBundleQty;
							$AddThisBundle = true;
						}
					}
					if ($AddThisBundle==true){
						$LineItem->SerialItems[$_POST['SerialNo' . $i]] = new SerialItem ($_POST['SerialNo' . $i], ($InOutModifier>0?1:-1) * filter_number_format($_POST['Qty' . $i]));
					}
				} /*end if ExistingBundleQty >0 */
				else {
					echo '<br />';
					prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='.$_POST['SerialNo'. $i] . '" target=_blank>'.$_POST['SerialNo'. $i]. '</a> ' ._('not available') . '...' , '', 'Notice' );
					unset($_POST['SerialNo' . $i]);
				}
			} // end of ItemMustExist
			else {
				//Serialised items can not exist w/ Qty > 0 if we have an $NewQty of 1
				//Serialised items must exist w/ Qty = 1 if we have $NewQty of -1
				$SerialError = false;
				$NewQty = ($InOutModifier>0?1:-1) * filter_number_format($_POST['Qty' . $i]);
				$NewSerialNo = $_POST['SerialNo' . $i];

				if ($LineItem->Serialised){
					$ExistingQty = ValidBundleRef($StockID, $LocationOut, $NewSerialNo);
					if ($NewQty == 1 and $ExistingQty != 0){
						prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> : '. _('The Serial Number being added exists with a Quantity that is not Zero (0)!'), 'error' );
						$SerialError = true;
					} elseif ($NewQty == -1 and $ExistingQty != 1){
						prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> : '. _('The Serial Number being removed exists with a Quantity that is not One (1)!'), 'error');
						$SerialError = true;
					}
				}
				if (isset($_POST['ExpiryDate' . $i])){
					$ExpiryDate = $_POST['ExpiryDate' . $i];
				} else {
					$ExpiryDate ='0000-00-00';
				}
				if (!$SerialError){
					$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($_POST['SerialNo' . $i], $NewQty, $ExpiryDate);
				}
			}
		} /* end if posted Serialno . i is not blank */

	} /* end of the loop aroung the form input fields */
	if (!isset($_POST['Bundles'])) {
		$_POST['Bundles']=0;
	}
	//echo count($_POST['Bundles']);
	for ($i=0;$i < count($_POST['Bundles']) and $_POST['Bundles']!=0;$i++){ /*there is an entry in the multi select list box */
		if ($LineItem->Serialised==1){	/*only if the item is serialised */
			$LineItem->SerialItems[$_POST['Bundles'][$i]] = new SerialItem ($_POST['Bundles'][$i],  ($InOutModifier>0 ? 1:-1) );
		} else {
			list($SerialNo, $Qty) = explode ('/|/', $_POST['Bundles'][$i]);
			if ($Qty != 0) {
				$LineItem->SerialItems[$SerialNo] = new SerialItem ($SerialNo,  $Qty*($InOutModifier>0?1:-1) );
			}
		}
	}

} /*end if the user hit the enter button on Keyed Entry */

 /********************************************
   Add a Sequence of Items and save entries
 ********************************************/
if ( isset($_POST['AddSequence']) and $_POST['AddSequence']!='') {
	// do some quick validation
	$BeginNo =  $_POST['BeginNo'];
	$EndNo   = $_POST['EndNo'];
	if ($BeginNo > $EndNo){
		prnMsg( _('To Add Items Sequentially, the Begin Number must be less than the End Number'), 'error');
	} else {
		$sql = "SELECT serialno FROM stockserialitems
			WHERE serialno BETWEEN '". $BeginNo . "' AND '". $EndNo . "'
			AND stockid = '". $StockID."' AND loccode='". $LocationOut . "'";
		$Qty = ($InOutModifier>0?1:0);
		if ($LineItem->Serialised == 1){
			$sql .= " AND quantity = ".$Qty;
		}
		$SeqItems = DB_query($sql,$db);

		while ($myrow=DB_fetch_array($SeqItems)) {
			$LineItem->SerialItems[$myrow['serialno']] = new SerialItem ($myrow['serialno'], ($InOutModifier>0?1:-1) );
			//force it to Keyed entry for cleanup & manual verification
			$_POST['EntryType'] = 'KEYED';
		}
	}//end of is valid request
} /* end of input by Sequence Number */

/********************************************
  Validate an uploaded FILE and save entries
********************************************/
$valid = true;
if (isset($_POST['EntryType'])
	and $_POST['EntryType']=='FILE'
	and isset($_POST['ValidateFile'])){

	$filename = $_SESSION['CurImportFile']['tmp_name'];

	$handle = fopen($filename, 'r');
	$TotalLines=0;
	$LineItem->SerialItemsValid=false;
	while (!feof($handle)) {
		$contents = trim(fgets($handle, 4096));
		//$valid = $LineItem->SerialItems[$i]->importFileLineItem($contents);
		$pieces  = explode(',',$contents);
		if ($LineItem->Serialised == 1){
		//for Serialised items, we are expecting the line to contain either just the serial no
		//OR a comma delimited file w/ the serial no FIRST
			if(trim($pieces[0]) != ""){
				$valid=false;
				if (mb_strlen($pieces[0]) <= 0 ){
					$valid=false;
				} else {
					$valid=true;
				}
				if ($valid){
					/*If the user enters a duplicate serial number the later one over-writes the first entered one - no warning given though ? */
					$NewSerialNo = $pieces[0];
					$NewQty = ($InOutModifier>0?1:-1);
				}
			} else {
				$valid = false;
			}
		} else {
		//for controlled only items, we must receive: BatchID, Qty in a comma delimited  file
			if($pieces[0] != '' and $pieces[1] != '' and is_numeric(filter_number_format($pieces[1])) and filter_number_format($pieces[1]) > 0 ){
			/*If the user enters a duplicate batch number the later one over-writes
			the first entered one - no warning given though ? */
					//$LineItem->SerialItems[$pieces[0]] = new SerialItem ($pieces[0],  $pieces[1] );
					$NewSerialNo = $pieces[0];
					$NewQty = ($InOutModifier>0?1:-1) * filter_number_format($pieces[1]);
			} else {
					$valid = false;
			}
		}
		$TotalLines++;
		if ($ItemMustExist){
			$ExistingBundleQty = ValidBundleRef($StockID, $LocationOut, $NewSerialNo);
			if ($ExistingBundleQty >0){
				$AddThisBundle = true;
					/*If the user enters a duplicate serial number the later one over-writes the first entered one - no warning given though ? */
					if ($NewQty > $ExistingBundleQty){
							if ($LineItem->Serialised ==1){
									echo '<br />' . '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> '. _('has already been sold'). '.';
									$AddThisBundle = false;
								} elseif ($ExistingBundleQty==0) { /* and its a batch */
									echo '<br />' . _('There is none of'). ' <a href="/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> '. _('remaining') .'.';
									$AddThisBundle = false;
							} else {
									echo '<br />'. _('There is only') . ' ' . $ExistingBundleQty . ' '. _('of') . ' ' .
												'<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> ' . _('remaining') . '. '.
												_('The entered quantity will be reduced to the remaining amount left of this batch/bundle/roll');
									$NewQty = $ExistingBundleQty;
									$AddThisBundle = true;
							}
					}
					if ($AddThisBundle==true){
							$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($NewSerialNo, $NewQty);
					}
			} /*end if ExistingBundleQty >0 */
			else {
				echo '<br />';
				prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a>  ' . _('not available') ,'', 'Notice' );
			}
			if (!$valid) $invalid_imports++;
			// of MustExist
		} else {
			//Serialised items can not exist w/ Qty > 0 if we have an $NewQty of 1
			//Serialised items must exist w/ Qty = 1 if we have $NewQty of -1
			$SerialError = false;
			if ($LineItem->Serialised){
				$ExistingQty = ValidBundleRef($StockID, $LocationOut, $NewSerialNo);
				if ($NewQty == 1 and $ExistingQty != 0){
					prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a>: '. _('The Serial Number being added exists with a Quantity that is not Zero (0)!'), 'error' );
					$SerialError = true;
				} elseif ($NewQty == -1 and $ExistingQty != 1){
					prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> : '. _('The Serial Number being removed exists with a Quantity that is not One (1)!'), 'error');
					$SerialError = true;
				}
			}
			if (!$SerialError){
				$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($NewSerialNo, $NewQty);
			}
			//$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($NewSerialNo, $NewQty);
		}
	}//while (file)
	if ($invalid_imports==0){
		$LineItem->SerialItemsValid=true;
		$_SESSION['CurImportFile']['Processed']=true;
	}
	fclose($handle);
	//we've saved the info we need from the file, so get rid of it
}
/********************************************
  Revalidate Array of Items
	 The point of this is to allow "copying" an array of items from 1 object to another, checking them, and insuring that nothing else
	 is added. So, after the validation, we will exit and NOT allow more items to be added.

********************************************/
if (isset($_GET['REVALIDATE']) or isset($_POST['REVALIDATE'])) {
	$invalid_imports = 0;
	$OrigLineItem = $LineItem; //grab a copy of the old one...
	$LineItem->SerialItems = array(); // and then reset it so we can add back to it.
	foreach ($OrigLineItem->SerialItems as $Item){
		if ($OrigLineItem->Serialised == 1){
			if(trim($Item->BundleRef) != ""){
				$valid=false;
				if (mb_strlen($Item->BundleRef) <= 0 ){
					$valid=false;
				} else {
					$valid=true;
				}
				if ($valid){
					/*If the user enters a duplicate serial number the later one over-writes the first entered one - no warning given though ? */
					$NewSerialNo = $Item->BundleRef;
					$NewQty = ($InOutModifier>0?1:-1) * $Item->BundleQty;
				}
			} else {
				$valid = false;
			}
		} else {
		//for controlled only items, we must receive: BatchID, Qty in a comma delimited  file
			if($Item->BundleRef != "" and $Item->BundleQty != "" and is_numeric($Item->BundleQty) and $Item->BundleQty > 0 ){
			/*If the user enters a duplicate batch number the later one over-writes
			the first entered one - no warning given though ? */
					//$LineItem->SerialItems[$pieces[0]] = new SerialItem ($pieces[0],  $pieces[1] );
					$NewSerialNo = $Item->BundleRef;
					$NewQty = ($InOutModifier>0?1:-1) * $Item->BundleQty;
			} else {
					$valid = false;
			}
		}
		$TotalLines++;
		if ($ItemMustExist){
			$ExistingBundleQty = ValidBundleRef($StockID, $LocationOut, $NewSerialNo);
			if ($ExistingBundleQty >0){
				$AddThisBundle = true;
					/*If the user enters a duplicate serial number the later one over-writes the first entered one - no warning given though ? */
					if ($NewQty > $ExistingBundleQty){
						if ($LineItem->Serialised ==1){
								echo '<br />' . '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> '. _('has already been sold'). '.';
								$AddThisBundle = false;
							} elseif ($ExistingBundleQty==0) { /* and its a batch */
								echo '<br />' . _('There is none of'). ' <a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> '. _('remaining') .'.';
								$AddThisBundle = false;
						} else {
								echo '<br />'. _('There is only') . ' ' . $ExistingBundleQty . ' '. _('of') . ' ' .
											'<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> ' . _('remaining') . '. '.
											_('The entered quantity will be reduced to the remaining amount left of this batch/bundle/roll');
								$NewQty = $ExistingBundleQty;
								$AddThisBundle = true;
						}
					}
					if ($AddThisBundle==true){
							$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($NewSerialNo, $NewQty);
					}
			} /*end if ExistingBundleQty >0 */
			else {
				echo '<br />';
				prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> ' . _('not available') . '...' ,'', 'Notice' );
			}
			if (!$valid) $invalid_imports++;
			// of MustExist
		} else {
			//Serialised items can not exist w/ Qty > 0 if we have an $NewQty of 1
			//Serialised items must exist w/ Qty = 1 if we have $NewQty of -1
			$SerialError = false;
			if ($LineItem->Serialised){
				$ExistingQty = ValidBundleRef($StockID, $LocationOut, $NewSerialNo);
				if ($NewQty == 1 and $ExistingQty != 0){
					prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a>: '. _("The Serial Number being added exists with a Quantity that is not Zero (0)!"), 'error' );
					$SerialError = true;
				} elseif ($NewQty == -1 and $ExistingQty != 1){
					prnMsg( '<a href="'.$RootPath.'/StockSerialItemResearch.php?serialno='. $NewSerialNo . '" target=_blank>'.$NewSerialNo. '</a> : '. _("The Serial Number being removed exists with a Quantity that is not One (1)!"), 'error');
					$SerialError = true;
				}
			}
			if (!$SerialError){
				$LineItem->SerialItems[$NewSerialNo] = new SerialItem ($NewSerialNo, $NewQty);
			} else {
				$invalid_imports++;
			}
		}
	}//foreach OrigItems
	$LineItem->Quantity = sizeof($LineItem->SerialItems);
	if ($invalid_imports > 0){
		prnMsg( _('Finished Validating Items') . ' : ' . $invalid_imports . ' ' . _('problems found. Please research and correct them') . '.', 'warn' );
	} else {
		prnMsg( _('Finished Validating Items').' with NO errors', 'success' );
	}
	include('includes/footer.inc');
	exit;

}//ReValidate

/********************************************
  Process Remove actions
********************************************/
if (isset($_GET['DELETEALL'])){
		$RemAll = $_GET['DELETEALL'];
} else {
		$RemAll = 'NO';
}

if ($RemAll == 'YES'){
		unset($LineItem->SerialItems);
		$LineItem->SerialItems=array();
	unset($_SESSION['CurImportFile']);
}

if (isset($_GET['Delete'])){
		unset($LineItem->SerialItems[$_GET['Delete']]);
}
?>