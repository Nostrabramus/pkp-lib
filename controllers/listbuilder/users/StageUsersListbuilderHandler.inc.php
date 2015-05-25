<?php

/**
 * @file controllers/listbuilder/users/StageUsersListbuilderHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StageUsersListbuilderHandler
 * @ingroup listbuilder
 *
 * @brief Class for adding participants to a stage.
 */

import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');

class StageUsersListbuilderHandler extends ListbuilderHandler {
	/**
	 * Constructor
	 */
	function StageUsersListbuilderHandler() {
		parent::ListbuilderHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetch', 'fetchRow', 'fetchOptions')
		);
	}

	//
	// Getters/Setters
	//
	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}

	//
	// Overridden parent class functions
	//
	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId()
		);
	}

	//
	// Implement protected template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load submission-specific translations
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);

		$this->setTitle('email.recipients');

		import('lib.pkp.classes.linkAction.request.NullAction');
		$this->addAction(
			new LinkAction(
				'addItem',
				new NullAction(),
				__('grid.action.addUser'),
				'add_user'
			)
		);

		// Basic configuration.
		$this->setSourceType(LISTBUILDER_SOURCE_TYPE_SELECT);
		$this->setSaveType(LISTBUILDER_SAVE_TYPE_EXTERNAL);
		$this->setSaveFieldName('users');

		// Name column
		$nameColumn = new ListbuilderGridColumn($this, 'name', 'common.name');
		import('lib.pkp.controllers.listbuilder.users.UserListbuilderGridCellProvider');
		$cellProvider = new UserListbuilderGridCellProvider();
		$nameColumn->setCellProvider($cellProvider);
		$this->addColumn($nameColumn);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Implement methods from ListbuilderHandler
	//
	/**
	 * @copydoc GridHandler::getRowDataElement
	 */
	protected function getRowDataElement($request, &$rowId) {
		// fallback on the parent if a rowId is found
		if ( !empty($rowId) ) {
			return parent::getRowDataElement($request, $rowId);
		}

		// Otherwise return from the newRowId
		// FIXME: Validate user ID?
		$newRowId = $this->getNewRowId($request);
		$userId = (int) $newRowId['name'];
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getById($userId);
	}

	/**
	 * @copydoc ListbuilderHandler::getOptions
	 */
	function getOptions() {
		// Initialize the object to return
		$items = array(
			array()
		);

		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$submission = $this->getSubmission();

		// FIXME: add stage id?
		$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submission->getId());
		while ($user = $users->next()) {
			$items[0][$user->getId()] = $user->getFullName() . ' <' . $user->getEmail() . '>';
		}
		return $items;
	}

	/**
	 * @copydoc GridHandler::loadData($request, $filter)
	 */
	protected function loadData($request) {
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$submission = $this->getSubmission();

		// A list of user IDs may be specified via request parameter; validate them.
		$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submission->getId());
		$selectedUserIds = (array) $request->getUserVar('userIds');
		$items = array();
		while ($user = $users->next()) {
			if (in_array($user->getId(), $selectedUserIds)) $items[$user->getId()] = $user;
		}
		return $items;
	}
}

?>
