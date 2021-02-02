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

global $langs, $user;
// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/easycommission.lib.php';
require_once DOL_DOCUMENT_ROOT."/product/class/html.formproduct.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";

// Translations
$langs->loadLangs(["admin", "easycommission@easycommission"]);

// Access control
if(! $user->admin) accessforbidden();
// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$value = GETPOST('value', 'alpha');

$arrayofparameters = [
    'EASYCOMMISSION_MYPARAM1' => ['css' => 'minwidth200', 'enabled' => 1],
    'EASYCOMMISSION_MYPARAM2' => ['css' => 'minwidth500', 'enabled' => 1]
];
$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */
 if (is_array($_POST))
    {
        foreach ($_POST as $key => $val)
        {
        	$reg = array();
            if (preg_match('/^param(\d*)$/', $key, $reg))    // Works for POST['param'], POST['param1'], POST['param2'], ...
            {
                $param = GETPOST("param".$reg[1], 'alpha');
                $value = GETPOST("value".$reg[1], 'alpha');
                if (is_array($value))
                {
                   $_POST["value".$reg[1]] = json_encode($value); // Pour gérer les multiselects avec l'inclusion standard
                }
            }
        }
    }

if((float) DOL_VERSION >= 6) {
    include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';
}

/*
 * View
 */

$form = new Form($db);
$usergroup = new UserGroup($db);

$dirmodels = array_merge(['/'], (array) $conf->modules_parts['models']);

$page_name = "EasyCommissionSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_easycommission@easycommission');

// Configuration header
$head = easycommissionAdminPrepareHead();
dol_fiche_head($head, 'settings', '', -1, "easycommission@easycommission");

// Setup page goes here
$var = 0;

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<table class="noborder" width="100%">';

_setupPrintTitle('EASYCOMMISSION_GLOBAL_CONF_TITLE');

// ****************
// CONFIGURATION **
// ****************
_printInputFormPart('EASYCOMMISSION_USER_GROUP', false, '', array(), $form->select_dolgroups($conf->global->EASYCOMMISSION_USER_GROUP, 'value'.($inputCount+2)));

// TAGS-CATEGORIES
if($conf->categorie->enabled) {
    $cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 1);
    $c = new Categorie($db);
    $cats = $c->containing($object->id, Categorie::TYPE_PRODUCT);
    $arrayselected = [];
    if(is_array($cats)) {
        foreach($cats as $cat) {
            $arrayselected[] = $cat->id;
        }
    }

    _printInputFormPart('EASYCOMMISSION_EXCLUDE_CATEGORY', false, '', array(), $form->multiselectarray('value'.($inputCount+1), $cate_arbo, json_decode($conf->global->EASYCOMMISSION_EXCLUDE_CATEGORY), '', 0, '', 0, '50%'));

}

print '<table>';

_updateBtn();

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
    print '<div style="text-align: right;" >';
    print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
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
