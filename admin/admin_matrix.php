<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021 SuperAdmin <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    easycommission/admin/setup.php
 * \ingroup easycommission
 * \brief   EasyCommission setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if(! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if(! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if(! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if(! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if(! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if(! $res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/easycommission.lib.php';
require_once ('../class/easycommission.class.php');


global $langs, $user;

// Translations
$langs->loadLangs(["admin", "easycommission@easycommission"]);

if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$value = GETPOST('value', 'alpha');

$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */

if($action == $langs->trans("Save")){

    $msg = '';
    $errorMsg = '';
    $TCommissionnement = GETPOST('TCommissionnement', 'array');

    foreach($TCommissionnement as $fk_commission => $commissionnement){

        $easyCommission = new EasyCommission($db);
        $easyCommission->fetch($fk_commission);

        $easyCommission->discountPercentageFrom = floatval($commissionnement['discountPercentageFrom']);
        $easyCommission->discountPercentageTo = floatval($commissionnement['discountPercentageTo']);
        $easyCommission->commissionPercentage = floatval($commissionnement['commissionPercentage']);


	    if ((! is_numeric($commissionnement['discountPercentageFrom'])) || (! is_numeric($commissionnement['discountPercentageTo'])) || (! is_numeric($commissionnement['commissionPercentage']))) {
		    $errorMsg = $langs->trans('notNumericValueMatrix');
		    break;
	    }
        if (empty($easyCommission->discountPercentageFrom || empty($easyCommission->discountPercentageTo) || empty($easyCommission->commissionPercentage))) {
        	$errorMsg = $langs->trans('emptyValueMatrix');
        	break;
        }
        if ($easyCommission->discountPercentageTo < $easyCommission->discountPercentageFrom) {
	        $errorMsg = $langs->trans('easycommissionMatrixWrongDeltaValue');
	        break;
        }
	    if (($easyCommission->discountPercentageFrom < 0) || ($easyCommission->discountPercentageTo < 0) || ($easyCommission->commissionPercentage < 0)) {
		    $errorMsg = $langs->trans('easycommissionMatrixUnderZeroValue');
		    break;
	    }
	    if (($easyCommission->discountPercentageFrom > 100) || ($easyCommission->discountPercentageTo > 100) || ($easyCommission->commissionPercentage > 100)) {
		    $errorMsg = $langs->trans('easycommissionMatrixOver100Value');
		    break;
	    }

        if(! empty($easyCommission->id)){
            $res = $easyCommission->update($user);
            if($res > 0){
                $msg = $langs->trans('SetupSaved');
            }
        } else {
            $easyCommission->create($user);
        }
    }

    $msgToDisplay = ! empty($errorMsg) ? $errorMsg : $msg;
    $typeOfMsgToDisplay = ! empty($errorMsg) ? 'errors' : 'mesgs';
    setEventMessage($msgToDisplay, $typeOfMsgToDisplay);
}


/*
 * View
 */

llxHeader('', $langs->trans("EasyCommissionSetup"), $help_url);


$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("EasyCommissionSetup"), $linkback, 'object_easycommission@easycommission');


$head = easycommissionAdminPrepareHead();

dol_fiche_head($head, 'matrix', $langs->trans("commissioningMatrix"), -1, '');

// Setup page goes here
$var = 0;


print '<form class="easycommissionForm" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<table class="noborder" width="100%">';

_setupPrintTitle('EASYCOMMISSION_MATRIX_CONF_TITLE');


$matrix = new easyCommission($db);
print $matrix->displayCommissionMatrix();


_updateBtn();

print '</table>';

print '</form>';

// Page end
dol_fiche_end();

llxFooter();
$db->close();

/**
 * Display title
 *
 * @param string $title
 */
function _setupPrintTitle($title = "", $width = 300) {
    global $langs;
    print '<tr class="liste_titre">';
    print '<th colspan="3">'.$langs->trans($title).'</th>'."\n";
    print '</tr>';
}

/**
 * Print an update button
 *
 * @return void
 */
function _updateBtn() {
    global $langs;
    print '<div style="text-align: right;margin-right: 15px" >';
    print '<input name="action" type="submit" class="butAction" value="'.$langs->trans("Save").'">';
    print '</div>';
}

/**
 * Print a On/Off button
 *
 * @param string $confkey the conf key
 * @param bool   $title   Title of conf
 * @param string $desc    Description
 *
 * @return void
 */
function _printOnOff($confkey, $title = false, $desc = '') {
    global $var, $bc, $langs;
    print '<tr class="oddeven">';
    print '<td>'.($title ? $title : $langs->trans($confkey));
    if(! empty($desc)) {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td class="center" width="20">&nbsp;</td>';
    print '<td class="right">';
    print ajax_constantonoff($confkey);
    print '</td></tr>';
}

/**
 * Print a form part
 *
 * @param string $confkey the conf key
 * @param bool   $title   Title of conf
 * @param string $desc    Description of
 * @param array  $metas   html meta
 * @param string $type    type of input textarea or input
 * @param bool   $help    help description
 *
 * @return void
 */
function _printInputFormPart($confkey, $title = false, $desc = '', $metas = [], $type = 'input', $help = false, $moreHtmlBefore = '', $moreHtmlAfter = '') {
    global $var, $bc, $langs, $conf, $db, $inputCount;
    $var = ! $var;
    _curentInputIndex(true);
    $form = new Form($db);

    $defaultMetas = [
        'name' => _curentInputValue()
    ];

    if($type != 'textarea') {
        $defaultMetas['type'] = 'text';
        $defaultMetas['value'] = $conf->global->{$confkey};
    }

    $metas = array_merge($defaultMetas, $metas);
    $metascompil = '';
    foreach($metas as $key => $values) {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }

    print '<tr '.$bc[$var].'>';
    print '<td>';

    if(! empty($help)) {
        print $form->textwithtooltip(($title ? $title : $langs->trans($confkey)), $langs->trans($help), 2, 1, img_help(1, ''));
    }
    else {
        print $title ? $title : $langs->trans($confkey);
    }

    if(! empty($desc)) {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }

    print '</td>';
    print '<td class="center" width="20">&nbsp;</td>';
    print '<td class="right">';

    print $moreHtmlBefore;

    print _curentParam($confkey);

    print '<input type="hidden" name="action" value="setModuleOptions">';
    if($type == 'textarea') {
        print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
    }
    else if($type == 'input') {
        print '<input '.$metascompil.'  />';
    }
    else {
        print $type;
    }

    print $moreHtmlAfter;

    print '</td></tr>';
}

function _curentInputIndex($next = false) {
    global $inputCount;

    if(empty($inputCount)) {
        $inputCount = 1;
    }

    if($next) {
        $inputCount++;
    }

    return $inputCount;
}

function _curentParam($confkey) {
    return '<input type="hidden" name="param'._curentInputIndex().'" value="'.$confkey.'">';
}

function _curentInputValue($offset = 0) {
    return 'value'.(_curentInputIndex() + $offset);
}
