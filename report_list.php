<?php

/**
 *  \file       htdocs/custom/easycommission/report_list.php
 *  \ingroup    easycommission
 *  \brief      Page to list easycommission report
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once(__DIR__ . '/class/easycommission.class.php');
require_once(__DIR__ . '/class/easycommissionTools.class.php');

$canreaduser = ($user->admin || $user->rights->easycommission->read);

// Load translation files required by the page
$langs->loadLangs(["easycommission", "other"]);

$action = GETPOST('action', 'alpha');
$toselect = GETPOST('toselect', 'array');
$cancel = GETPOST('cancel', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'easycommissionList';   // To manage different context of search
$id = GETPOST('id', 'int');
$optioncss = GETPOST('optioncss', 'alpha');
$search_sale = GETPOST('search_sale', 'int');

$now = dol_now();
$prevMonthDateStart = date('Y-m-d H:i:s', mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
$nbDaysInPrevMonth = date("t", strtotime($prevMonthDateStart));
$prevMonthDateEnd = date('Y-m-d H:i:s', mktime(23, 59, 59, date("m") - 1, $nbDaysInPrevMonth, date("Y")));

$search_invoice_start = GETPOST('search_fac_date_start', 'int');
if (empty($search_invoice_start)) $search_invoice_start = dol_mktime(0, 0, 0, GETPOST('search_invoice_startmonth', 'int'), GETPOST('search_invoice_startday', 'int'), GETPOST('search_invoice_startyear', 'int'));

$search_invoice_end = GETPOST('search_fac_date_end', 'int');
if (empty($search_invoice_end)) $search_invoice_end = dol_mktime(23, 59, 59, GETPOST('search_invoice_endmonth', 'int'), GETPOST('search_invoice_endday', 'int'), GETPOST('search_invoice_endyear', 'int'));

// Par défaut date de début au premier jour du mois précédent et date de fin au dernier jour du mois précédent
$dateStart = $search_invoice_start ? GETPOST($search_invoice_start, 'alpha') : GETPOST($prevMonthDateStart, 'alpha');
$dateEnd = $search_invoice_end ? GETPOST($search_invoice_end, 'alpha') : GETPOST($prevMonthDateEnd, 'alpha');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');

if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (!$canreaduser) accessforbidden();

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array of hooks
$object = new EasyCommission($db);
$form = new Form($db);
$hookmanager->initHooks(array('easycommissionlist'));// Fetch optionals attributes and labels

if (empty($action)) $action = 'list';

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'fa.fk_soc' => "soc",
	'fa.datef' => "date",
	'fa.ref' => "facref",
	'det.total_ht' => "TotalHT",
	'det.remise_percent' => "percent",
	'det.fk_product' => "product"
);

// Definition of fields for lists
$arrayfields = array(
	'fa.ref' => array('label' => $langs->trans("EasyComRef"), 'checked' => 1),
	'det.total_ht' => array('label' => $langs->trans("EasyComTotalHT"), 'checked' => 1),
	'det.remise_percent' => array('label' => $langs->trans("EasyComRemise"), 'checked' => 1),
	'det.fk_product' => array('label' => $langs->trans("EasyComProduct"), 'checked' => 1),
);


$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
	{
		$sall = "";
		$search_invoice_start = "";
		$search_invoice_end = "";
		$search_sale = '';
		$search_array_options = array();
	}

	$objectclass = "EasyCom";
	$uploaddir = $conf->easycommission->multidir_output; // define only because core/actions_massactions.inc.php want it
	include DOL_DOCUMENT_ROOT . '/core/actions_massactions.inc.php';
}

/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);
$facturestatic = new Facture($db);
$productstatic = new Product($db);
$socstatic = new Societe($db);

/*
 * Récupération du tableau de toutes les commissions confondues (globale $TCom + overload des utilisateurs $TUserCom)
 */
$TDatas = EasyCommissionTools::getAllCommissions();
list($TCom, $TUserCom) = EasyCommissionTools::split_com($TDatas);

$title = $langs->trans("ReportEasyCommission");
$page_name = "ReportEasyCommission";

llxHeader('', $title);

// Subheader
print load_fiche_titre($langs->trans($page_name));

// Build and execute select
// Two conditions : if we select a commercial or not
// --------------------------------------------------------------------
$sqlDateColFilter = $conf->global->EASYCOMMISSION_FILTER_ON_BILLING_DATE ? 'fa.datef' : 'fa.date_closing'; // Global sql filter according to module settings

