<?php

namespace MultiLanguageManager\Hooks;

use MediaWiki\Title\Title;
use MultiLanguageManager\Helper;
use MultiLanguageManager\MultiLanguageTranslation as Translation;

class SkinBuildSidebar {

	/**
	 *
	 * @var \Skin
	 */
	protected $oSkin = null;

	/**
	 *
	 * @var array
	 */
	protected $aSidebar = [];

	/**
	 * @param \Skin $skin
	 * @param array &$sidebar
	 */
	public function __construct( $skin, &$sidebar ) {
		$this->oSkin = $skin;
		$this->aSidebar = &$sidebar;
	}

	protected function makeLink( $sLang, Title $oTitle ) {
		$sLangFlagUrl = Helper::getLangFlagUrl( $sLang );
		return [
			'text'  => $sLang,
			'href'  => $oTitle->getLocalURL(),
			'title' => Helper::getLanguageName( $sLang ),
			'style' => 'background-image: url("' . $sLangFlagUrl . '");',
			'class' => 'mlm-flag',
			'id'    => "n-mlm-$sLang",
		];
	}

	/**
	 *
	 * @return bool
	 */
	public function process() {
		$oStatus = Helper::isValidTitle(
			$this->oSkin->getTitle()
		);
		if ( !$oStatus->isOK() ) {
			return true;
		}
		$oTransations = Translation::newFromTitle( $this->oSkin->getTitle() );
		if ( !$oTransations->getSourceTitle() instanceof Title ) {
			return true;
		}

		$aLinks = [ $this->makeLink(
			Helper::getSystemLanguageCode(),
			$oTransations->getSourceTitle()
		) ];
		foreach ( $oTransations->getTranslations() as $oTranslation ) {
			$oTitle = Title::newFromId( $oTranslation->id );
			if ( $oTitle ) {
				$aLinks[] = $this->makeLink( $oTranslation->lang, $oTitle );
			}
		}
		$this->aSidebar = array_merge(
			[ 'MLM' => $aLinks ],
			$this->aSidebar
		);
		return true;
	}
}
