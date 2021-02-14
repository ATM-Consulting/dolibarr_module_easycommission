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
class easyCommission extends CommonObject
{
    /**
	 * @var string ID to identify managed object.
	 */
	public $element = 'matrix';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element = 'easycommission_matrix';

	/**
     * @var int ID of user that overload the matrix
     */
    public $fk_user = NULL;

    /**
	 * @var int Pourcentage de remise palier 1
	 */
	public $discountPercentageFrom = 0;

	/**
	 * @var int Pourcentage de remise palier 2
	 */
	public $discountPercentageTo = 0;

    /**
     * @var string int Pourcentage de commission
     */
    public $commissionPercentage = 0;


    // BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid' => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>1, 'visible'=>-1, 'position'=>1, 'notnull'=>1, 'index'=>1, 'comment'=>"Id",),
        'entity' =>array('type'=>'integer', 'label'=>'Entity', 'default'=>1, 'enabled'=>1, 'visible'=>-2, 'notnull'=>1, 'position'=>20, 'index'=>1),
        'tms' =>array('type'=>'timestamp', 'label'=>'DateModification', 'enabled'=>1, 'visible'=>-1, 'notnull'=>1, 'position'=>25),
        'fk_user' => array('type'=>'integer', 'label'=>'User', 'visible'=>-1, 'enabled'=>1, 'position'=>35, 'notnull'=>0, 'index'=>1,),
        'discountPercentageFrom' => array('type'=>'real', 'label'=>'discountPercentageFrom', 'enabled'=>1, 'visible'=>1, 'default'=>1, 'position'=>55, 'notnull'=>1, 'css'=>'maxwidth75imp'),
        'discountPercentageTo' => array('type'=>'real', 'label'=>'discountPercentageTo', 'enabled'=>1, 'visible'=>1, 'default'=>1, 'position'=>65, 'notnull'=>1, 'css'=>'maxwidth75imp'),
	    'commissionPercentage' => array('type'=>'real', 'label'=>'commissionPercentage', 'enabled'=>1, 'visible'=>1, 'default'=>1, 'position'=>75, 'notnull'=>1, 'css'=>'maxwidth75imp'),
    );

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;

	}

	public function duplicateConfComm($fk_user) {
	    global $db, $conf;

	    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (entity,discountPercentageFrom, discountPercentageTo, commissionPercentage, fk_user)';
	    $sql .= ' SELECT '.$conf->entity.',discountPercentageFrom, discountPercentageTo, commissionPercentage,'.$fk_user.' FROM '.MAIN_DB_PREFIX.$this->table_element;
	    $sql .= ' WHERE fk_user IS NULL';

        $res = $db->query($sql);
    }

    public function fetchByArray($limit = 0, $TFilter = array(), $loadChild = true, $justFetchIfOnlyOneResult = true) {
        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
        $sql.= ' WHERE 1 = 1';

        foreach($TFilter as $key => $field) {
            $sql.= ' AND '.$this->db->escape($key).' = '.$this->quote($field, $this->fields[$key]);
        }
        if(! empty($limit)) $sql.= ' LIMIT '.$this->db->escape($limit);

        $resql = $this->db->query($sql);
        if(! $resql) {
            $this->error = $this->db->lasterror();
		    $this->errors[] = $this->error;
            return -1;
        }

        $nbRow = $this->db->num_rows($resql);

        $TRes = array();
        while($obj = $this->db->fetch_object($resql)) {
            if($justFetchIfOnlyOneResult) {
                return $this->fetch($obj->rowid, $loadChild);
            }

            $o = new static($this->db);
            $o->fetch($obj->rowid, $loadChild);
            $TRes[] = $o;
        }

        if($justFetchIfOnlyOneResult) return 0;
        return $TRes;
    }

    /**
     * @param string $mode
     * @return string Html of Commission Matrix
     */
    public function displayCommissionMatrix($fk_user = 0){

        global $db, $langs;

        $out = '';

        $out = '<div class="easycommissionmatrixdiv">';
        $out.= '<table class="noborder centpercent">';
        $out.= '<tr class="liste-titre">';
        $out.= '<th align="left" colspan="4" class="wrapcolumntitle liste_titre">'.$langs->trans('easyCommissionDiscountPercentage').'</th>';
        $out.= '<th align="left" colspan="2" class="wrapcolumntitle liste_titre">'.$langs->trans('easyCommissionCommissionPercentage').'</th>';
        $out.= '<tr class="liste-titre">';
        $out.= '<td class="maxwidth100 tddict">'.$langs->trans('easyCommissionFrom').'</td>';
        $out.= '<td class="maxwidth100 tddict"></td>';
        $out.= '<td class="maxwidth100 tddict">'.$langs->trans('easyCommissionTo').'</td>';
        $out.= '<td class="maxwidth100 tddict"></td>';
        $out.= '<td class="maxwidth100 tddict"></td>';
        $out.= '<td class="maxwidth100 tddict"></td>';
        $out.= '</tr>';

        $sql = 'SELECT * FROM ' .MAIN_DB_PREFIX.'easycommission_matrix';
        if($fk_user > 0) {
            $sql.= ' WHERE fk_user ='.$fk_user;
        }
        else {
            $sql.= ' WHERE fk_user IS NULL';
        }

        $res = $db->query($sql);
        if($res > 0){
            while($obj = $db->fetch_object($res)){
                $out.= $this->dynamicMacthName($obj);
            }
        }

        $out.= '</table>';
        $out.= '<a>';
        $out.= '<span class="fa fa-plus-circle easycommissionaddbtn paddingleft" style="cursor: pointer;" title="'.$langs->trans('easyCommissionAddLine').'"> '.$langs->trans('easyCommissionAddLine').'</span>';
        $out.= '</a>';
        $out.= '</div>';
        $out.= '</div>';

        return $out;
    }

    /**
     * @param $rowTable
     * @return string  Dynamic html of Commission Matrix
     */
    public function dynamicMacthName($rowTable){

        global $langs;
        $out = '';

        $out.= '<tr class="oddeven easycommissionValues" data-id='.$rowTable->rowid.'>';
        $out.= '<td class="maxwidth100 tddict valueInputFrom"><input style="width:100%" type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.$rowTable->rowid.'][discountPercentageFrom]'.'" value="'.$rowTable->discountPercentageFrom.'"></td>';
        $out.= '<td class="maxwidth100 tddict" style="width: 20px">%</td>';
        $out.= '<td class="maxwidth100 tddict valueInputTo"><input style="width:100%"  type="number" min="0" max="100" step="0.1" required name="TCommissionnement['.$rowTable->rowid.'][discountPercentageTo]'.'" value="'.$rowTable->discountPercentageTo.'"></td>';
        $out.= '<td class="maxwidth100 tddict" style="width: 20px">%</td>';
        $out.= '<td class="maxwidth100 tddict valueCommission"><input style="width:100%"  type="number" min="0" max="100" required step="0.1" name="TCommissionnement['.$rowTable->rowid.'][commissionPercentage]'.'" value="'.$rowTable->commissionPercentage.'">';
        $out.= '<td class="maxwidth100 tddict" style="width: 60px">%';
        $out.= '<span class="fas fa-trash pictodelete easycommissionrmvbtn pull-right" style="cursor: pointer;" title="'.$langs->trans('easyCommissionRemoveLine').'"></span>';
        $out.= '</td>';
        $out.= '</tr>';

        return $out;
    }


	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id   Id object
	 * @param string $ref  Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && !empty($this->table_element_line)) $this->fetchLines();
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		$result = $this->fetchLinesCommon();
		return $result;
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 *  Delete a line of object in database
	 *
	 *	@param  User	$user       User that delete
	 *  @param	int		$idline		Id of line to delete
	 *  @param 	bool 	$notrigger  false=launch triggers after, true=disable triggers
	 *  @return int         		>0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $idline, $notrigger = false)
	{
		if ($this->status < 0)
		{
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}

}
