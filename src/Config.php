<?php

namespace MultiLanguageManager;

use MediaWiki\Config\GlobalVarConfig;

class Config extends GlobalVarConfig {

	const AVAILABLE_LANGUAGES = 'AvailableLanguages';
	const PERMISSION = 'Permission';
	const LANGUAGE_TABLE = 'LanguageTableName';
	const TRANSLATION_TABLE = 'TranslationTableName';
	const SPECIAL_PAGE_NAME = 'SpecialPageName';
	const NON_TRANSLATABLE_NAMESPACES = 'NonTranslatableNamespaces';

	public function __construct() {
		parent::__construct( 'mg' );
	}
}
