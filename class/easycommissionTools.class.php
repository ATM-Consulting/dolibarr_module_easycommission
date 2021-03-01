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
 * \file        class/EasyCommission.class.php
 * \ingroup     easycommission
 * \brief       This file is a CRUD class file for EasyCommission (Create/Read/Update/Delete)
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
	public function getAllCommissions() {
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

	public function split_com($TDatas) {

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
     * @param FactureLigne $facdet
     * @param array $TCom Module conf commissions
     * @param array $TUserCom User overload commissions
     * @return array
     */
	public function calcul_com($facdet, $TCom, $TUserCom) {
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
                                unset($TResult['missingInfo']);
                                break;
                            }
                            else $TResult['missingInfo'] = $langs->trans('Matrice globale incomplète');
                        }
                        exit;
                    }
                }
            }
		}

		return $TResult;
	}


	public function getUserTotaux() {
	    global $conf;
	    $sql = '';

	    $sql = "SELECT u.rowid user_rowid, ugu.fk_user, ug.nom groupe, SUM(det.total_ht)";

	    $sql .= " FROM ".MAIN_DB_PREFIX."facture fa ";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."facturedet det on fa.rowid = det.fk_facture";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid = det.fk_product";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe s on s.rowid = fa.fk_soc";
        $sql .=" LEFT JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = pr.rowid";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux sc ON sc.fk_soc = s.rowid";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = sc.fk_user";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup_user ugu ON ugu.fk_user = u.rowid";
        $sql .=" INNER JOIN ".MAIN_DB_PREFIX."usergroup ug ON ug.rowid = ugu.fk_usergroup";

        $sql .= " WHERE ug.rowid = ".$conf->global->EASYCOMMISSION_USER_GROUP;

        $sql .= " AND det.fk_product NOT IN (";
        $sql .= " SELECT cp.fk_product ";
        $sql .= " FROM ".MAIN_DB_PREFIX."categorie_product cp";
        $sql .= " WHERE cp.fk_categorie = ".$conf->global->EASYCOMMISSION_EXCLUDE_CATEGORY;
        $sql .= ")";

        return $sql;
	}

}
