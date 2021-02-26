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

// Load translation files required by the page
$langs->loadLangs(["easycommission", "other"]);

$action = GETPOST('action', 'alpha');
$massaction = GETPOST('massaction', 'alpha');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'easycommissionList';   // To manage different context of search

$search_invoice_start = dol_mktime(0, 0, 0, GETPOST('search_invoice_startmonth', 'int'), GETPOST('search_invoice_startday', 'int'), GETPOST('search_invoice_startyear', 'int'));
$search_invoice_end = dol_mktime(23, 59, 59, GETPOST('search_invoice_endmonth', 'int'), GETPOST('search_invoice_endday', 'int'), GETPOST('search_invoice_endyear', 'int'));

$id = GETPOST('id', 'int');
$backtopage = GETPOST('backtopage');
$optioncss = GETPOST('optioncss', 'alpha');

$fk_product = GETPOST('fk_product', 'int');
$fk_company = GETPOST('fk_company', 'int');

$searchCategoryProductOperator = GETPOST('searchCategoryProductOperator', 'int');
$searchCategorySocieteOperator = GETPOST('searchCategorySocieteOperator', 'int');
$TCategoryProduct = GETPOST('search_TCategoryProduct', 'array');
$TCategoryCompany = GETPOST('search_TCategoryCompany', 'array');

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
$diroutputmassaction = $conf->easycommission->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('easycommissionlist'));// Fetch optionals attributes and labels

if (empty($action)) $action='list';


// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(

);

// Definition of fields for lists
$arrayfields=array(
	'fa.datef'=>array('label'=>$langs->trans("date"), 'checked'=>1),
	'det.total_ht'=>array('label'=>$langs->trans("HT"), 'checked'=>1),
	'det.remise_percent'=>array('label'=>$langs->trans("percent"), 'checked'=>1),
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
		$search_array_options=array();
    }

    // Mass actions
	$objectclass="easycommission";
	$uploaddir = $conf->easycommission->multidir_output; // define only because core/actions_massactions.inc.php want it
	$permtoread = $user->admin;
	$permtodelete = $user->admin;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

}

/*
 * View
 */

$form=new Form($db);
$now=dol_now();

$help_url='';
$title = $langs->trans("ReportEasyCommission");
$page_name = "ReportEasyCommission";

llxHeader('', $title, $helpurl);

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);


// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT DISTINCT fa.rowid, fa.datef, det.total_ht, det.remise_percent ,pr.rowid prowid, pr.ref, s.rowid srowid, s.nom, u.rowid user_rowid, u.lastname user_name, ug.nom groupe';

// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= ' FROM '.MAIN_DB_PREFIX.'facture fa ';
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."facturedet det on fa.rowid = det.fk_facture";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid = det.fk_product";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe s on s.rowid = fa.fk_soc";
$sql .=" LEFT JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = pr.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux sc ON sc.fk_soc = s.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = sc.fk_user";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup_user ugu ON ugu.fk_user = u.rowid";
$sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup ug ON ug.rowid = ugu.fk_usergroup";

if ($sall) $sql .= natural_search(array_keys($fieldstosearchall), $sall);

/*if ($search_date_invoice)  $sql .= natural_search('cr.date_invoice', $search_date_invoice);
if ($search_rate)   $sql .= natural_search('cr.rate', $search_rate);*/

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

//$sql.= $db->order($sortfield, $sortorder);
$sql.= " ORDER BY det.rowid ASC";


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
		setEventMessage($langs->trans('No_record_on_multicurrency_rate'), 'warnings');
	}
}

$sql.= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$arrayofselected=is_array($toselect)?$toselect:array();

	$param='';
	if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
	if ($sall) $param.="&sall=".urlencode($sall);

	if ($search_code != '') $param.="&search_code=".urlencode($search_code);

	// Add $param from extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';


	if ($user->admin) $arrayofmassactions['predelete']=$langs->trans("Delete");
	if (in_array($massaction, array('presend','predelete'))) $arrayofmassactions=array();
	$massactionbutton=$form->selectMassAction('', $arrayofmassactions);

	print '<form action="'.$_SERVER["PHP_SELF"].'" method="post" name="formulaire">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	print '<input type="hidden" name="type" value="'.$type.'">';

	print_barre_liste($texte, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, '', 0, $newcardbutton, '', $limit);

	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

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
	if ($massactionbutton) $selectedfields.=$form->showCheckAddButtons('checkforselect', 1);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

	// Lines with input filters
	print '<tr class="liste_titre_filter">';

	// date
	if (! empty($arrayfields['fa.datef']['checked']))
	{
	    print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $langs->trans('From').' ';
		print $form->selectDate($search_invoice_start ? $search_invoice_start : -1, 'search_invoice_start', 0, 0, 1);
		print '</div>';
		print '<div class="nowrap">';
		print $langs->trans('to').' ';
		print $form->selectDate($search_invoice_end ? $search_invoice_end : -1, 'search_invoice_end', 0, 0, 1);
		print '</div>';
		print '</td>';

	}

	// Fields from hook
	$parameters=array('arrayfields'=>$arrayfields);
	$reshook=$hookmanager->executeHooks('printFieldListOption', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '<td class="liste_titre" align="middle">';
	$searchpicto=$form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print '</tr>';

	print '<tr class="liste_titre">';
	if (! empty($arrayfields['fa.datef']['checked']))  print_liste_field_titre($arrayfields['fa.datef']['label'], $_SERVER["PHP_SELF"], "fa.datef", "", $param, "", $sortfield, $sortorder);

	// Hook fields
	$parameters=array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
	$reshook=$hookmanager->executeHooks('printFieldListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', 'align="center"', $sortfield, $sortorder, 'maxwidthsearch ');
	print "</tr>\n";

	$i = 0;
	$totalarray=array();
	while ($i < min($num, $limit))
	{
		$obj = $db->fetch_object($resql);

		print '<tr class="oddeven">';

		// date_invoice
		if (! empty($arrayfields['fa.datef']['checked']))
		{
			print '<td class="tdoverflowmax200">';
			print $obj->datef;
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Fields from hook
		$parameters=array('arrayfields'=>$arrayfields, 'obj'=>$obj);
		$reshook=$hookmanager->executeHooks('printFieldListValue', $parameters);    // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		// Action
		print '<td class="nowrap" align="center">';
		if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		{
			$selected=0;
			if (in_array($obj->rowid, $arrayofselected)) $selected=1;
			print '<a href="'.$_SERVER["PHP_SELF"].'?action=updateRate&amp;id_rate='.$obj->rowid.'" class="like-link " style="margin-right:15px;important">' . img_picto('edit', 'edit') . '</a>';
			print '<a href="'.$_SERVER["PHP_SELF"].'?action=deleteRate&amp;id_rate='.$obj->rowid.'" class="like-link" style="margin-right:45px;important">' . img_picto('delete', 'delete') . '</a>';
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
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


/**
 *
 *
 *      1) $TLines = get_lines()
 *          ==> SQL
 *          ==> retourne toutes les lignes qui nous intéressent
 *
 *      2) $TDatas = get_com()
 *
 *      3) list($TCom, $TUserCom) = split_com($TDatas);
 *
 *      4) TRes = calcul_com(TCom, TUserCom);
 *
 *      5) Afficher tout le tableau avec les com
 *
 *
 *
 */