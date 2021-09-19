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

		$oRow = wfGetDB( DB_REPLICA )->selectRow(
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

		$oRes = wfGetDB( DB_REPLICA )->select(
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
		$oDbw = wfGetDB( DB_PRIMARY );
		$sTranlationTable = Helper::getConfig()->get( Config::TRANSLATION_TABLE );
		$sLanguageTable = Helper::getConfig()->get( Config::LANGUAGE_TABLE );

		foreach( $aTranslations as $oTranslation ) {
			$oDbw->insert(
				$sTranlationTable,
				[
					'source' => $iSourceId,
					'translate' => $oTranslation->id
				]
			);

			$oDbw->delete(
				$sLanguageTable,
				[
					'page_id' => $oTranslation->id
				]
			);
			$oDbw->insert(
				$sLanguageTable,
				[
					'page_id' => $oTranslation->id,
					'lang' => $oTranslation->lang
				]
			);
		}
	}

	protected static function deleteTranslations( $aTranslations ) {
		$aIds = [];
		foreach( $aTranslations as $oTranslation ) {
			$aIds[] = $oTranslation->id;
		}
		$bRes = wfGetDB( DB_PRIMARY )->delete(
			Helper::getConfig()->get( Config::TRANSLATION_TABLE ),
			[ 'translate' => $aIds ],
			__METHOD__
		);
		$bRes = wfGetDB( DB_PRIMARY )->delete(
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
		$oRes = wfGetDB( DB_REPLICA )->select(
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
