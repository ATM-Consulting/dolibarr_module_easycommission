<?php
/* Copyright (C) 2018 John BOTELLA
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER'))
	define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB'))
	define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC'))
	define('NOREQUIRESOC', '1');
//if (!defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))
	define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN'))
	define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU'))
	define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))
	define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))
	define('NOREQUIREAJAX', '1');


/**
 * \file    js/linesfromproductmatrix.js.php
 * \ingroup linesfromproductmatrix
 * \brief   JavaScript file for module linesfromproductmatrix.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"]))
	$res = @include($_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];$tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php"))
	$res = @include(substr($tmp, 0, ($i + 1)) . "/main.inc.php");
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/../main.inc.php"))
	$res = @include(substr($tmp, 0, ($i + 1)) . "/../main.inc.php");
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php"))
	$res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php"))
	$res = @include("../../../main.inc.php");
if (!$res)
	die("Include of main fails");

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache))
	header('Cache-Control: max-age=3600, public, must-revalidate');
else header('Cache-Control: no-cache');


// Load traductions files requiredby by page
$langs->loadLangs(array("easycommission@easycommission", "other"));
?>



/* Javascript library of module easycommission */
$(document).ready(function () {

	/**
	 * Ajout d'une ligne dans la matrice de commissionnement
	 */
	$(document).on("click", ".easycommissionaddbtn", function () {

		var currentBloc = $(this).parent().parent();
		var currentTable = currentBloc.find('table.noborder.centpercent');
		var lastLineId = currentTable.find('tr:last-child').attr('data-id');
		var maxLines = $('tr.easycommissionValues').length;

		$.ajax({
			url: "<?php print dol_buildpath('easycommission/scripts/interface.php', 1)?>",
			method: "POST",
			dataType: "json",  // format de réponse attendu
			data: {
				maxLines: maxLines,
				lastTrDataId: lastLineId,
				action: 'addLineToMatrix'
			},
			success: function (data) {
				if (!data.error) {
					currentTable.append(data.newMatrixLine);
				} else {
					setCommissionMessage(data.error, "error");
				}
			}
		})
	});

	/**
	 * Suppression d'une ligne de la matrice de commissionnement
	 */
	$(document).on("click", ".easycommissionrmvbtn", function () {

		var currentLine = $(this).parent().parent();
		var currentLineId = $(this).parent().parent().attr('data-id');

		$.ajax({
			url: "<?php print dol_buildpath('easycommission/scripts/interface.php', 1)?>",
			method: "POST",
			dataType: "json",  // format de réponse attendu
			data: {
				currentIdLine: currentLineId,
				action: 'removeLineToMatrix'
			},
			success: function (data) {
				if (!data.error) {
					currentLine.remove();
					setCommissionMessage(data.message);
				} else {
					setCommissionMessage(data.error, "error");
				}
			}
		})
	});

	/**
	 * Diverses vérifications de saisie
	 */
	$(document).on("change", "input[type=number]", function () {

		var currentValuesLine = $(this).parent().closest('tr');
		var currentValueFrom = currentValuesLine.children('td.valueInputFrom').children('input').val();
		var currentValueTo = currentValuesLine.children('td.valueInputTo').children('input').val();
		var currentValueToDiv = currentValuesLine.children('td.valueInputTo').children('input');
		var currentCommission = currentValuesLine.children('td.valueCommission').children('input').val();
		var currentCommissionDiv = currentValuesLine.children('td.valueCommission').children('input');

		if ((currentValueTo) && (currentValueFrom > currentValueTo)) {
			currentValueToDiv.css("borderColor", "red");
		}
		if ((currentCommission) && (currentCommission > 100) || (currentCommission < 0)) {
			currentCommissionDiv.css("borderColor", "red");
		}
	});

	/**
	 * Check on form submit
	 */
	$(document).on("submit", "form.easycommissionForm", function (e) {

		var allInputsLine = $('.easycommissionValues');

		allInputsLine.each(function (index, input) {
			valInputTo = input.firstChild.nextSibling.nextSibling.firstChild.value;
			valInputFrom = input.firstChild.firstChild.value;
			valueCommission = input.lastElementChild.previousSibling.firstChild.value;

			if (valInputTo < valInputFrom) {
				e.preventDefault();
				setCommissionMessage("La valeur 'à' est inférieure à la valeur 'De'", "error")
			}
			if ((!valInputFrom) || (!valInputTo) || (!valueCommission)) {
				e.preventDefault();
				setCommissionMessage("Une des valeurs est vide. Veuillez renseigner toutes les valeurs", "error")
			}
			if ((valInputFrom < 0) || (valInputTo < 0) || (valueCommission < 0)) {
				e.preventDefault();
				setCommissionMessage("Aucune valeur ne peut être inférieure à zéro", "error")
			}
			if ((valInputFrom > 100) || (valInputTo > 100) || (valueCommission > 100)) {
				e.preventDefault();
				setCommissionMessage("Aucune valeur ne peut être supérieure à 100", "error")
			}
		})
	})

	function setCommissionMessage($out, $type = "success") {
		$.jnotify($out, $type, 3000);
	}

});
