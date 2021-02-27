<?php

/**
 *  \file       htdocs/custom/easycommission/report_list.php
 *  \ingroup    easycommission
 *  \brief      Page to list easycommission report
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
if(! $res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if(! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if(! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if(! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once(__DIR__.'/class/easycommission.class.php');
require_once(__DIR__.'/class/easycommissionTools.class.php');

// Load translation files required by the page
$langs->loadLangs(["easycommission", "other"]);

$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'easycommissionList';   // To manage different context of search
$id = GETPOST('id', 'int');
$backtopage = GETPOST('backtopage');
$optioncss = GETPOST('optioncss', 'alpha');
$fk_product = GETPOST('fk_product', 'int');
$search_sale = GETPOST('search_sale', 'int');

$search_invoice_start = dol_mktime(0, 0, 0, GETPOST('search_invoice_startmonth', 'int'), GETPOST('search_invoice_startday', 'int'), GETPOST('search_invoice_startyear', 'int'));
$dateStart = $search_invoice_start ? $search_invoice_start : '';

$search_invoice_end = dol_mktime(23, 59, 59, GETPOST('search_invoice_endmonth', 'int'), GETPOST('search_invoice_endday', 'int'), GETPOST('search_invoice_endyear', 'int'));
$dateEnd = $search_invoice_end ? $search_invoice_end : '';


// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');
if(empty($page) || $page == -1) {
    $page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array of hooks
$object = new EasyCommission($db);
$form = new Form($db);
$hookmanager->initHooks(array('easycommissionlist'));// Fetch optionals attributes and labels

if (empty($action)) $action='list';


// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'fa.datef'=>"date",
	'fa.ref'=>"facref",
	'det.total_ht'=>"HT",
	'det.remise_percent'=>"percent"
);

// Definition of fields for lists
$arrayfields=array(
	'fa.fk_soc'=>array('label'=>$langs->trans("Client / Commercial"), 'checked'=>1),
	'fa.ref'=>array('label'=>$langs->trans("Réf"), 'checked'=>1),
	'fa.datef'=>array('label'=>$langs->trans("date"), 'checked'=>1),
	'det.total_ht'=>array('label'=>$langs->trans("HT"), 'checked'=>1),
	'det.remise_percent'=>array('label'=>$langs->trans("Remise"), 'checked'=>1),
);


$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if(empty($reshook)) {
    // Selection of new fields
    include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if(GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
    {
		$sall="";
		$search_invoice_start="";
		$search_invoice_end="";
		$search_sale = '';
		$search_array_options=array();
    }

}

/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);
$now = dol_now();

$help_url ='';
$title = $langs->trans("ReportEasyCommission");
$page_name = "ReportEasyCommission";

llxHeader('', $title, $helpurl);

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);


// Build and execute select
// Get all the facture lines corresponding to the conditions
// --------------------------------------------------------------------
$sql = "SELECT DISTINCT fa.rowid facrowid, fa.ref facref, fa.datef, det.total_ht, det.remise_percent ,pr.rowid prowid, pr.ref, s.rowid srowid, s.nom, u.rowid user_rowid, ugu.fk_user, ug.nom groupe";

// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= " FROM ".MAIN_DB_PREFIX."facture fa ";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."facturedet det on fa.rowid = det.fk_facture";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid = det.fk_product";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe s on s.rowid = fa.fk_soc";
$sql .=" LEFT JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = pr.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux sc ON sc.fk_soc = s.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = sc.fk_user";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup_user ugu ON ugu.fk_user = u.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup ug ON ug.rowid = ugu.fk_usergroup";

if ($sall) $sql .= natural_search(array_keys($fieldstosearchall), $sall);

$sql.= " WHERE det.fk_product NOT IN (";
$sql.= " SELECT cp.fk_product ";
$sql.= " FROM ".MAIN_DB_PREFIX."categorie_product cp";
$sql.= " WHERE cp.fk_categorie = ".$conf->global->EASYCOMMISSION_EXCLUDE_CATEGORY;
$sql.= ")";

// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= " AND ug.rowid = ".$conf->global->EASYCOMMISSION_USER_GROUP;

if ( ! empty ($search_invoice_start) && ! empty($search_invoice_end)) $sql .= " AND fa.datef between '".date('Y-m-d', $search_invoice_start)."' and '".date('Y-m-d', $search_invoice_end)."'";
if ( ! empty ($search_invoice_start) && empty($search_invoice_end)) $sql .= " AND fa.datef between '".date('Y-m-d', $search_invoice_start)."' and '".dol_print_date(dol_now(), '%Y-%m-%d')."'";
if ( empty ($search_invoice_start) && ! empty($search_invoice_end)) $sql .= " AND fa.datef between '".dol_print_date(dol_now(), '%Y-%m-%d')."' and ".date('Y-m-d', $search_invoice_end)."'";

$sql.= " ORDER BY det.rowid ASC, ugu.fk_user";

$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$result = $db->query($sql);

	if ($result){
		$nbtotalofrecords = $db->num_rows($result);
		if (($page * $limit) > $nbtotalofrecords)	// if total resultset is smaller then paging size (filtering), goto and load page 0
		{
			$page = 0;
			$offset = 0;
		}
	}else {
		setEventMessage($langs->trans('Error'), 'warnings');
	}
}

$sql.= $db->plimit($limit + 1, $offset);

$EasyCom = new EasyCommissionTools($db);
$TDatas = $EasyCom->getAllCommissions();

list($TCom, $TUserCom) = $EasyCom->split_com($TDatas);

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$arrayofselected=is_array($toselect)?$toselect:array();

	$param='';
	if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
	if ($sall) $param.="&sall=".urlencode($sall);

	// Add $param from extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

	print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">'; // Dolibarr V12
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="fk_product" value="'.$fk_product.'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';


	if ($sall)
	{
		foreach ($fieldstosearchall as $key => $val) $fieldstosearchall[$key]=$langs->trans($val);
		print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $sall) . join(', ', $fieldstosearchall).'</div>';
	}

	// Filter on categories
	$moreforfilter='';

	$parameters=array();
	$reshook=$hookmanager->executeHooks('printFieldPreListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	if (empty($reshook)) $moreforfilter.=$hookmanager->resPrint;
	else $moreforfilter=$hookmanager->resPrint;

	if ($moreforfilter)
	{
		print '<div class="liste_titre liste_titre_bydiv centpercent">';
		print $moreforfilter;
		print '</div>';
	}

	$varpage=empty($contextpage)?$_SERVER["PHP_SELF"]:$contextpage;
	$selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);	// This also change content of $arrayfields

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

	// Lines with input filters
	print '<tr class="liste_titre_filter">';

	// Invoice facturation Date
	if (! empty($arrayfields['fa.datef']['checked']))
	{
	    print '<td class="liste_titre" align="left">';
		print '<div class="nowrap">';
		print $langs->trans('From').' ';
		print $form->selectDate($search_invoice_start ? $search_invoice_start : -1, 'search_invoice_start', 0, 0, 1);
		print $langs->trans('to').' ';
		print $form->selectDate($search_invoice_end ? $search_invoice_end : -1, 'search_invoice_end', 0, 0, 1);
		print '</div>';
		print '</td>';
	}

	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir)
	{
		$langs->load("commercial");
		print '<td class="liste_titre" align="left">';
		print '<div class="divsearchfield">';
		print $langs->trans('EasyCommercial').': ';
		print $formother->select_salesrepresentatives($search_sale, 'search_sale', $user, 0, 1, 'maxwidth200');
		print '</div>';
	}

	// Fields from hook
	$parameters=array('arrayfields'=>$arrayfields);
	$reshook=$hookmanager->executeHooks('printFieldListOption', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '<td class="liste_titre" align="right">';
	$searchpicto=$form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print '</tr>';

	print '<tr class="liste_titre">';
	if (! empty($arrayfields['fa.fk_soc']['checked']))  print_liste_field_titre($arrayfields['fa.fk_soc']['label'], $_SERVER["PHP_SELF"], "fa.fk_soc", "", $param, "", $sortfield, $sortorder);
	if (! empty($arrayfields['fa.ref']['checked']))  print_liste_field_titre($arrayfields['fa.ref']['label'], $_SERVER["PHP_SELF"], "fa.ref", "", $param, "", $sortfield, $sortorder);
	if (! empty($arrayfields['det.total_ht']['checked']))  print_liste_field_titre($arrayfields['det.total_ht']['label'], $_SERVER["PHP_SELF"], "det.total_ht", "", $param, "", $sortfield, $sortorder);
	if (! empty($arrayfields['det.remise_percent']['checked']))  print_liste_field_titre($arrayfields['det.remise_percent']['label'], $_SERVER["PHP_SELF"], "det.remise_percent", "", $param, "", $sortfield, $sortorder);
	print_liste_field_titre('Commission');

	// Hook fields
	$parameters=array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
	$reshook=$hookmanager->executeHooks('printFieldListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', 'align="right"', $sortfield, $sortorder, 'maxwidthsearch ');
	print "</tr>\n";

	$i = 0;
	$totalarray=array();

	while ($i < min($num, $limit))
	{
		$obj = $db->fetch_object($resql);

		$TRes = $EasyCom->calcul_com($obj, $TCom, $TUserCom);

		print '<tr class="oddeven">';

		// Societe
		if (! empty($arrayfields['fa.fk_soc']['checked']))
		{
			$soc = new Societe($db);
			$soc->fetch($obj->srowid);
			$user->fetch($obj->fk_user);

			print '<td class="tdoverflowmax200">';
			print $soc->getNomUrl(1, '', '', 1, '');
			print '</br>';
			print $user->getNomUrl(1, '', '', 1);
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Fac REF
		if (! empty($arrayfields['fa.ref']['checked']))
		{
			print '<td class="tdoverflowmax200">';
			print $obj->facref;
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Facdet total HT
		if (! empty($arrayfields['det.total_ht']['checked']))
		{
			print '<td class="tdoverflowmax200">';
			print round($obj->total_ht, 2);
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Facdet remise
		if (! empty($arrayfields['det.remise_percent']['checked']))
		{
			print '<td class="tdoverflowmax200">';
			print $obj->remise_percent;
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Facdet Commercial Commission
		print '<td class="tdoverflowmax200">';
		print round($TRes['commission'], 2);
		print "</td>\n";
		if (! $i) $totalarray['nbfield']++;

		// Fields from hook
		$parameters=array('arrayfields'=>$arrayfields, 'obj'=>$obj);
		$reshook=$hookmanager->executeHooks('printFieldListValue', $parameters);    // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		if (! $i) $totalarray['nbfield']++;

		print "</tr>\n";
		$i++;
	}

	$db->free($resql);

	print "</table>";
	print "</div>";

	print '</form>';
}
else {
	dol_print_error($db);
}


llxFooter();
$db->close();
