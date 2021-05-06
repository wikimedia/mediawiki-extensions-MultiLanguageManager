<?php

namespace MultiLanguageManager\Hooks;

use MultiLanguageManager\Helper;
use MultiLanguageManager\MultiLanguageTranslation as Translation;

class UserGetLanguageObject {

	/**
	 *
	 * @var \User
	 */
	protected $oUser = null;

	/**
	 *
	 * @var string
	 */
	protected $sCode = '';

	/**
	 * @param \User $user
	 * @param string $code
	 */
	public function __construct( $user, &$code ) {
		$this->oUser = $user;
		$this->sCode = &$code;
	}

	/**
	 *
	 * @return boolean
	 */
	public function process() {
		if( $this->oUser->isRegistered() ) {
			return true;
		}
		$oTitle = \RequestContext::getMain()->getTitle();
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOK() ) {
			return true;
		}
		$oTranslations = Translation::newFromTitle( $oTitle );
		if( !$oTranslations ) {
			return true;
		}
		if( !$oTranslations->getSourceTitle() instanceof \Title ) {
			return true;
		}
		if( $oTranslations->isSourceTitle( $oTitle ) ) {
			$this->sCode = Helper::getSystemLanguageCode();
			return true;
		}
		foreach( $oTranslations->getTranslations() as $oTranslation ) {
			if( (int) $oTitle->getArticleID() !== $oTranslation->id ) {
				continue;
			}
			$sLang = Helper::getUserLanguageCode( $oTranslation->lang );
			$this->sCode = $sLang;
			return true;
		}
		return true;
	}
}
