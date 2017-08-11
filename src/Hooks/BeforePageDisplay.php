<?php

namespace MultiLanguageManager\Hooks;

class BeforePageDisplay {

	/**
	 *
	 * @var \OutputPage
	 */
	protected $oOutputPage = null;


	/**
	 *
	 * @var \Skin
	 */
	protected $oSkin = null;

	/**
	 *
	 * @param \OutputPage $outputPage
	 * @param \Skin $sk
	 */
	public function __construct( \OutputPage $outputPage, \Skin $sk ) {
		$this->oOutputPage = $outputPage;
		$this->oSkin = $sk;
	}

	/**
	 *
	 * @return boolean
	 */
	public function process() {
		$this->oOutputPage->addModuleStyles( 'ext.mlm.styles' );
		return true;
	}
}