$sql = "SELECT DISTINCT fa.rowid facrowid, fa.ref facref, fa.datef, det.rowid detrowid, det.fk_product detproduct, det.fk_facture fk_facture, det.total_ht, det.remise_percent,
pr.rowid prowid, pr.ref productref, pr.label productlabel, pr.tosell productsell, pr.tobuy productbuy, s.rowid srowid, s.nom, u.rowid user_rowid, ugu.fk_user, ug.nom groupe, pm.datep paiement";

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql .= " FROM " . MAIN_DB_PREFIX . "facture fa ";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facturedet det on fa.rowid = det.fk_facture";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product pr ON pr.rowid = det.fk_product";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe s on s.rowid = fa.fk_soc";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_product cp ON cp.fk_product = pr.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux sc ON sc.fk_soc = s.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = sc.fk_user";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "usergroup_user ugu ON ugu.fk_user = u.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "usergroup ug ON ug.rowid = ugu.fk_usergroup";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "paiement_facture pmf ON fa.rowid = pmf.fk_facture";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "paiement pm ON pm.rowid = pmf.fk_paiement";

if ($sall) $sql .= natural_search(array_keys($fieldstosearchall), $sall);

$sql .= " WHERE fa.fk_statut = " . Facture::STATUS_CLOSED;
$sql .= " AND ug.rowid = " . $conf->global->EASYCOMMISSION_USER_GROUP;
if (!empty($search_sale)) $sql .= " AND ugu.fk_user = " . $search_sale;

$sql .= " AND det.fk_product NOT IN (";
$sql .= " SELECT cp.fk_product ";
$sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product cp";
$sql .= " WHERE cp.fk_categorie = " . $conf->global->EASYCOMMISSION_EXCLUDE_CATEGORY;
$sql .= ")";

// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters);    // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldSelect', $parameters);    // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

if (!empty ($search_invoice_start) && !empty($search_invoice_end)) $sql .= " AND " . $sqlDateColFilter . " between '" . date('Y-m-d H:i:s', $search_invoice_start) . "' and '" . date('Y-m-d H:i:s', $search_invoice_end) . "'";
else $sql .= " AND " . $sqlDateColFilter . " between '" . $prevMonthDateStart . "' and '" . $prevMonthDateEnd . "'";

if (!empty ($search_invoice_start) && empty($search_invoice_end)) $sql .= " AND " . $sqlDateColFilter . " between '" . date('Y-m-d H:i:s', $search_invoice_start) . "' and '" . dol_print_date(dol_now(), '%Y-%m-%d') . "'";

if (empty ($search_invoice_start) && !empty($search_invoice_end)) $sql .= " AND " . $sqlDateColFilter . " between '" . dol_print_date(dol_now(), '%Y-%m-%d') . "' and " . date('Y-m-d H:i:s', $search_invoice_end) . "'";

if (!empty($search_sale)) {
	$sql .= ' GROUP BY det.rowid ';
} else {
	$sql .= ' GROUP BY det.rowid, ugu.fk_user ';
}

$sql .= $db->order($sortfield, $sortorder);

$nbtotalofrecords = '';

if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);

	if ($resql) {
		$nbtotalofrecords = $db->num_rows($resql);

		if (($page * $limit) > $nbtotalofrecords)    // if total resultset is smaller then paging size (filtering), goto and load page 0
		{
			$page = 0;
			$offset = 0;
		}
	} else {
		setEventMessage($langs->trans('Error'), 'warnings');
	}
}

// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if(is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
	$num = $nbtotalofrecords;
}
else {
	if($limit) $sql .= $db->plimit($limit + 1, $offset);
}

