<?
// vet-list-ajax.php

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "vet-fns.php";

if(isset($_GET)) {
  extract($_GET);
  $clinicId = isset($clinicId) ? $clinicId : -1;
  if($_GET['options'] == 'allVets') echo fetchAllVetOptionsSelecting($selectedVet, $clinicId);
  if($_GET['options'] == 'allClinicChoices') echo fetchAllClinicOptionsSelecting();
}

?>

