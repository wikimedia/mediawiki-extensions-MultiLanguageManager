<?php

namespace MultiLanguageManager\Api;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MultiLanguageManager\Config;
use MultiLanguageManager\Helper;
use MultiLanguageManager\MultiLanguageTranslation;

class Tasks extends \ApiBase {
	/**
	 * Returns an array of tasks and their required permissions
	 * array('taskname' => array('read', 'edit'))
	 * @return array[]
	 */
	protected function getRequiredTaskPermissions() {
		return [
			'save' => [
				'read',
				Helper::getConfig()->get( Config::PERMISSION )
			],
			'delete' => [
				'read',
				Helper::getConfig()->get( Config::PERMISSION )
			],
			'get' => [
				'read',
				Helper::getConfig()->get( Config::PERMISSION )
			],
		];
	}

	protected function task_save( $taskData, $params ) {
		$result = $this->makeStandardReturn();
		$sysLang = Helper::getSystemLanguageCode();

		// dont use errors param to prevent random unalterable client side code
		$result->message = [];
		if ( empty( $taskData->srcText ) ) {
			$taskData->srcText = '';
		}
		$oSourceTitle = Title::newFromText( $taskData->srcText );
		$status = Helper::isValidTitle(
			$oSourceTitle
		);
		if ( !$status->isOK() ) {
			$result->message[$sysLang] = $status->getHTML();
		}

		if ( empty( $taskData->translations ) ) {
			$taskData->translations = [];
		}
		if ( is_object( $taskData->translations ) ) {
			$taskData->translations = (array)$taskData->translations;
		}
		foreach ( $taskData->translations as $translation ) {
			$status = Helper::isValidTitle(
				Title::newFromText( $translation->text )
			);
			if ( !$status->isOK() ) {
				$result->message[$translation->lang] = $status->getHTML();
			}
		}
		if ( count( $result->message ) > 0 ) {
			return $result;
		}

		$mlmTranslation = MultiLanguageTranslation::newFromTitle(
			$oSourceTitle
		);

		if ( !$mlmTranslation ) {
			// very unexpected!
			$result->message[$sysLang] = $this->msg(
				'mlm-error-title-invalid'
			)->plain();
			return $result;
		}

		if ( !$mlmTranslation->isSourceTitle( $oSourceTitle ) ) {
			$status = $mlmTranslation->setSourceTitle( $oSourceTitle );
			if ( !$status->isOK() ) {
				$result->message[$sysLang] = $status->getHTML();
				return $result;
			}
		}

		foreach ( $mlmTranslation->getTranslations() as $translation ) {
			$status = $mlmTranslation->removeTranslation(
				Title::newFromID( $translation->id )
			);
			if ( !$status->isOK() ) {
				$result->message[$translation->lang] = $status->getHTML();
				return $result;
			}
		}
		foreach ( $taskData->translations as $translation ) {
			$status = $mlmTranslation->addTranslation(
				Title::newFromText( $translation->text ),
				$translation->lang
			);
			if ( !$status->isOK() ) {
				$result->message[$translation->lang] = $status->getHTML();
			}
		}
		if ( count( $result->message ) > 0 ) {
			return $result;
		}

		$status = $mlmTranslation->save();
		if ( !$status->isOK() ) {
			$result->message[$translation->lang] = $status->getHTML();
			return $result;
		}

		$result->success = true;
		return $result;
	}

	protected function task_delete( $taskData, $params ) {
		$result = $this->makeStandardReturn();
		$sysLang = Helper::getSystemLanguageCode();

		if ( empty( $taskData->srcText ) ) {
			$taskData->srcText = '';
		}
		$oSourceTitle = Title::newFromText( $taskData->srcText );
		$status = Helper::isValidTitle(
			$oSourceTitle
		);
		if ( !$status->isOK() ) {
			$result->message[$sysLang] = $status->getHTML();
			return $result;
		}

		$mlmTranslation = MultiLanguageTranslation::newFromTitle(
			$oSourceTitle
		);

		if ( !$mlmTranslation ) {
			// very unexpected!
			$result->message[$sysLang] = $this->msg(
				'mlm-error-title-invalid'
			)->plain();
			return $result;
		}

		$status = $mlmTranslation->delete();
		if ( !$status->isOK() ) {
			$result->message[$sysLang] = $status->getHTML();
			return $result;
		}
		return $result;
	}