if ($resql) {
	if (empty($search_sale)) {
		// On construit le tableau des totaux par utilisateur
		$TUsersTotaux = array();

		while ($obj = $db->fetch_object($resql)) {
			$TUsersTotaux[$obj->fk_user]['total_ht'] += $obj->total_ht;
			$TUsersTotaux[$obj->fk_user]['commission'] += EasyCommissionTools::calcul_com($obj, $TCom, $TUserCom, true);
		}
		$nbtotalofrecords = count($TUsersTotaux);
	} else $nbtotalofrecords = $db->num_rows($resql);

	if (($page * $limit) > $nbtotalofrecords) // if total resultset is smaller then paging size (filtering), goto and load page 0
	{
		$page = 0;
		$offset = 0;
	}

	$num = $db->num_rows($resql);
	$arrayofselected = is_array($toselect) ? $toselect : array();

	$param = '';
	if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage=' . urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit=' . urlencode($limit);
	if ($sall) $param .= "&sall=" . urlencode($sall);

	if ($search_invoice_start || $prevMonthDateStart) $param .= "&search_fac_date_start=" . urlencode($search_invoice_start);
	if ($search_invoice_end || $prevMonthDateEnd) $param .= "&search_fac_date_end=" . urlencode($search_invoice_end);
	if ($search_sale != '') $param .= "&search_sale=" . urlencode($search_sale);
	// Add $param from extra fields
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';


	print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">' . "\n";
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
	print '<input type="hidden" name="token" value="' . newToken() . '">'; // Dolibarr V12
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
	print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
	print '<input type="hidden" name="page" value="' . $page . '">';
	print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';

	print_barre_liste($langs->trans('EasyReport'), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, '', 0, '', '', $limit);

	include DOL_DOCUMENT_ROOT . '/core/tpl/massactions_pre.tpl.php';

	if ($sall) {
		foreach ($fieldstosearchall as $key => $val) $fieldstosearchall[$key] = $langs->trans($val);
		print '<div class="divsearchfieldfilter">' . $langs->trans("FilterOnInto", $sall) . join(', ', $fieldstosearchall) . '</div>';
	}

	// Filter on categories
	$moreforfilter = '';

	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
	else $moreforfilter = $hookmanager->resPrint;

	if ($moreforfilter) {
		print '<div class="liste_titre liste_titre_bydiv centpercent">';
		print $moreforfilter;
		print '</div>';
	}

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);    // This also change content of $arrayfields
	if ($massactionbutton) $selectedfields .= $form->showCheckAddButtons('checkforselect', 1);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";

	// Lines with input filters
	print '<tr class="liste_titre_filter">';

	// Invoice facturation Date
	print '<td class="liste_titre">';
	print $langs->trans('From') . ' ';
	print $form->selectDate($search_invoice_start ? $search_invoice_start : $prevMonthDateStart, 'search_invoice_start', 0, 0, 1);
	print $langs->trans('to') . ' ';
	print $form->selectDate($search_invoice_end ? $search_invoice_end : $prevMonthDateEnd, 'search_invoice_end', 0, 0, 1);
	print '</td>';


	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir) {
		$langs->load("commercial");
		print '<td class="liste_titre" align="left">';
		print $langs->trans('EasyCommercial') . ': ';
		print $formother->select_salesrepresentatives($search_sale, 'search_sale', $user, 0, 1, 'maxwidth200');
		print '</td>';
	}

	if (!empty($arrayfields['fa.ref']['checked'])) print '<td class="liste_titre" align="left"></td>';
	if (!empty($arrayfields['det.total_ht']['checked'])) print '<td class="liste_titre" align="left"></td>';
	if (!empty($arrayfields['det.remise_percent']['checked'])) print '<td class="liste_titre" align="left"></td>';
	if (!empty($arrayfields['det.fk_product']['checked'])) print '<td class="liste_titre" align="left"></td>';


	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields);
	$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '<td class="liste_titre" align="left">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print '</tr>';

	print '<tr class="liste_titre">';
	if (!empty($search_sale)) {
		print_liste_field_titre('EasyComSocAndCommercial', $_SERVER["PHP_SELF"], "fa.fk_soc", "", $param, "", $sortfield, $sortorder);
		if (!empty($arrayfields['fa.ref']['checked'])) print_liste_field_titre($arrayfields['fa.ref']['label'], $_SERVER["PHP_SELF"], "fa.ref", "", $param, "", $sortfield, $sortorder);
		if (!empty($arrayfields['det.fk_product']['checked'])) print_liste_field_titre($arrayfields['det.fk_product']['label'], $_SERVER["PHP_SELF"], "det.fk_product", "", $param, "", $sortfield, $sortorder);
		if (!empty($arrayfields['det.total_ht']['checked'])) print_liste_field_titre($arrayfields['det.total_ht']['label'], $_SERVER["PHP_SELF"], "det.total_ht", "", $param, "align='right'", $sortfield, $sortorder);
		if (!empty($arrayfields['det.remise_percent']['checked'])) print_liste_field_titre($arrayfields['det.remise_percent']['label'], $_SERVER["PHP_SELF"], "det.remise_percent", "", $param, "align='right'", $sortfield, $sortorder);
		print_liste_field_titre('EasyCommissionTitle', '', '', '', '', "align='right'");
	} else {
		print_liste_field_titre('EasyCommercial', $_SERVER["PHP_SELF"], "fa.fk_soc", "", $param, "", $sortfield, $sortorder);
		if (!empty($arrayfields['fa.ref']['checked'])) print_liste_field_titre('', $_SERVER["PHP_SELF"], "", "", '', "", '', '');
		if (!empty($arrayfields['det.fk_product']['checked'])) print_liste_field_titre('', $_SERVER["PHP_SELF"], "", "", "", "", "", "");
		if (!empty($arrayfields['det.remise_percent']['checked'])) print_liste_field_titre("", $_SERVER["PHP_SELF"], "", "", "", "align='right'", "", "");
		if (!empty($arrayfields['det.total_ht']['checked'])) print_liste_field_titre($arrayfields['det.total_ht']['label'], $_SERVER["PHP_SELF"], "det.total_ht", "", $param, "align='right'", $sortfield, $sortorder);
		print_liste_field_titre('EasyCommissionTitle', '', '', '', '', "align='right'");
	}


	// Hook fields
	$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
	$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', 'align="center"', $sortfield, $sortorder, 'maxwidthsearch ');
	print "</tr>\n";

	$i = 0;
	$totalarray = array();

	if (empty($search_sale)) {
		// Affichage des lignes de totaux HT et Commissions par utilisateur
		EasyCommissionTools::displayTotauxByCommercial($TUsersTotaux, $i, $arrayfields, $totalarray);
	} else {
		// Affichage du détail des lignes de facture pour le commercial sélectionné
		while ($i < min($num, $limit)) {
			$obj = $db->fetch_object($resql);

			$TRes = EasyCommissionTools::calcul_com($obj, $TCom, $TUserCom);

			print '<tr class="oddeven">';

			// Societe
			$socstatic->fetch($obj->srowid);
			$user->fetch($obj->fk_user);

			print '<td class="tdoverflowmax200">';
			print $socstatic->getNomUrl(1, '', '', 1, '');
			print '</br>';
			print $user->getNomUrl(1, '', '', 1);
			print "</td>\n";
			if (!$i) $totalarray['nbfield']++;


			// Fac REF
			if (!empty($arrayfields['fa.ref']['checked'])) {
				$facturestatic->id = $obj->facrowid;
				$facturestatic->ref = $obj->facref;
				$facturestatic->ref_client = $obj->ref_client;
				$facturestatic->total_ht = $obj->total_ht;
				$facturestatic->total_tva = $obj->total_vat;
				$facturestatic->total_ttc = $obj->total_ttc;

				print '<td class="tdoverflowmax200">';
				print $facturestatic->getNomUrl(1);
				print "</td>\n";
				if (!$i) $totalarray['nbfield']++;
			}

			// Facdet product
			if (!empty($arrayfields['det.fk_product']['checked'])) {
				$productstatic->id = $obj->detproduct;
				$productstatic->ref = $obj->productref;
				$productstatic->label = $obj->productlabel;
				$productstatic->status = $obj->productsell;
				$productstatic->status_buy = $obj->productbuy;

				print '<td class="tdoverflowmax200">';
				print $productstatic->getNomUrl(1);
				print "</td>\n";
				if (!$i) $totalarray['nbfield']++;
			}

			// Facdet total HT
			if (!empty($arrayfields['det.total_ht']['checked'])) {
				print '<td class="tdoverflowmax200" align="right">';
				print round($obj->total_ht, 2);
				print "</td>\n";
				if (!$i) $totalarray['nbfield']++;
				if (!$i) $totalarray['pos'][$totalarray['nbfield']] = 'det.total_ht';
				$totalarray['val']['det.total_ht'] += $obj->total_ht;
			}

			// Facdet remise
			if (!empty($arrayfields['det.remise_percent']['checked'])) {
				print '<td class="tdoverflowmax200" align="right">';
				print $obj->remise_percent . '%';
				print "</td>\n";
				if (!$i) $totalarray['nbfield']++;
			}

			// Facdet Commercial Commission
			print '<td class="tdoverflowmax200" align="right">';
			if (!$TRes['missingInfo']) print price(round($TRes['commission'], 2));
			else print $TRes['missingInfo'];

			print "</td>\n";
			if (!$i) $totalarray['nbfield']++;
			if (!$i) $totalarray['pos'][$totalarray['nbfield']] = 'Commission';
			$totalarray['val']['Commission'] += round($TRes['commission'], 2);

			// Fields from hook
			$parameters = ['arrayfields' => $arrayfields, 'obj' => $obj];
			$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters);    // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;

			// Action
			print '<td class="nowrap" align="center">';
			if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
			{
				$selected = 0;
				if (in_array($obj->rowid, $arrayofselected)) $selected = 1;
				print '<a href="' . $_SERVER["PHP_SELF"] . '?action=updateRate&amp;id_rate=' . $obj->rowid . '" class="like-link " style="margin-right:15px;important">' . img_picto('edit', 'edit') . '</a>';
				print '<a href="' . $_SERVER["PHP_SELF"] . '?action=deleteRate&amp;id_rate=' . $obj->rowid . '" class="like-link" style="margin-right:45px;important">' . img_picto('delete', 'delete') . '</a>';
				print '<input id="cb' . $obj->rowid . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $obj->rowid . '"' . ($selected ? ' checked="checked"' : '') . '>';
			}
			print '</td>';

			if (!$i) $totalarray['nbfield']++;

			print "</tr>\n";
			$i++;
		}
	}


	// Show total line
	include DOL_DOCUMENT_ROOT . '/core/tpl/list_print_total.tpl.php';

	$db->free($resql);

	print "</table>";
	print "</div>";

	print '</form>';
} else {
	dol_print_error($db);
}


llxFooter();
$db->close();
