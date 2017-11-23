<?php

namespace MultiLanguageManager;

class MultiLanguageTranslation extends DataProvider {
	/**
	 * Contains all created Instances
	 * @var \MultiLanguageManager\MultiLanguageTranslation[]
	 */
	protected static $aInstances = [];

	/**
	 * Source title
	 * @var Title | null
	 */
	protected $oSourceTitle = null;

	/**
	 * Array of Translations
	 * @var array
	 */
	protected $aTranslations = [];

	/**
	 * @param integer $iSourceId
	 * @param array $aTranslations
	 */
	protected function __construct( $iSourceId = 0, array $aTranslations = [] ) {
		if( $iSourceId > 0 ) {
			$this->oSourceTitle = \Title::newFromID( $iSourceId );
		}
		$this->aTranslations = $aTranslations;
	}

	/**
	 * Adds an instance to the cache
	 * TODO: Real caching
	 * @param \MultiLanguageManager\MultiLanguageTranslation $oInstance
	 * @return \MultiLanguageManager\MultiLanguageTranslation
	 */
	protected static function appendCache( MultiLanguageTranslation $oInstance ) {
		if( !$oInstance->getSourceTitle() instanceof \Title ) {
			return $oInstance;
		}
		static::$aInstances[
			(int) $oInstance->getSourceTitle()->getArticleID()
		] = $oInstance;
		return $oInstance;
	}

