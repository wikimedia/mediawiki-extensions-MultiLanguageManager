<?php

namespace MultiLanguageManager\Hooks;

use MediaWiki\MediaWikiServices;
use MultiLanguageManager\Helper;
use MultiLanguageManager\Config;

class SkinTemplateContentActions {

	/**
	 *
	 * @var \SkinTemplate
	 */
	protected $oSkinTemplate = null;

	/**
	 *
	 * @var array
	 */
	protected $aContentNavigation = [];

	/**
	 * @param \SkinTemplate $skinTemplate
	 * @param array $content_navigation
	 */
	public function __construct( $skinTemplate, &$content_navigation ) {
		$this->oSkinTemplate = $skinTemplate;
		$this->aContentNavigation = &$content_navigation;
	}

	/**
	 *
	 * @return boolean
	 */
	public function process() {
		if( !isset( $this->aContentNavigation['actions'] ) ) {
			return true;
		}

		$oTitle = $this->oSkinTemplate->getTitle();
		$oStatus = Helper::isValidTitle( $oTitle );
		if( !$oStatus->isOK() ) {
			return true;
		}

		$oConfig = Helper::getConfig();
		$sPermission = $oConfig->get( Config::PERMISSION );

		if ( class_exists( 'MediaWiki\Permissions\PermissionManager' ) ) {
			// MW 1.33+
			if ( !MediaWikiServices::getInstance()->getPermissionManager()
				->userCan(
					$sPermission,
					$this->oSkinTemplate->getUser(),
					$oTitle
				)
			) {
				return true;
			}
		} else {
			if( !$oTitle->userCan( $sPermission ) ) {
				return true;
			}
		}

		$oSpecial = Helper::getSpecialPage();
		$oSpecialTitle = $oSpecial->getPageTitle( $oTitle );
		$this->aContentNavigation['actions']['mlm'] = [
			'class' => false,
			'text' => wfMessage( 'mlm-contentaction-label' )->plain(),
			'href' => $oSpecialTitle->getLocalURL(),
		];
		return true;
	}
}