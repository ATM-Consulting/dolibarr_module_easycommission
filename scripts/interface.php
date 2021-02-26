<?php

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = dirname(__FILE__) . '/';

global $db, $user, $conf;

// Include and load Dolibarr environment variables
$res = 0;

// LES USERS sont chargés avec main.inc. pas avec master.inc !!!
$res = @include ("../../main.inc.php"); // For root directory
if (! $res)
	$res = @include ("../../../main.inc.php"); // For "custom" directory
if (!$res) die("Include of master fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/easycommission/class/easycommission.class.php';

// Load traductions files requiredby by page
$langs->loadLangs(array("easycommission@easycommission", "other", 'main'));

$action 		        = GETPOST('action');
$lastTableTrId	        = GETPOST('lastTrDataId');
$lineToRemoveId 		= GETPOST('currentIdLine');
$maxLines 		        = GETPOST('maxLines');
$lineValues 		    = GETPOST('lineValues');
$userMatrix 		    = GETPOST('fk_user');
$personalValueState 	= GETPOST('state_MATRIX_PERSONAL_VALUE');

$errormysql = -1;
$jsonResponse = new stdClass();

// Add a Matrix Line
if (isset($action) && $action == 'addLineToMatrix' ) {

    // On insert une ligne dans la matrice
    $out = '';

    $sql = 'SELECT IFNULL(MAX(rowid), 0) as maxid FROM ' .MAIN_DB_PREFIX.'easycommission_matrix';
    $res = $db->query($sql);
    if($res > 0){
        while($obj = $db->fetch_object($res)){
            $out.= '<tr class="oddeven easycommissionValues" data-id='.($maxLines + $obj->maxid).'>';
            $out.= '<td class="maxwidth100 tddict valueInputFrom"><input class="inputFrom" style="width:100%" type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($maxLines + $obj->maxid).'][discountPercentageFrom]'.'" value="'.$obj->discountPercentageFrom.'"></td>';
            $out.= '<td class="maxwidth100 tddict" style="width: 20px">%</td>';
            $out.= '<td class="maxwidth100 tddict valueInputTo"><input class="inputTo" style="width:100%" type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($maxLines + $obj->maxid).'][discountPercentageTo]'.'" value="'.$obj->discountPercentageTo.'"></td>';
            $out.= '<td class="maxwidth100 tddict" style="width: 20px">%</td>';
            $out.= '<td class="maxwidth100 tddict valueCommission"><input class="inputCommission" style="width:100%" type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($maxLines + $obj->maxid).'][commissionPercentage]'.'" value="'.$obj->commissionPercentage.'">';
            $out.= '<td class="maxwidth100 tddict" style="width: 60px">%';
            $out.= '<span class="fas fa-trash pictodelete easycommissionrmvbtn pull-right" style="cursor: pointer;" title="'.$langs->trans('easyCommissionRemoveLine').'"></span>';
            $out.= '</td>';
	        $out.= '</tr>';
        }
    }

	 if ($res == $errormysql){
		 $jsonResponse->error =  $langs->trans("errorCreateLine");
	 } else {
		 $jsonResponse->newMatrixLine = $out;
	 }
}

if (isset($action) && $action == 'removeLineToMatrix') {

    // On supprime une ligne de la matrice
    $sql = 'DELETE FROM ' .MAIN_DB_PREFIX.'easycommission_matrix WHERE rowid = '.$lineToRemoveId;

    $res = $db->query($sql);
    if (!$res){
        $jsonResponse->error =  $langs->trans("errorRemoveLine");
	}
    else {
        $jsonResponse->message = $langs->trans('removeLineSucess');
    }

}

if (isset($action) && $action == 'getEasyCommissionMatrix') {
    $out = '';
    $matrix = new EasyCommission($db);
    $TCommission = $matrix->fetchByArray(0, array('fk_user'=> $userMatrix),false,false);

    if(empty($TCommission)) {
        $matrix->duplicateConfComm($userMatrix);
    }

    if($personalValueState == 'true') {
        $out = $matrix->displayCommissionMatrix($userMatrix);
    }
    else {
        $sql = 'DELETE FROM ' .MAIN_DB_PREFIX.'easycommission_matrix WHERE fk_user = '.$userMatrix;
        $res = $db->query($sql);

        $out = $matrix->displayCommissionMatrix();
    }

    $jsonResponse->getMatrix = $out;
}


print json_encode($jsonResponse, JSON_PRETTY_PRINT);
