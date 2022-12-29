<?php

namespace MultiLanguageManager;

class Helper {

	/**
	 * @return \GlobalVarConfig
	 */
	public static function getConfig() {
		return \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()->makeConfig( 'mlm' );
	}

	/**
	 * @param \Title $oTitle
	 * @return \Status
	 */
	public static function isValidTitle( \Title $oTitle = null ) {
		if( !$oTitle ) {
			return \Status::newFatal( 'mlm-error-title-invalid' );
		}
		if( !$oTitle->exists() ) {
			return \Status::newFatal(
				'mlm-error-title-notexists',
				$oTitle->getFullText()
			);
		}
		if( $oTitle->isTalkPage() ) {
			return \Status::newFatal(
				'mlm-error-title-istalkpage',
				$oTitle->getFullText()
			);
		}
		if( !$oTitle->getNamespace() < 0 ) {
			return \Status::newFatal(
				'mlm-error-title-nsnotallowed',
				$oTitle->getFullText()
			);
		}
		$aNonTranslatableNs = static::getConfig()->get(
			Config::NON_TRANSLATABLE_NAMESPACES
		);
		if( in_array( $oTitle->getNamespace(), $aNonTranslatableNs ) ) {
			return \Status::newFatal(
				'mlm-error-title-nsnotallowed',
				$oTitle->getFullText()
			);
		}
		return \Status::newGood( $oTitle );
	}

	/**
	 * Returns the main part of the global systems language code
	 * @return string
	 */
	public static function getSystemLanguageCode() {
		$oGlobals = \MediaWiki\MediaWikiServices::getInstance()
			->getMainConfig();
		list( $sLang ) = explode( '-', $oGlobals->get( 'LanguageCode' ) );
		return $sLang;
	}

	/**
	 * @param string $sLang
	 * @return Status
	 */
	public static function getValidLanguage( $sLang ) {
		list( $sLang ) = explode( '-', $sLang );
		if( empty( $sLang ) ) {
			return \Status::newFatal( 'mlm-error-lang-invalid' );
		}
		$bAvailableLang = in_array(
			$sLang,
			static::getConfig()->get( Config::AVAILABLE_LANGUAGES )
		);
		if( !$bAvailableLang ) {
			return \Status::newFatal(
				'mlm-error-lang-notallowed',
				$sLang
			);
		}
		return \Status::newGood( $sLang );
	}

	/**
	 * @param \MultiLanguageManager\MultiLanguageTranslation $oOld
	 * @param \MultiLanguageManager\MultiLanguageTranslation $oNew
	 * @return array
	 */
	public static function diffTranslations( MultiLanguageTranslation $oOld, MultiLanguageTranslation $oNew ) {
		$aNew = array_filter(
			$oNew->getTranslations(),
			function( $e ) use ( $oOld ){
				if( !$oOld->isTranslation( \Title::newFromID( $e->id ) ) ) {
					return true;
				}
				if( !$oOld->isTranslatedLang( $e->lang ) ) {
					return true;
				}
				return false;
		});
		$aDeleted = array_filter(
			$oOld->getTranslations(),
			function( $e ) use ( $oNew ){
				if( !$oNew->isTranslation( \Title::newFromID( $e->id ) ) ) {
					return true;
				}
				if( !$oNew->isTranslatedLang( $e->lang ) ) {
					return true;
				}
				return false;
		});

		return [
			'new' => $aNew,
			'deleted' => $aDeleted,
		];
	}

	/**
	 * @return \SpecialPage|null
	 */
	public static function getSpecialPage() {
		$pageName = static::getConfig()->get( Config::SPECIAL_PAGE_NAME );
		return \MediaWiki\MediaWikiServices::getInstance()
			->getSpecialPageFactory()
			->getPage( $pageName );
	}

	/**
	 * Returns the available languagecodes filtert by used languages in
	 * MultiLanguageTranslation, when given. Set $bNoSysLang to false if the
	 * system language should not get removed
	 * @param \MultiLanguageManager\MultiLanguageTranslation $oTranslation
	 * @param boolean $bNoSysLang
	 * @return array
	 */
	public static function getAvailableLanguageCodes( \MultiLanguageManager\MultiLanguageTranslation $oTranslation = null, $bNoSysLang = true ) {
		$sSystemLang = static::getSystemLanguageCode();
		$aLangs = static::getConfig()->get(
			Config::AVAILABLE_LANGUAGES
		);

		$funcFilter = function( $e ) use(
			$oTranslation,
			$sSystemLang,
			$bNoSysLang
		) {
			if( $bNoSysLang && $e == $sSystemLang ) {
				return false;
			}
			if( !$oTranslation ) {
				return true;
			}
			if( $oTranslation->isTranslatedLang( $e ) ) {
				return false;
			}
			return true;
		};
		$aFilteredLangs = array_filter( $aLangs, $funcFilter );

		return array_unique( $aFilteredLangs );
	}

	/**
	 * Returns the url to the flag image for a given language code
	 * @param string $sLang
	 * @return string
	 */
	public static function getLangFlagUrl( $sLang ) {
		$oGlobals = \MediaWiki\MediaWikiServices::getInstance()
			->getMainConfig();
		$sLang = strtoupper( $sLang );
		if( $sLang == 'EN' ) {
			$sLang = 'GB';
		}
		//TODO: descibe, problem with language code != language flag
		\Hooks::run( 'MultiLanguageManagerGetLangFlagUrlCorrection', [
			&$sLang
		]);
		$sScriptPath = $oGlobals->get( "ScriptPath" );
		return
			"$sScriptPath/extensions/MultiLanguageManager/resources/images/$sLang.png";
	}

	/**
	 * Returns the full LanguageName for given language code
	 * @param string $sLang
	 * @return string
	 */
	public static function getLanguageName( $sLang ) {
		return \MediaWiki\MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageName( $sLang );
	}

	/**
	 * Returns the user language code for given language code or system language
	 * code, when not valid
	 * @param string $sLang
	 * @return string
	 */
	public static function getUserLanguageCode( $sLang ) {
		//TODO: descibe, problem with language code != language flag
		\Hooks::run( 'MultiLanguageManagerUserLanguageCodeCorrection', [
			&$sLang
		]);
		if( !static::getLanguageName( $sLang ) ) {
			return static::getSystemLanguageCode();
		}
		return $sLang;
	}
}