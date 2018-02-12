<?php

/**
 * OSSMailView Relation model class
 * @package YetiForce.Model
 * @copyright YetiForce Sp. z o.o.
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class OSSMailView_Relation_Model extends Vtiger_Relation_Model
{

	/**
	 * Add relation
	 * @param int $sourceRecordId
	 * @param int|int[] $destinationRecordId
	 * @param mixed $params
	 * @return boolean
	 */
	public function addRelation($sourceRecordId, $destinationRecordId, $params = false)
	{
		$return = false;
		$dbCommand = \App\Db::getInstance()->createCommand();
		if (!is_array($destinationRecordId)) {
			$destinationRecordId = [$destinationRecordId];
		}
		foreach ($destinationRecordId as $crmId) {
			CRMEntity::trackLinkedInfo($crmId);
			$destinationModuleName = \App\Record::getType($crmId);
			$data = [
				'CRMEntity' => CRMEntity::getInstance($destinationModuleName),
				'sourceModule' => $destinationModuleName,
				'sourceRecordId' => $crmId,
				'destinationModule' => 'OSSMailView',
				'destinationRecordId' => $sourceRecordId
			];
			$eventHandler = new App\EventHandler();
			$eventHandler->setModuleName($destinationModuleName);
			$eventHandler->setParams($data);
			$eventHandler->trigger('EntityBeforeLink');

			$relationExists = (new App\Db\Query())->from('vtiger_ossmailview_relation')->where(['ossmailviewid' => $sourceRecordId, 'crmid' => $crmId])->exists();
			if (!$relationExists) {
				if (!$params) {
					$recordModel = Vtiger_Record_Model::getInstanceById($sourceRecordId, 'OSSMailView');
					$date = $recordModel->get('date');
				} else {
					$date = $params;
				}
				$dbCommand->insert('vtiger_ossmailview_relation', [
					'ossmailviewid' => $sourceRecordId,
					'crmid' => $crmId,
					'date' => $date
				])->execute();

				if ($parentId = Users_Privileges_Model::getParentRecord($crmId)) {
					$relationExists = (new App\Db\Query())->from('vtiger_ossmailview_relation')->where(['ossmailviewid' => $sourceRecordId, 'crmid' => $parentId])->exists();
					if (!$relationExists) {
						$dbCommand->insert('vtiger_ossmailview_relation', [
							'ossmailviewid' => $sourceRecordId,
							'crmid' => $parentId,
							'date' => $date
						])->execute();
						if ($parentId = Users_Privileges_Model::getParentRecord($parentId)) {
							$relationExists = (new App\Db\Query())->from('vtiger_ossmailview_relation')->where(['ossmailviewid' => $sourceRecordId, 'crmid' => $parentId])->exists();
							if (!$relationExists) {
								$dbCommand->insert('vtiger_ossmailview_relation', [
									'ossmailviewid' => $sourceRecordId,
									'crmid' => $parentId,
									'date' => $date
								])->execute();
							}
						}
					}
				}
				$return = true;
			}
		}
		$eventHandler->trigger('EntityAfterLink');
		return $return;
	}

	public function getAttachments()
	{
		$queryGenerator = $this->getQueryGenerator();
		$queryGenerator->addJoin(['LEFT JOIN', 'vtiger_seattachmentsrel', 'vtiger_seattachmentsrel.crmid = vtiger_notes.notesid']);
		$queryGenerator->addJoin(['LEFT JOIN', 'vtiger_attachments', 'vtiger_seattachmentsrel.attachmentsid = vtiger_attachments.attachmentsid']);
		$queryGenerator->addJoin(['LEFT JOIN', 'vtiger_ossmailview_files', 'vtiger_ossmailview_files.documentsid = vtiger_notes.notesid']);
		$queryGenerator->addNativeCondition(['vtiger_ossmailview_files.ossmailviewid' => $this->get('parentRecord')->getId()]);
	}
}