	protected function task_get( $taskData, $params ) {
		$result = $this->makeStandardReturn();
		$sysLang = Helper::getSystemLanguageCode();
		$result->message = [];
		if ( empty( $taskData->srcText ) ) {
			$taskData->srcText = '';
		}
		$oSourceTitle = Title::newFromText( $taskData->srcText );
		$status = Helper::isValidTitle(
			$oSourceTitle
		);
		if ( !$status->isOK() ) {
			$result->message[$sysLang] = $status->getHTML();
			return $result;
		}

		$mlmTranslation = MultiLanguageTranslation::newFromTitle(
			$oSourceTitle
		);

		$translations = $mlmTranslation->getTranslations();
		foreach ( $translations as &$translation ) {
			$title = Title::newFromID( $translation->id );
			$translation->text = $title->getPrefixedText();
		}

		$result->success = 1;
		$result->payload = $translations;
		return $result;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$task = $params['task'];

		$method = "task_$task";
		$result = $this->makeStandardReturn();

		if ( !is_callable( [ $this, $method ] ) ) {
			$result->errors['task'] = "Task '$task' not implemented!";
		} else {
			$res = $this->checkTaskPermission( $task );
			if ( !$res ) {
				$this->dieWithError(
					'apierror-permissiondenied-generic',
					'permissiondenied'
				);
			}
			if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
				$result->message = wfMessage(
					'bs-readonly',
					MediaWikiServices::getInstance()->getReadOnlyMode()->getReason()
				)->plain();
			} else {
				$taskData = $this->getParameter( 'taskData' );
				if ( empty( $result->errors ) && empty( $result->message ) ) {
					try {
						$result = $this->$method( $taskData, $params );
					} catch ( Exception $e ) {
						$result->success = false;
						$result->message = $e->getMessage();
						$mCode = method_exists( $e, 'getCodeString' )
							? $e->getCodeString()
							: $e->getCode();
						if ( $e instanceof DBError ) {
							// TODO: error code for subtypes like DBQueryError or
							//DBReadOnlyError?
							$mCode = 'dberror';
						}
						$result->errors[$mCode] = $e->getMessage();
						$result->errors[0]['code'] = 'unknown error';
					}
				}
			}
		}

		foreach ( $result as $sFieldName => $mFieldValue ) {
			if ( $mFieldValue === null ) {
				// MW Api doesn't like NULL values
				continue;
			}

			// Remove empty 'errors' array from respons as mw.Api in MW 1.30+
			//will interpret this field as indicator for a failed request
			if ( $sFieldName === 'errors' && empty( $mFieldValue ) ) {
				continue;
			}
			$this->getResult()->addValue( null, $sFieldName, $mFieldValue );
		}
	}

	protected function getParameterFromSettings( $paramName, $paramSettings, $parseLimit ) {
		$value = parent::getParameterFromSettings(
			$paramName,
			$paramSettings,
			$parseLimit
		);
		// Unfortunately there is no way to register custom types for parameters
		if ( $paramName == 'taskData' ) {
			$value = \FormatJson::decode( $value );
			if ( empty( $value ) ) {
				return new \stdClass();
			}
		}
		return $value;
	}

	protected function makeStandardReturn() {
		return (object)[
			'errors' => [],
			'success' => false,
			'message' => '',
			'payload' => [],
			'payload_count' => 0
		];
	}

	/**
	 * @param string $task
	 * @return bool null if requested task not in list
	 * true if allowed
	 * false if not found in permission table of current user
	 */
	public function checkTaskPermission( $task ) {
		$taskPermissions = $this->getRequiredTaskPermissions();

		if ( empty( $taskPermissions[$task] ) ) {
			return;
		}
		// lookup permission for given task
		foreach ( $taskPermissions[$task] as $sPermission ) {
			// check if user have needed permission
			if ( $this->getUser()->isAllowed( $sPermission ) ) {
				continue;
			}
			// TODO: Reflect permission in error message
			return false;
		}

		return true;
	}

	/**
	 * Returns an array of allowed parameters
	 * @return array
	 */
	protected function getAllowedParams() {
		return [
			'task' => [
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_TYPE => 'string',
			],
			'taskData' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => false,
				\ApiBase::PARAM_DFLT => '{}',
			],
			'format' => [
				\ApiBase::PARAM_DFLT => 'json',
				\ApiBase::PARAM_TYPE => [ 'json', 'jsonfm' ],
			]
		];
	}

	public function needsToken() {
		return 'csrf';
	}
}
