<?php
/* Copyright (C) 2005-2008  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2015  Regis Houssin           <regis.houssin@inodbox.com>
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
 * or see https://www.gnu.org/
 */

/**
 *  \file       htdocs/core/modules/don/mod_don_terre.php
 *  \ingroup    don
 *  \brief      File containing class for numbering module Terre
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/dons/modules_don.php';

/**
 *  \class      mod_don_terre
 *  \brief      Class of numbering module Terre for dons
 */
class mod_don_terre extends ModeleNumRefDons
{
	/**
	 * Dolibarr version of the loaded document 'development', 'experimental', 'dolibarr'
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Prefix for dons
	 * @var string
	 */
	public $prefixdon = 'DON';

	/**
	 * Prefix for replacement dons
	 * @var string
	 */
	public $prefixreplacement = 'FA';

	/**
	 * Prefix for credit note
	 * @var string
	 */
	public $prefixcreditnote = 'AV';

	/**
	 * Prefix for deposit
	 * @var string
	 */
	public $prefixdeposit = 'AC';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';


	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $conf, $mysoc;

	}

	/**
	 *  Returns the description of the numbering model
	 *
	 *  @param	Translate	$langs		Object langs
	 *  @return     string      Texte descripif
	 */
	public function info($langs)
	{
		$langs->load("bills");
		return $langs->trans('TerreNumRefModelDesc1', $this->prefixdon, $this->prefixcreditnote, $this->prefixdeposit);
	}

	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return $this->prefixdon."0501-0001";
	}

	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *  @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated($object)
	{
		global $langs, $conf, $db;

		$langs->load("bills");

		// Check don num
		$fayymm = '';
		$max = '';

		$posindice = strlen($this->prefixdon) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max"; // This is standard SQL
		$sql .= " FROM ".MAIN_DB_PREFIX."don";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefixdon)."____-%'";
		$sql .= " AND entity = ".$conf->entity;

		$resql = $db->query($sql);
		if ($resql) {
			$row = $db->fetch_row($resql);
			if ($row) {
				$fayymm = substr($row[0], 0, 6);
				$max = $row[0];
			}
		}
		if ($fayymm && !preg_match('/'.$this->prefixdon.'[0-9][0-9][0-9][0-9]/i', $fayymm)) {
			$langs->load("errors");
			$this->error = $langs->trans('ErrorNumRefModel', $max);
			return false;
		}

		return true;
	}

	/**
	 * Return next value not used or last value used.
	 * Note to increase perf of this numbering engine, you can create a calculated column and modify request to use this field instead for select:
	 * ALTER TABLE llx_don ADD COLUMN calculated_numrefonly INTEGER AS (CASE SUBSTRING(ref FROM 1 FOR 2) WHEN 'FA' THEN CAST(SUBSTRING(ref FROM 10) AS SIGNED) ELSE 0 END) PERSISTENT;
	 * ALTER TABLE llx_don ADD INDEX calculated_numrefonly_idx (calculated_numrefonly);
	 *
	 * @param   Societe		$objsoc		Object third party
	 * @param   Don		$don	Object don
	 * @param   string		$mode       'next' for next value or 'last' for last value
	 * @return  string       			Next ref value or last ref if $mode is 'last', <= 0 if KO
	 */
	public function getNextValue($objsoc, $don, $mode = 'next')
	{
		global $db;

		dol_syslog(get_class($this)."::getNextValue mode=".$mode, LOG_DEBUG);

		$prefix = $this->prefixdon;

		// First we get the max value
		$posindice = strlen($prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max"; // This is standard SQL
		$sql .= " FROM ".MAIN_DB_PREFIX."don";
		$sql .= " WHERE ref LIKE '".$db->escape($prefix)."____-%'";
		$sql .= " AND entity IN (".getEntity('donnumber', 1, $don).")";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max);
			} else {
				$max = 0;
			}
		} else {
			return -1;
		}

		if ($mode == 'last') {
			if ($max >= (pow(10, 4) - 1)) {
				$num = $max; // If counter > 9999, we do not format on 4 chars, we take number as it is
			} else {
				$num = sprintf("%04s", $max);
			}

			$ref = '';
			$sql = "SELECT ref as ref";
			$sql .= " FROM ".MAIN_DB_PREFIX."don";
			$sql .= " WHERE ref LIKE '".$db->escape($prefix)."____-".$num."'";
			$sql .= " AND entity IN (".getEntity('donnumber', 1, $don).")";
			$sql .= " ORDER BY ref DESC";

			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				if ($obj) {
					$ref = $obj->ref;
				}
			} else {
				dol_print_error($db);
			}

			return $ref;
		} elseif ($mode == 'next') {
			$date = $don->date; // This is don date (not creation date)
			$yymm = strftime("%y%m", $date);

			if ($max >= (pow(10, 4) - 1)) {
				$num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
			} else {
				$num = sprintf("%04s", $max + 1);
			}

			dol_syslog(get_class($this)."::getNextValue return ".$prefix.$yymm."-".$num);
			return $prefix.$yymm."-".$num;
		} else {
			dol_print_error('', 'Bad parameter for getNextValue');
		}

		return 0;
	}

	/**
	 *  Return next free value
	 *
	 *  @param  Societe     $objsoc         Object third party
	 *  @param  string      $objforref      Object for number to search
	 *  @param   string     $mode           'next' for next value or 'last' for last value
	 *  @return  string                     Next free value
	 */
	public function getNumRef($objsoc, $objforref, $mode = 'next')
	{
		return $this->getNextValue($objsoc, $objforref, $mode);
	}
}
