<?php

namespace MultiLanguageManager\Specials;

use MultiLanguageManager\Config;
use MultiLanguageManager\Helper;
use MultiLanguageManager\MultiLanguageTranslation as Translation;


class MultiLanguageManager extends \SpecialPage {
	/**
	 * @var \Title | null
	 */
	protected $oTitle = null;
	/**
	 * @var Translation | null
	 */
	protected $oTranslation = null;

	protected $subPage = '';

	public function __construct( $name = '', $restriction = '', $listed = true, $function = false, $file = '', $includable = false ) {
		$oConfig = Helper::getConfig();
		$sName = $oConfig->get( Config::SPECIAL_PAGE_NAME );
		$sPermission = $oConfig->get( Config::PERMISSION );

		parent::__construct(
			$sName,
			$sPermission
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->subPage = $subPage;
		$this->proccessForm();

		if( !$this->makeTitleContext( $this->subPage ) ) {
			return;
		}
		if( !$this->oTitle ) {
			$this->outputList();
			return;
		}

		if( !$oTranslation = $this->getMultiLanguageTranslation() ) {
			//Something went very wrong here!
			return;
		}
		$this->oTranslation = $oTranslation;
		$this->outputFormStart();

		if( !$this->oTranslation->isSourceTitle( $this->oTitle ) ) {
			$this->mayRedirect();
		}
		$this->outputSourceTitleForm();
		$this->outputTranslationsForm();
		$this->outputSaveButton();
		if( $this->oTranslation->isSourceTitle( $this->oTitle ) ) {
			$this->outputDeleteButton();
		}
		$this->outputFormEnd();
	}

	protected function getMultiLanguageTranslation() {
		return Translation::newFromTitle( $this->oTitle	);
	}

	protected function proccessForm() {
		if( !$this->getRequest()->getVal( 'mlm-form', false ) ) {
			return false;
		}
		$oSourceTitle = \Title::newFromText(
			$this->getRequest()->getVal( 'mlm-sourcetitle', '' )
		);

		$oStatus = Helper::isValidTitle( $oSourceTitle );
		if( !$oStatus->isOK() ) {
			$this->outputError(
				$oStatus->getHTML(),
				wfMessage( 'mlm-input-label-sourcetitle' )->plain()
			);
			return false;
		}

		$oTranslation = Translation
			::newFromTitle(
			$oSourceTitle
		);
		if( !$oTranslation ) {
			//very unexpected!
			$this->outputError(
				\Status::newFatal( 'mlm-error-title-invalid' ),
				wfMessage( 'mlm-input-label-sourcetitle' )->plain()
			);
			return false;
		}

		if( !$oTranslation->isSourceTitle( $oSourceTitle ) ) {
			$oStatus = $oTranslation->setSourceTitle( $oSourceTitle );
			if( !$oStatus->isOK() ) {
				$this->outputError(
					$oStatus->getHTML(),
					wfMessage( 'mlm-input-label-sourcetitle' )->plain()
				);
				return false;
			}
		}

		if( $this->getRequest()->getVal( 'mlm-delete', false ) ) {
			$oStatus = $oTranslation->delete();
			if( !$oStatus->isOK() ) {
				$this->outputError( $oStatus->getHTML() );
				return false;
			}
			return true;
		}

		$sNewTranslation = $this->getRequest()->getVal(
			'mlm-newtranslation',
			''
		);
		if( !empty( $sNewTranslation ) ) {
			$oNewTranslationTitle = \Title::newFromText(
				$sNewTranslation
			);
			$oStatus = $oTranslation->addTranslation(
				$oNewTranslationTitle,
				$this->getRequest()->getVal( 'mlm-newtranslation-lang', '' )
			);
			if( !$oStatus->isOK() ) {
				$this->outputError(
					$oStatus->getHTML(),
					wfMessage( 'mlm-input-label-translationtitles', 1)->text()
				);
				return false;
			}
		}

		$oStatus = $oTranslation->save();
		if( !$oStatus->isOK() ) {
			$this->outputError( $oStatus->getHTML() );
		}
		return true;

	}

	protected function outputError( $sText, $sPrefix = '' ) {
		$this->getOutput()->addHtml(
			\Xml::tags('div', ['class' => 'error'], $sPrefix.$sText )
		);
	}

	/**
	 * Add list of all source pages
	 * @return null
	 */
	protected function outputList() {
		$aTitles = Translation::getAllSourceTitles();

		if( empty( $aTitles ) ) {
			return;
		}
		$oSpecial = Helper::getSpecialPage();
		foreach( $aTitles as $oTitle ) {
			$sLink = $this->getLinkRenderer()->makeLink(
				$oSpecial->getPageTitle( $oTitle->getFullText() ),
				$oTitle->getFullText()
			);
			$this->getOutput()->addHtml( "$sLink<br />" );
		}
		return;
	}

	protected function outputSourceTitleForm( $sHtml = '' ) {
		$bDisabled = false;
		$aArgs = [
			'id' => 'mlm-sourcetitle',
			'value' => '',
			'name' => 'mlm-sourcetitle',
		];
		if( $this->oTranslation && $this->oTranslation->getSourceTitle() instanceof \Title ) {
			$aArgs['value'] = $this->oTranslation->getSourceTitle()->getFullText();
			$aArgs['readonly'] = 'readonly';
			$bDisabled = true;
		} elseif( $this->getRequest()->getVal( 'mlm-sourcetitle', false ) ) {
			$aArgs['value'] = $this->getRequest()->getVal( 'mlm-sourcetitle' );
		}
		$sLegend = \Html::element(
			'legend',
			null,
			wfMessage( 'mlm-input-label-sourcetitle' )->text()
		);
		$sInput = \Html::element( 'input', $aArgs );
		$sSystemLang = Helper::getSystemLanguageCode();
		$sLang = \Xml::tags( 'select', [
			'id' => 'mlm-sourcetitle-lang',
			'disabled' => 'disabled',
		], \Xml::option( $sSystemLang, $sSystemLang ) );

		$sHtml .= \Xml::tags( 'fieldset', null, $sLegend.$sInput.$sLang );
		$this->getOutput()->addHtml( $sHtml );
	}

	protected function outputTranslationsForm( $sHtml = '' ) {
		$iTranslations = count( $this->oTranslation->getTranslations() )+1;
		$sLegend = \Html::element(
			'legend',
			null,
			wfMessage(
				'mlm-input-label-translationtitles',
				$iTranslations
			)->text()
		);
		$sRows = '';
		$bTitleInTranslations = false;
		foreach( $this->oTranslation->getTranslations() as $oTanslation ) {
			$oTitle = \Title::newFromID( $oTanslation->id );
			if( $oTitle->equals( $this->oTitle ) ) {
				$bTitleInTranslations = true;
			}
			$sInput = \Html::element( 'input', [
				'id' => "mlm-translation-$oTanslation->id",
				'value' => $oTitle->getFullText(),
				'readonly' => 'readonly',
			]);
			$sLang = \Xml::tags( 'select', [
				'id' => "mlm-translation-lang-$oTanslation->id",
				'disabled' => 'disabled',
			], \Xml::option( $oTanslation->lang, $oTanslation->lang ) );
			$sRows .= "$sInput$sLang<br />";
		}

		$sValue = '';
		$bIsSourceTitle = $this->oTranslation->isSourceTitle( $this->oTitle );
		if( !$bIsSourceTitle && !$bTitleInTranslations ) {
			$sValue = $this->oTitle->getFullText();
		}
		if( $this->getRequest()->getVal( 'mlm-newtranslation', false ) ) {
			$sValue = $this->getRequest()->getVal( 'mlm-newtranslation' );
		}

		$sInput = \Html::element( 'input', [
			'id' => "mlm-newtranslation",
			'value' => $sValue,
			'name' => "mlm-newtranslation",
		]);
		$sLang = \Xml::tags( 'select', [
			'id' => 'mlm-newtranslation-lang',
			'name' => 'mlm-newtranslation-lang',
		], $this->getLanguageSelectOptions() );
		$sRows .= $sInput.$sLang;

		$sHtml .= \Xml::tags( 'fieldset', null, $sLegend.$sRows );
		$this->getOutput()->addHtml( $sHtml );
	}

	protected function getLanguageSelectOptions( $sHtml = '' ) {
		$aLangs = Helper::getAvailableLanguageCodes( $this->oTranslation );

		foreach( $aLangs as $sLang ) {
			$sHtml .= \Xml::option( $sLang, $sLang );
		}
		return $sHtml;
	}

	protected function outputSaveButton() {
		$oMsg = wfMessage( 'mlm-input-label-save' );
		$this->getOutput()->addHTML(
			\Html::submitButton( $oMsg->plain(), [ 'name' => 'mlm-save' ] )
		);
	}

	protected function outputDeleteButton() {
		$oMsg = wfMessage( 'mlm-input-label-delete' );
		$this->getOutput()->addHTML(
			\Html::submitButton( $oMsg->plain(), [ 'name' => 'mlm-delete' ] )
		);
	}

	protected function outputFormStart() {
		$this->getOutput()->addHTML( \Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle( $this->subPage )->getLocalURL(),
			'id' => 'mlm-form',
		]));
		$this->getOutput()->addHTML(
			\Html::hidden( 'mlm-form', true )
		);
	}

	protected function outputFormEnd() {
		$this->getOutput()->addHTML( \Html::closeElement( 'form' ) );
	}

	protected function makeTitleContext( $subPage = '' ) {
		if( !empty( $subPage ) ) {
			$oTitle = \Title::newFromText( $subPage );
			$oStatus = Helper::isValidTitle( $oTitle );
			if( !$oStatus->isOK() ) {
				$this->outputError( $oStatus->getHTML(), "'$subPage':" );
				return false;
			}
			$this->oTitle = $oTitle;
			return true;
		}
		return true;
	}

	protected function mayRedirect() {
		if( !$this->oTranslation->getSourceTitle() instanceof \Title ) {
			return;
		}
		$oSpecial = Helper::getSpecialPage();
		$this->getOutput()->redirect( $oSpecial->getPageTitle(
				$this->oTranslation->getSourceTitle()->getFullText()
		)->getLocalURL());
	}
}