	/**
	 * @param \Title $oTitle
	 * @param boolean $bNoCache
	 * @return \MultiLanguageManager\MultiLanguageTranslation
	 */
	public static function newFromTitle( \Title $oTitle, $bNoCache = false ) {
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOk() ) {
			return null;
		}
		if( !$bNoCache && $oInstance = static::fromCache( $oTitle ) ) {
			return $oInstance;
		}
		if( $bNoCache ) {
			return static::fromDB( $oTitle );
		}
		return static::appendCache( static::fromDB( $oTitle ) );
	}

	/**
	 * Returns the instance from cache
	 * TODO: Real caching
	 * @return \MultiLanguageManager\MultiLanguageTranslation | null
	 */
	protected static function fromCache( \Title $oTitle ) {
		if( isset( static::$aInstances[ (int) $oTitle->getArticleID() ] ) ) {
			static::$aInstances[ (int) $oTitle->getArticleID() ];
		}
		foreach( static::$aInstances as $iID => $oInstance ) {
			if( !$oInstance->isTranslation( $oTitle ) ) {
				continue;
			}
			return $oInstance;
		}
		return null;
	}

	/**
	 * @return \GlobalVarConfig
	 */
	public function getConfig() {
		return Helper::getConfig();
	}

	/**
	 * Checks if given \Title is the source \Title
	 * @param \Title $oTitle
	 * @return boolean
	 */
	public function isSourceTitle( \Title $oTitle ) {
		if( !$this->getSourceTitle() instanceof \Title ) {
			return false;
		}
		return $this->getSourceTitle()->equals( $oTitle );
	}

	/**
	 * Returns the source \Title or null if not set
	 * @return \Title | null
	 */
	public function getSourceTitle() {
		return $this->oSourceTitle;
	}

	/**
	 * Checks if the given \Title is a translation
	 * @param \Title $oTitle
	 * @return boolean
	 */
	public function isTranslation( \Title $oTitle ) {
		foreach( $this->getTranslations() as $oTranslation ) {
			if( (int) $oTitle->getArticleID() !== $oTranslation->id ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * Checks if given language code is in translations
	 * @param string $sLang
	 * @return boolean
	 */
	public function isTranslatedLang( $sLang ) {
		return $oTranslate = $this->getTranslationFromLang( $sLang );
	}

	/**
	 * Returns translation to given language code or false, when not set
	 * @param string $sLang
	 * @return \stdClass | boolean
	 */
	public function getTranslationFromLang( $sLang ) {
		$oStatus = Helper::getValidLanguage( $sLang );
		if( !$oStatus->isOk() ) {
			return false;
		}
		$sLang = $oStatus->getValue();
		foreach( $this->getTranslations() as $oTranslation ) {
			if( $oTranslation->lang != $sLang ) {
				continue;
			}
			return $oTranslation;
		}
		return false;
	}

	/**
	 * Returns an array of translations
	 * @return array
	 */
	public function getTranslations() {
		return $this->aTranslations;
	}

	/**
	 * Adds a Title as a Translation
	 * @param \Title $oTitle
	 * @param string $sLang
	 * @return \Status
	 */
	public function addTranslation( \Title $oTitle, $sLang ) {
		$oStatus = Helper::getValidLanguage( $sLang );
		if( !$oStatus->isOK() ) {
			return $oStatus;
		}
		$sLang = $oStatus->getValue();
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOk() ) {
			return $oStatus;
		}
		$oTranslation = static::newFromTitle( $oTitle, true );
		if( !$oTranslation ) {
			//Something unexpected!
			return \Status::newFatal( 'mlm-error-title-invalid' );
		}
		if( $oTranslation->isSourceTitle( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isalreadysource',
				$oTitle->getFullText()
			);
		}
		if( $this->isTranslation( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isalreadysource',
				$oTitle->getFullText()
			);
		}
		if( $oTranslation->getSourceTitle()
			&& !$oTranslation->getSourceTitle()->equals( $this->getSourceTitle() )
			&& $oTranslation->isTranslation( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isalreadysource',
				$oTitle->getFullText()
			);
		}
		if( $sLang == Helper::getSystemLanguageCode() ) {
			return \Status::newFatal(
				"mlm-error-lang-notallowed"
			);
		}

		$aLangs = \MultiLanguageManager\Helper::getAvailableLanguageCodes(
			$this
		);
		if( !in_array( $sLang, $aLangs ) ) {
			return \Status::newFatal(
				"mlm-error-lang-alreadytraslated",
				$sLang
			);
		}
		$this->aTranslations[] = (object) [
			'id' => (int) $oTitle->getArticleID(),
			'lang' => $sLang,
		];
		return \Status::newGood( $this );
	}

	/**
	 * Removes a Title as a Translation
	 * @param \Title $oTitle
	 * @return \Status
	 */
	public function removeTranslation( \Title $oTitle ) {
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOk() ) {
			return $oStatus;
		}
		if( !$this->isTranslation( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isnottranslation',
				$oTitle->getFullText()
			);
		}
		foreach( $this->aTranslations as $iKey => $oTranslation ) {
			if( (int) $oTitle->getArticleID() !== $oTranslation->id ) {
				continue;
			}
			unset( $this->aTranslations[$iKey] );
		}
		return \Status::newGood( $this );
	}

	/**
	 * Sets the source title, if there is no source title yet
	 * @param \Title $oTitle
	 * @return \Status
	 */
	public function setSourceTitle( \Title $oTitle ) {
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOk() ) {
			return $oStatus;
		}
		if( $this->getSourceTitle() instanceof \Title ) {
			if( $this->isSourceTitle( $oTitle ) ) {
				return \Status::newFatal(
					'mlm-error-title-isalreadysource',
					$oTitle->getFullText()
				);
			}
			return \Status::newFatal(
				'mlm-error-title-isalreadytranslation',
				$oTitle->getFullText()
			);
		}
		$oTranslation = static::newFromTitle( $oTitle, true );
		if( !$oTranslation ) {
			//Something unexpected!
			return \Status::newFatal( 'mlm-error-title-invalid' );
		}
		if( $oTranslation->isSourceTitle( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isalreadysource',
				$oTitle->getFullText()
			);
		}
		if( $oTranslation->isTranslation( $oTitle ) ) {
			return \Status::newFatal(
				'mlm-error-title-isalreadytranslation',
				$oTitle->getFullText()
			);
		}

		$this->oSourceTitle = $oTitle;
		return \Status::newGood( $this );
	}

	/**
	 * Invalidates the cache of this instance
	 * TODO: Real caching
	 * @return \MultiLanguageManager\MultiLanguageTranslation
	 */
	public function invalidate() {
		if( !$this->getSourceTitle() instanceof \Title ) {
			return $this;
		}
		$bExists = isset(
			static::$aInstances[ (int) $this->getSourceTitle()->getArticleID()]
		);
		if( !$bExists ) {
			return $this;
		}
		unset(
			static::$aInstances[ (int) $this->getSourceTitle()->getArticleID()]
		);
		return $this;
	}

	/**
	 * Performs the save of the current state of this object
	 * @return \Status
	 */
	public function save() {
		if( empty( $this->getTranslations() ) ) {
			return \Status::newFatal( 'No Translations' );
		}
		if( !$this->getSourceTitle() instanceof \Title ) {
			return \Status::newFatal( 'No Source' );
		}

		$oOldInstance = static::makefromDB( $this->getSourceTitle() );
		//never existed
		if( !$oOldInstance->getSourceTitle() instanceof \Title ) {
			static::insertTranslations(
				(int) $this->getSourceTitle()->getArticleID(),
				$this->getTranslations()
			);
		} else {
			$aDiff = Helper::diffTranslations( $oOldInstance, $this );
			if( !empty( $aDiff['new'] ) ) {
				static::insertTranslations(
					(int) $this->getSourceTitle()->getArticleID(),
					$aDiff['new']
				);
			}
			if( !empty( $aDiff['deleted'] ) ) {
				static::deleteTranslations( $aDiff['deleted'] );
			}
		}
		return \Status::newGood( $this->invalidate() );
	}

	/**
	 * Deletes all translations
	 * @return \Status
	 */
	public function delete() {
		if( !$this->getSourceTitle() instanceof \Title ) {
			return \Status::newFatal( 'No Source' );
		}
		$oOldInstance = static::makefromDB( $this->getSourceTitle() );
		//never existed
		if( !$oOldInstance->getSourceTitle() instanceof \Title ) {
			return \Status::newFatal( 'Never existed' ); //TODO: Msg
		}
		static::deleteTranslations( $oOldInstance->getTranslations() );
		return \Status::newGood( $this->invalidate() );
	}
}