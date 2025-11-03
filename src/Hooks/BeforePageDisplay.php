<?php

namespace MultiLanguageManager\Hooks;

use MediaWiki\Title\Title;
use MultiLanguageManager\Helper;
use MultiLanguageManager\MultiLanguageTranslation as Translation;

class BeforePageDisplay {

	/**
	 * @var \OutputPage
	 */
	protected $oOutputPage = null;

	/**
	 * @var \Skin
	 */
	protected $oSkin = null;

	/**
	 * @param \OutputPage $outputPage
	 * @param \Skin $sk
	 */
	public function __construct( \OutputPage $outputPage, \Skin $sk ) {
		$this->oOutputPage = $outputPage;
		$this->oSkin = $sk;
	}

	/**
	 * @return bool
	 */
	public function process() {
		$this->oOutputPage->addModuleStyles( 'ext.mlm.styles' );

		if ( !Helper::isValidTitle( $this->oSkin->getTitle() )->isOK() ) {
			return true;
		}
		$this->oOutputPage->addModules( 'ext.mlm' );

		$availableLanguages = Helper::getAvailableLanguageCodes();
		$this->oOutputPage->addJsConfigVars(
			'mlmLanguages',
			array_values( $availableLanguages )
		);

		$sysLang = Helper::getSystemLanguageCode();
		$langFlags = [
			$sysLang => Helper::getLangFlagUrl( $sysLang ),
		];
		foreach ( $availableLanguages as $lang ) {
			$langFlags[$lang] = Helper::getLangFlagUrl( $lang );
		}
		$this->oOutputPage->addJsConfigVars(
			'mlmLanguageFlags',
			$langFlags
		);

		$oTransations = Translation::newFromTitle( $this->oSkin->getTitle() );
		if ( !$oTransations || !$oTransations->getSourceTitle() instanceof Title ) {
			return true;
		}

		$this->oOutputPage->addJsConfigVars(
			'mlmSourceTitle',
			$oTransations->getSourceTitle()->getFullText()
		);

		$translations = [];
		foreach ( $oTransations->getTranslations() as $translation ) {
			if ( !$title = Title::newFromID( $translation->id ) ) {
				continue;
			}
			$translations[] = [
				'text' => $title->getFullText(),
				'lang' => $translation->lang,
				'id' => $translation->id,
			];
		}

		$this->oOutputPage->addJsConfigVars(
			'mlmTranslations',
			$translations
		);

		return true;
	}
}
