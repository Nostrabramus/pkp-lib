<?php

/**
 * @file controllers/grid/queries/QueryNotesGridHandler.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridHandler
 * @ingroup controllers_grid_query
 *
 * @brief base PKP class to handle query grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class QueryNotesGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function QueryNotesGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_AUTHOR, ROLE_ID_SUB_EDITOR),
			array('fetchGrid', 'fetchRow', 'insertNote', 'deleteNote'));
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

	/**
	 * Get the query.
	 * @return Query
	 */
	function getQuery() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
	}

	/**
	 * Get the stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}

	//
	// Overridden methods from PKPHandler.
	// Note: this is subclassed in application-specific grids.
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

		// Get the access policy
		import('lib.pkp.classes.security.authorization.QueryAccessPolicy');
		$this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);
		$this->setTitle('common.notes');

		// Load pkp-lib translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_EDITOR
		);

		import('lib.pkp.controllers.grid.queries.QueryNotesGridCellProvider');
		$cellProvider = new QueryNotesGridCellProvider();

		// Columns
		$this->addColumn(
			new GridColumn(
				'contents',
				'common.note',
				null,
				null,
				$cellProvider,
				array('width' => 90, 'alignment' => COLUMN_ALIGNMENT_CENTER, 'html' => true)
			)
		);
		$this->addColumn(
			new GridColumn(
				'from',
				'submission.query.from',
				null,
				null,
				$cellProvider,
				array('html' => true)
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return QueryNotesGridRow
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.queries.QueryNotesGridRow');
		return new QueryNotesGridRow($this->getSubmission(), $this->getStageId(), $this->getQuery());
	}

	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		return array(
			'submissionId' => $this->getSubmission()->getId(),
			'stageId' => $this->getStageId(),
			'queryId' => $this->getQuery()->getId(),
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter = null) {
		return $this->getQuery()->getReplies(null, NOTE_ORDER_ID, SORT_DIRECTION_ASC);
	}

	//
	// Public Query Notes Grid Actions
	//
	/**
	 * Insert a new note.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function insertNote($args, $request) {
		// Form handling
		import('lib.pkp.controllers.grid.queries.form.QueryNoteForm');
		$queryNoteForm = new QueryNoteForm($this->getQuery());
		$queryNoteForm->readInputData();
		if ($queryNoteForm->validate()) {
			$note = $queryNoteForm->execute($request);
			return DAO::getDataChangedEvent($note->getId());
		} else {
			return new JSONMessage(true, $queryForm->fetch($request));
		}
	}

	/**
	 * Delete a query note.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteNote($args, $request) {
		$query = $this->getQuery();
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$note = $noteDao->getById($request->getUserVar('noteId'));
		if ($note->getAssocType()==ASSOC_TYPE_QUERY && $note->getAssocId()==$query->getId()) {
			$noteDao->deleteObject($note);
			return DAO::getDataChangedEvent($note->getId());
		}
		return new JSONMessage(false); // The query note could not be found.
	}

}

?>
