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

	public function calcul_com($facdet, $TCom, $TUserCom) {
		global $langs;
		$TResult = array();

		if (empty($TUserCom)) {
			foreach ($TCom as $com => $globalCom) {
				if ($facdet->remise_percent >= $globalCom->discountPercentageFrom
					&& $facdet->remise_percent <= $globalCom->discountPercentageTo) {
					$TResult['commission'] = (($globalCom->commissionPercentage * $facdet->total_ht)/100);
					break;
				}
				else $TResult['missingInfo'] = $langs->trans('Matrice globale incomplète');
			}
		}
		else {
			foreach ($TUserCom as $commercial => $userCom) {
				if ($facdet->fk_user == $commercial) {
					if ($facdet->remise_percent >= $userCom[$commercial]->discountPercentageFrom
						&& $facdet->remise_percent <= $userCom[$commercial]->discountPercentageTo) {
						$TResult['commission'] = (($userCom[$commercial]->commissionPercentage * $facdet->total_ht) / 100);
					} else $TResult['missingInfo'] = $langs->trans('Matrice utilisateur incomplète');
				}
				else {
					foreach ($TCom as $globalCom) {
						if ($facdet->remise_percent >= $globalCom->discountPercentageFrom
							&& $facdet->remise_percent <= $globalCom->discountPercentageTo) {
							$TResult['commission'] = (($globalCom->commissionPercentage * $facdet->total_ht)/100);
							break;
						}
						else $TResult['missingInfo'] = $langs->trans('Matrice globale incomplète');
					}
				}
			}
		}

		return $TResult;
	}

}
