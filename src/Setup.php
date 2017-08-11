<?php

namespace MultiLanguageManager;

class Setup {
	const PERMISSION = 'language';

	/**
	 * Callback for 'ExtensionFunctions'
	 */
	public static function init() {
		$GLOBALS['mgNonTranslatableNamespaces'] = [
			NS_MEDIAWIKI,
			NS_FILE,
		];
	}

	/**
	 * Callback for 'ConfigRegistry'
	 * @return \Config
	 */
	public static function makeConfig() {
		return new Config();
	}

	/**
	 * Hook handler for hook 'UnitTestsList'
	 * @param array $paths
	 * @return boolean
	 */
	public static function onUnitTestsList( &$paths ) {
		$paths[] = dirname( __DIR__ ).'/tests/phpunit';
		return true;
	}

	/**
	 * Hook handler for hook 'LoadExtensionSchemaUpdates'
	 * @param \DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlPath = dirname( __DIR__ ) . '/docs';
		$updater->addExtensionTable(
			'page_language',
			$sqlPath . '/page_language.sql'
		);
		$updater->addExtensionTable(
			'page_translation',
			$sqlPath . '/page_translation.sql'
		);
	}

	/**
	 * Hook handler for hook 'BeforePageDisplay'
	 * @param \OutputPage $outputPage
	 * @param \Skin $sk
	 * @return boolean
	 */
	public static function onBeforePageDisplay( &$outputPage, &$sk ) {
		$handler = new Hooks\BeforePageDisplay( $outputPage, $sk );
		return $handler->process();
	}

	/**
	 * Hook handler for hook 'SkinTemplateNavigation_Universal'
	 * @param \SkinTemplate $skinTemplate
	 * @param array $content_navigation
	 * @return boolean
	 */
	public static function onSkinTemplateNavigation_Universal( &$skinTemplate, &$content_navigation ) {
		$handler = new Hooks\SkinTemplateContentActions(
			$skinTemplate,
			$content_navigation
		);
		return $handler->process();
	}

	/**
	 * Hook handler for hook 'ArticleDeleteComplete'
	 * @param \WikiPage $wikiPageBeforeDelete
	 * @param \User $user
	 * @param string $reason
	 * @param string $error
	 * @return boolean
	 */
	public static function onArticleDelete( \WikiPage &$article, \User &$user, &$reason, &$error ) {
		$handler = new Hooks\ArticleDelete(
			$article,
			$user,
			$reason,
			$error
		);

		return $handler->process();
	}

	/**
	 * Hook handler for hook 'SkinBuildSidebar'
	 * @param Skin $skin
	 * @param array $sidebar
	 * @return boolean
	 */
	public static function onSkinBuildSidebar( $skin, &$sidebar ) {
		$handler = new Hooks\SkinBuildSidebar(
			$skin,
			$sidebar
		);

		return $handler->process();
	}

	/**
	 * Hook handler for hook 'UserGetLanguageObject'
	 * @param \User $user
	 * @param string $code
	 * @return boolean
	 */
	public static function onUserGetLanguageObject( $user, &$code ) {
		$handler = new Hooks\UserGetLanguageObject(
			$user,
			$code
		);

		return $handler->process();
	}
}