<?php
/* Copyright (C) 2012 Regis Houssin       <regis.houssin@inodbox.com>
 * Copyright (C) 2020 Laurent Destailleur <eldy@users.sourceforge.net>
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

/**
 *       \file       htdocs/core/ajax/contacts.php
 *       \brief      File to load contacts combobox
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
global $langs;

$id = GETPOSTINT('id'); // id of thirdparty
$action = GETPOST('action', 'aZ09');
$htmlname = GETPOST('htmlname', 'alpha');
$showempty = GETPOSTINT('showempty');

// Security check
$result = restrictedArea($user, 'societe', $id, '&societe', '', 'fk_soc', 'rowid', 0);


/*
 * View
 */

top_httphead();

//print '<!-- Ajax page called with url '.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?'.dol_escape_htmltag($_SERVER["QUERY_STRING"]).' -->'."\n";

// Load original field value
if (!empty($id) && !empty($action) && !empty($htmlname)) {
	$form = new Form($db);

	$return = array();
	if (empty($showempty)) {
		$showempty = 0;
	}

	$return['value']	= $form->selectcontacts($id, '', $htmlname, $showempty, '', '', 0, '', true);
	$return['num'] = $form->num;
	$return['error']	= $form->error;

	//if the medical center module is enabled
	$correspondants = [];
	if (isModEnabled('cabinetmed')){
		$societe = new Societe($db);
		$societe->fetch($id);

		// get the list of contacts
		$correspondants = $societe->liste_contact();
	}

	// can only happen if the 'cabinetmed' module is enabled
	// would cause too much indentation to have it in the previous if statement
	if (!empty($correspondants)){
		// the inital value is the "empty" option if $form->selectcontacts didn't find anyone
		$initialvalue = $form->num === 0 ? '<option value="0">&nbsp;</option>' : "";

		// a static contact, used for functions like "getFullName"
		$contactstatic = new Contact($db);

		// current: current Contact as an array
		$correspHTMLOptions = array_reduce($correspondants, function(string $prev, array $current) use ($langs, $contactstatic): string {
			//give the current values to the static contact
			$contactstatic->lastname = $current['lastname'];
			$contactstatic->firstname = $current['firstname'];

			return $prev . sprintf('<option value="%d">%s</option>', $current['id'], $contactstatic->getFullName($langs));
		}, $initialvalue);

		// add to / override the previous return value
		// it ovverides when $form->selectcontacts didn't find any contact
		$return['value'] = ($form->num === 0 ? "" : $return['value']) . $correspHTMLOptions;
	}

	echo json_encode($return);
}
