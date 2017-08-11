<?php

namespace MultiLanguageManager\Hooks;

use MultiLanguageManager\MultiLanguageTranslation as Translation;

class ArticleDelete {

	/**
	 *
	 * @var \WikiPage
	 */
	protected $oWikiPage = null;

	/**
	 * @param \WikiPage $wikiPageBeforeDelete
	 * @param \User $user
	 * @param string $reason
	 * @param string $error
	*/
	public function __construct( \WikiPage &$article, \User &$user, &$reason, &$error ) {
		$this->oWikiPage = $article;
	}

	/**
	 *
	 * @return boolean
	 */
	public function process() {
		$oTitle = $this->oWikiPage->getTitle();
		$oTranslation = Translation::newFromTitle( $oTitle );
		if( !$oTranslation ) {
			return true;
		}
		if( $oTranslation->isSourceTitle( $oTitle ) ) {
			$oTranslation->delete();
			return true;
		}
		if( $oTranslation->isTranslation( $oTitle ) ) {
			$oStatus = $oTranslation->removeTranslation( $oTitle );
			if( !$oStatus->isOK() ) {
				return true;
			}
			$oStatus = $oTranslation->save();
		}
		return true;
	}
}