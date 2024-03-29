<?php
/* Copyright (C) 2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
/**
 * \file        class/EasyCommissionTools.class.php
 * \ingroup     easycommission
 * \brief       This file is a tools class file for EasyCommission
 */
class EasyCommissionTools
{

    /**
	 * @var DoliDB
	 */
    public $db;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Retrieve all existing commissions on DB
	 *
	 * @return array $TResult
	 */
	public static function getAllCommissions() {
		global $db;
		$sql = '';
		$TResult = array();

		$sql = "SELECT rowid, entity, discountPercentageFrom, discountPercentageTo, commissionPercentage, fk_user ";
		$sql.= " FROM ".MAIN_DB_PREFIX."easycommission_matrix ";
		$resql = $db->query($sql);
		if ($resql)
		{
			while ($obj = $db->fetch_object($resql)) {
				$TResult[] = $obj;
			}
		}

		return $TResult;
	}

    /**
     * @param $TDatas
     * @return array[]
     */
	public static function split_com($TDatas) {

		$TCom = $TUserCom = array();
		foreach ($TDatas as $data) {
			if (! empty ($data->fk_user)) {
				$fk_user = $data->fk_user;
				$TUserCom[$fk_user][] = $data;
			}
			else {
				$TCom[] = $data;
			}
		}

		return array($TCom, $TUserCom);
	}

    /**
     * @param       $facdet
     * @param array $TCom     Module conf commissions
     * @param array $TUserCom User overload commissions
     * @param bool  $returnVal
     * @return array|float|int
     */
	public static function calcul_com($facdet, $TCom, $TUserCom, $returnVal = false) {
		global $langs;
		$TResult = array();

		if (empty($TUserCom)) {
			foreach ($TCom as $globalCom) {
				if ($facdet->remise_percent >= $globalCom->discountPercentageFrom
					&& $facdet->remise_percent <= $globalCom->discountPercentageTo) {
					$TResult['commission'] = (($globalCom->commissionPercentage * $facdet->total_ht)/100);
                    unset($TResult['missingInfo']);
                    break;
				}
				else $TResult['missingInfo'] = $langs->trans('Matrice globale incomplète');
			}
		}
		else {
            foreach ($TUserCom as $commercial => $TOneUserCom) {
                foreach($TOneUserCom as $oneUser => $TUserLines) {
                    if($facdet->fk_user == $commercial) {
                        if($facdet->remise_percent >= $TUserLines->discountPercentageFrom && $facdet->remise_percent <= $TUserLines->discountPercentageTo) {
                            $TResult['commission'] = (($TUserLines->commissionPercentage * $facdet->total_ht) / 100);
                            unset($TResult['missingInfo']);
                            break;
                        }
                        else $TResult['missingInfo'] = $langs->trans('Matrice utilisateur incomplète');
                    }
                    else {
                        foreach($TCom as $globalCom) {
                            if($facdet->remise_percent >= $globalCom->discountPercentageFrom && $facdet->remise_percent <= $globalCom->discountPercentageTo) {
                                $TResult['commission'] = (($globalCom->commissionPercentage * $facdet->total_ht) / 100);
                                $TResult['totalCom']+= $TResult['commission'];
                                unset($TResult['missingInfo']);
                                break;
                            }
                            else $TResult['missingInfo'] = $langs->trans('Matrice globale incomplète');
                        }
                    }
                }
            }
		}

		if ($returnVal) return ($TResult['commission']);
		return $TResult;
	}

    /**
     * @param $TUsersTotaux
     * @param $i
     * @param $arrayfields
     * @param $totalarray
     */
	public static function displayTotauxByCommercial($TUsersTotaux, $i, $arrayfields, &$totalarray) {
	    global $db;

	    foreach($TUsersTotaux as $fk_user => $userTotaux) {

	        print '<tr class="oddeven">';

            // Commercial
            $user = new User($db);
            $user->fetch($fk_user);

            print '<td class="tdoverflowmax200">';
            print '</br>';
            print $user->getNomUrl(1, '', '', 1);
            print "</td>\n";
            if (! $i) $totalarray['nbfield']++;

            // Fac REF
            if(! empty($arrayfields['fa.ref']['checked'])) {
                print '<td class="tdoverflowmax200">';
                print "</td>\n";
                if(! $i) $totalarray['nbfield']++;
            }

            // Facdet product
            if(! empty($arrayfields['det.fk_product']['checked'])) {
                print '<td class="tdoverflowmax200">';
                print "</td>\n";
                if(! $i) $totalarray['nbfield']++;
            }

            // Facdet remise
            if(! empty($arrayfields['det.remise_percent']['checked'])) {
                print '<td class="tdoverflowmax200" align="right">';
                print "</td>\n";
                if(! $i) $totalarray['nbfield']++;
            }

            // Facdet total HT
            if (! empty($arrayfields['det.total_ht']['checked']))
            {
                print '<td class="tdoverflowmax200" align="right">';
                print price($userTotaux['total_ht']);
                print "</td>\n";
                if (! $i) $totalarray['nbfield']++;
                if (! $i) $totalarray['pos'][$totalarray['nbfield']] = 'det.total_ht';
                $totalarray['val']['det.total_ht'] += $userTotaux['total_ht'];
            }

            // Facdet Commercial Commission
            print '<td class="tdoverflowmax200" align="right">';
            print price(round($userTotaux['commission'], 2));
            print "</td>\n";
            if (! $i) $totalarray['nbfield']++;
            if (! $i) $totalarray['pos'][$totalarray['nbfield']] = 'Commission';
            $totalarray['val']['Commission'] += round($userTotaux['commission'], 2);

            // Action
            print '<td class="nowrap" align="center">';
            print '</td>';

            if(! $i) $totalarray['nbfield']++;
            print "</tr>\n";
            $i++;
        }

    }

}
