<?php

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
require_once('../class/easycommission.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

global $langs, $user;

// Load translation files required by page
$langs->loadLangs(['companies', 'products', 'admin', 'users', 'languages', 'projects', 'members', 'easycommission@easycommission']);

// Defini si peux lire/modifier permisssions
$canreaduser = ($user->admin || $user->rights->user->user->lire);

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'userihm'; // To manage different context of search

if($id) {
    // $user est le user qui edite, $id est l'id de l'utilisateur edite
    $caneditfield = ((($user->id == $id) && $user->rights->user->self->creer) || (($user->id != $id) && $user->rights->user->user->creer));
}

// Security check
$socid = 0;
if($user->socid > 0) $socid = $user->socid;
$feature2 = (($socid && $user->rights->user->self->creer) ? '' : 'user');

$result = restrictedArea($user, 'user', $id, 'user&user', $feature2);
if($user->id <> $id && ! $canreaduser) accessforbidden();

$dirtop = "../core/menus/standard";
$dirleft = "../core/menus/standard";

// Charge utilisateur edite
$object = new User($db);
$object->fetch($id, '', '', 1);
$object->getrights();

$form = new Form($db);
$formadmin = new FormAdmin($db);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(['usercard', 'userihm', 'globalcard']);

/*
 * Actions
 */

$parameters = ['id' => $socid];
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if(empty($reshook)) {
    if($action == 'update' && ($caneditfield || ! empty($user->admin))) {

        if(! $_POST["cancel"]) {
            $tabparam = [];
            $msg = '';

            if(GETPOST("check_MATRIX_PERSONAL_VALUE") == "on") {
                $tabparam["MATRIX_PERSONAL_VALUE"] = 'checked';
            }
            else {
                $tabparam["MATRIX_PERSONAL_VALUE"] = '';
            }

            $TCommissionnement = GETPOST('TCommissionnement', 'array');

            foreach($TCommissionnement as $fk_commission => $commissionnement) {

                $easyCommission = new EasyCommission($db);
                $easyCommission->fetch($fk_commission);

                $easyCommission->discountPercentageFrom = $commissionnement['discountPercentageFrom'];
                $easyCommission->discountPercentageTo = $commissionnement['discountPercentageTo'];
                $easyCommission->commissionPercentage = $commissionnement['commissionPercentage'];
                $easyCommission->fk_user = $object->id;

                if(! empty($easyCommission->id)) {
                    $res = $easyCommission->update($user);
                    if($res > 0) {
                        $msg = $langs->trans('SetupSaved');
                    }
                }
                else {
                    $easyCommission->create($user);
                }
            }

            setEventMessage($msg);

            $result = dol_set_user_param($db, $conf, $object, $tabparam);

            header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
            exit;
        }
    }
}

/*
 * View
 */

llxHeader();

// List of possible landing pages
$tmparray = ['index.php' => 'Dashboard'];
if(! empty($conf->societe->enabled)) $tmparray['societe/index.php?mainmenu=companies&leftmenu='] = 'ThirdPartiesArea';
if(! empty($conf->projet->enabled)) $tmparray['projet/index.php?mainmenu=project&leftmenu='] = 'ProjectsArea';
if(! empty($conf->holiday->enabled) || ! empty($conf->expensereport->enabled)) $tmparray['hrm/index.php?mainmenu=hrm&leftmenu='] = 'HRMArea'; // TODO Complete list with first level of menus
if(! empty($conf->product->enabled) || ! empty($conf->service->enabled)) $tmparray['product/index.php?mainmenu=products&leftmenu='] = 'ProductsAndServicesArea';
if(! empty($conf->propal->enabled) || ! empty($conf->commande->enabled) || ! empty($conf->ficheinter->enabled) || ! empty($conf->contrat->enabled)) $tmparray['comm/index.php?mainmenu=commercial&leftmenu='] = 'CommercialArea';
if(! empty($conf->comptabilite->enabled) || ! empty($conf->accounting->enabled)) $tmparray['compta/index.php?mainmenu=compta&leftmenu='] = 'AccountancyTreasuryArea';
if(! empty($conf->adherent->enabled)) $tmparray['adherents/index.php?mainmenu=members&leftmenu='] = 'MembersArea';
if(! empty($conf->agenda->enabled)) $tmparray['comm/action/index.php?mainmenu=agenda&leftmenu='] = 'Agenda';

$head = user_prepare_head($object);

$title = $langs->trans("User");

if($action == 'edit') {
    print '<form class="easycommissionForm" method="post" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$id.'">';
    print '<input type="hidden" name="userid" value="'.$object->id.'">';
}

if($action == 'edit') {
    dol_fiche_head($head, 'easycommissionuser', $title, -1, 'user');

    $linkback = '';

    if($user->rights->user->user->lire || $user->admin) {
        $linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
    }

    dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

    if(! empty($conf->use_javascript_ajax)) {
        ?>
        <script type="text/javascript">
            $(document).ready(function () {

                // Matrix disabled until we click on "use personal value"
                if (! $('#check_MATRIX_PERSONAL_VALUE').is(':checked')) {
                    handleDisplayDisablePersonalValue();
                }


                $('#check_MATRIX_PERSONAL_VALUE').click(function () {

                    var currentTable = $('div.easycommissionmatrixdiv');

                    $.ajax({
                        url: "<?php print dol_buildpath('easycommission/scripts/interface.php', 1)?>",
                        method: 'POST',
                        dataType: 'json',  // format de réponse attendu
                        data: {
                            fk_user: <?php print $object->id ?>,
                            state_MATRIX_PERSONAL_VALUE: $('#check_MATRIX_PERSONAL_VALUE').is(':checked'),
                            action: 'getEasyCommissionMatrix'
                        },
                        success: function (data) {
                            if (!data.error) {
                                currentTable.after(data.getMatrix);
                                currentTable.remove();
                                if (! $('#check_MATRIX_PERSONAL_VALUE').is(':checked')) {
                                    handleDisplayDisablePersonalValue();
                                } else {
                                    handleDisplayCheckedPersonalValue();
                                }
                            } else {
                                //
                            }
                        }
                    });


                });


                function handleDisplayDisablePersonalValue() {
                    $('input[type=number]').attr('disabled', 'disabled');
                    $('span.easycommissionaddbtn').closest('a').hide();
                    $('span.easycommissionrmvbtn').hide();
                    $('input[type=number]').parent().closest('div').css('color', 'grey');
                    $('td.maxwidth100.tddict').css('color', 'grey');
                    $('input[type=number]').css('--colortext', 'grey');
                }

                function handleDisplayCheckedPersonalValue() {
                    $('input[type=number]').removeAttr('disabled');
                    $('span.easycommissionaddbtn').closest('a').show();
                    $('span.easycommissionrmvbtn').show();
                    $('input[type=number]').parent().closest('div').css('color', 'black');
                    $('td.maxwidth100.tddict').css('color', 'black');
                    $('input[type=number]').css('--colortext', 'black');
                }

            });
        </script><?php
    }

    clearstatcache();

    print '<table class="noborder centpercent tableforfield">';
    print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("PersonalValue").'</td></tr>';

    // Matrix personal value
    print '<tr class="oddeven"><td>'.$langs->trans("matrixPersonalValue").'</td>';
    print '<td class="nowrap"><input class="oddeven" name="check_MATRIX_PERSONAL_VALUE" id="check_MATRIX_PERSONAL_VALUE" type="checkbox" '.(! empty($object->conf->MATRIX_PERSONAL_VALUE) ? " checked" : "");
    print '> '.$langs->trans("UsePersonalValue").'</td>';
    print '</tr>';

    print '</table><br>';

    $matrix = new easyCommission($db);

    if($object->conf->MATRIX_PERSONAL_VALUE == 'checked') {
        print $matrix->displayCommissionMatrix($object->id);
    }
    else {
        print $matrix->displayCommissionMatrix();
    }

    print '<div class="center">';
    print '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'">';
    print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</div>';

    print '</form>';

    dol_fiche_end();
}
else {

    dol_fiche_head($head, 'easycommissionuser', $title, -1, 'user');

    $linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

    dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

    print '<table class="noborder centpercent tableforfield">';
    print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("PersonalValue").'</td></tr>';

    // Matrix personal value
    print '<tr class="oddeven"><td>'.$langs->trans("matrixPersonalValue").'</td>';
    print '<td class="nowrap"><input class="oddeven" name="check_MATRIX_PERSONAL_VALUE" disabled id="check_MATRIX_PERSONAL_VALUE" type="checkbox" '.(! empty($object->conf->MATRIX_PERSONAL_VALUE) ? " checked" : "");
    print '> '.$langs->trans("UsePersonalValue").'</td>';

    print '</tr>';

    print '</table><br>';

    dol_fiche_end();

    print '<div class="tabsAction" style="margin:10px 0em 10px 0em">';

    if($caneditfield || ! empty($user->admin))       // Si utilisateur edite = utilisateur courant (pas besoin de droits particulier car il s'agit d'une page de modif d'output et non de données) ou si admin
    {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&amp;id='.$object->id.'">'.$langs->trans("Modify").'</a>';
    }
    else {
        print "<a class=\"butActionRefused classfortooltip\" title=\"".$langs->trans("NotEnoughPermissions")."\" href=\"#\">".$langs->trans("Modify")."</a>";
    }

    print '</div>';

    $matrix = new easyCommission($db);

    if($object->conf->MATRIX_PERSONAL_VALUE == 'checked') {
        print $matrix->displayCommissionMatrix($object->id);
    }
    else {
        print $matrix->displayCommissionMatrix();
    }

    print '<script type="text/javascript">
        $(document).ready(function() {      
            if ($("#check_MATRIX_PERSONAL_VALUE").prop("disabled")) { 
                $("input[type=number]").attr(\'disabled\', \'disabled\');
                $("span.easycommissionaddbtn").closest("a").remove();
                $("span.easycommissionrmvbtn").remove();
                $("input[type=number]").parent().closest("div").css("color", "grey");
                $("td.maxwidth100.tddict").css("color", "grey");
                $("input[type=number]").css("--colortext", "grey");
            }
        });
        </script>';
}

// End of page
llxFooter();
$db->close();
