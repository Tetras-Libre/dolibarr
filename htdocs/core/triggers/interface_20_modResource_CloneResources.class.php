<?php
require_once DOL_DOCUMENT_ROOT.'/resource/class/dolresource.class.php';
/* Copyright (C) ---Put here your own copyright and developer email---
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_20_modResource_CloneResources.class.php
 * \ingroup mymodule
 * \brief   Example trigger.
 *
 *
 *
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for MyModule module
 */
class InterfaceCloneResources extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "demo";
		$this->description = "A trigger called when cloning an ActionComm to clone the resources linked to it.";
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'resource';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf) : int
	{
		// does it meet the conditions ?
		if (!isModEnabled('resource') || $action !== 'ACTION_CLONE'){
			return 0;
		}

		// static object
		$dolresource = new Dolresource($this->db);

		// get the linked resources (element_resources)
		// element will always be action since this is triggered from the actioncomm class
		$linked_resources = $dolresource->getElementResources("action", $GLOBALS['id']);

		// copy every resource
		foreach ($linked_resources as $linked_resource) {
			// clone this resource & check for errors
			if (!$object->add_element_resource($linked_resource['resource_id'], $linked_resource['resource_type'], $linked_resource['busy'], $linked_resource['mandatory'])) {
				return -1;
			}
		}
		return 0;
	}
}
