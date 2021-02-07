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

$errormysql = -1;
$jsonResponse = new stdClass();


// Add a Matrix Line
if (isset($action) && $action == 'addLineToMatrix' ) {

    // On insert une ligne dans la matrice
    $out = '';

    $sql = 'SELECT MAX(rowid) as maxid FROM ' .MAIN_DB_PREFIX.'easycommission_matrix';
    $res = $db->query($sql);
    if($res > 0){
        while($obj = $db->fetch_object($res)){
            $out.= '<tr data-id='.($lastTableTrId+1).'>';
            $out.= '<td class="maxwidth100 tddict"><input type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($lastTableTrId+1).'][discountPercentageFrom]'.'" value="'.$obj->discountPercentageFrom.'">%</td>';
            $out.= '<td class="maxwidth100 tddict"><input type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($lastTableTrId+1).'][discountPercentageTo]'.'" value="'.$obj->discountPercentageTo.'">%</td>';
            $out.= '<td align="left"><input type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.($lastTableTrId+1).'][commissionPercentage]'.'" value="'.$obj->commissionPercentage.'">%';
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


print json_encode($jsonResponse, JSON_PRETTY_PRINT);
