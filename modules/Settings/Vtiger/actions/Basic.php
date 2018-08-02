<?php
/* +**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * ********************************************************************************** */

class Settings_Vtiger_Basic_Action extends \App\Controller\Action
{
	use \App\Controller\ExposeMethod;

	public function __construct()
	{
		parent::__construct();
		$this->exposeMethod('updateFieldPinnedStatus');
	}

	/**
	 * Checking permissions.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\NoPermittedForAdmin
	 */
	public function checkPermission(\App\Request $request)
	{
		if (!\App\User::getCurrentUserModel()->isAdmin()) {
			throw new \App\Exceptions\NoPermittedForAdmin('LBL_PERMISSION_DENIED');
		}
	}

	public function updateFieldPinnedStatus(\App\Request $request)
	{
		$fieldId = $request->get('fieldid');
		$menuItemModel = Settings_Vtiger_MenuItem_Model::getInstanceById($fieldId);

		$pin = $request->get('pin');
		if ($pin == 'true') {
			$menuItemModel->markPinned();
		} else {
			$menuItemModel->unMarkPinned();
		}

		$response = new Vtiger_Response();
		$response->setResult(['SUCCESS' => 'OK']);
		$response->emit();
	}
}
