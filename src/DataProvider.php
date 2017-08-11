<?php

namespace MultiLanguageManager;

abstract class DataProvider {
	/**
	 * Load a MultiLanguageTranslation from the database
	 * @param \Title $oTitle
	 * @return \MultiLanguageManager\MultiLanguageTranslation
	 */
	protected static function fromDB( \Title $oTitle ) {
		$iArticleId = (int) $oTitle->getArticleID();
		$aTables = [ Helper::getConfig()->get( Config::TRANSLATION_TABLE ) ];
		$aFields = [ 'source', 'translate' ];
		$aConditions = [ "source = $iArticleId OR translate = $iArticleId" ];

		$oRow = wfGetDB( DB_SLAVE )->selectRow(
			$aTables,
			$aFields,
			$aConditions,
			__METHOD__
		);
		if( !$oRow ) {
			return new static();
		}

		return static::makefromDB( \Title::newFromID( (int)$oRow->source ) );
	}

	/**
	 * Load a MultiLanguageTranslation from the database form a source title
	 * @param \Title $oSourceTitle
	 * @return \MultiLanguageManager\MultiLanguageTranslation
	 */
	protected static function makefromDB( \Title $oSourceTitle ) {
		$aTables = [
			Helper::getConfig()->get( Config::TRANSLATION_TABLE ),
			Helper::getConfig()->get( Config::LANGUAGE_TABLE )
		];
		$aFields = [ 'source', 'translate', 'lang' ];
		$aConditions = [
			'source' => (int) $oSourceTitle->getArticleID(),
			'page_id = translate',
		];

		$oRes = wfGetDB( DB_SLAVE )->select(
			$aTables,
			$aFields,
			$aConditions,
			__METHOD__
		);
		$aTranslations = [];
		$iSourceId = 0;
		foreach( $oRes as $oRow ) {
			$iSourceId = (int) $oRow->source;
			$aTranslations[] = (object) [
				'id' => (int) $oRow->translate,
				'lang' => $oRow->lang
			];
		}
		return new static(
			$iSourceId,
			$aTranslations
		);
	}

	protected static function insertTranslations( $iSourceId, $aTranslations ) {
		foreach( $aTranslations as $oTranslation ) {
			$bRes = wfGetDB( DB_MASTER )->insert(
				Helper::getConfig()->get( Config::TRANSLATION_TABLE ),
				[
					'source' => $iSourceId,
					'translate' => $oTranslation->id
				],
				__METHOD__
			);
			$bRes = wfGetDB( DB_MASTER )->insert(
				Helper::getConfig()->get( Config::LANGUAGE_TABLE ),
				[
					'page_id' => $oTranslation->id,
					'lang' => $oTranslation->lang
				],
				__METHOD__
			);
		}
	}

	protected static function deleteTranslations( $aTranslations ) {
		$aIds = [];
		foreach( $aTranslations as $oTranslation ) {
			$aIds[] = $oTranslation->id;
		}
		$bRes = wfGetDB( DB_MASTER )->delete(
			Helper::getConfig()->get( Config::TRANSLATION_TABLE ),
			[ 'translate' => $aIds ],
			__METHOD__
		);
		$bRes = wfGetDB( DB_MASTER )->delete(
			Helper::getConfig()->get( Config::LANGUAGE_TABLE ),
			[ 'page_id' => $aIds ],
			__METHOD__
		);
	}

	/**
	 * Returns an array of all source titles
	 * @return array
	 */
	public static function getAllSourceTitles() {
		$oRes = wfGetDB( DB_SLAVE )->select(
			Helper::getConfig()->get( Config::TRANSLATION_TABLE ),
			[ 'source' ],
			[],
			__METHOD__,
			[ 'DISTINCT' ]
		);
		$aReturn = [];
		foreach( $oRes as $oRow ) {
			$aReturn[] = \Title::newFromID( $oRow->source );
		}
		return $aReturn;
	}
}