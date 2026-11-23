<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\ModelBundles;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use ProfessionalWiki\NeoWiki\EntryPoints\Hook\NeoWikiGetFrontendModulesHook;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * To a user who may install model bundles, Special:NeoWiki shows the setup of the data model while it is offered,
 * and how many installed bundles have another version, as a notice of this extension's module. Special:Schemas
 * shows the setup while it is offered.
 */
class ModelBundlesNoticeHook implements NeoWikiGetFrontendModulesHook {

	public const array INSTALL_RIGHTS = [ 'editsitejson', 'editinterface' ];

	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$title = $out->getTitle();

		if ( $title === null || !$out->getAuthority()->isAllowedAll( ...self::INSTALL_RIGHTS ) ) {
			return;
		}

		if ( $title->isSpecial( 'NeoWiki' ) ) {
			$this->addNotice( $modules, $out, self::setupOffered( $out->getConfig() ) );
		} elseif ( $title->isSpecial( 'Schemas' ) && self::setupOffered( $out->getConfig() ) ) {
			$this->addNotice( $modules, $out, true );
		}
	}

	/**
	 * @param string[] &$modules
	 */
	private function addNotice( array &$modules, OutputPage $out, bool $setupOffered ): void {
		$modules[] = 'ext.neowiki.modelBundles';
		$out->addJsConfigVars( 'wgNeoWikiModelBundlesSetupOffered', $setupOffered );
	}

	/**
	 * While the wiki has no Schemas and no installed bundle, when it names a catalog to set up from.
	 */
	private static function setupOffered( Config $config ): bool {
		return $config->get( 'NeoWikiModelBundleCatalogs' ) !== [] && !self::wikiHasSchemas() && !self::wikiHasInstalledBundles();
	}

	public static function wikiHasSchemas(): bool {
		return NeoWikiExtension::getInstance()->getSchemaNameLookup()->getSchemaNamesMatching( '', 1 ) !== [];
	}

	/**
	 * Whether any install record exists: a page MediaWiki:NeoWiki/Model bundles/<name>.json.
	 */
	private static function wikiHasInstalledBundles(): bool {
		$database = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		return $database->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->where( [
				'page_namespace' => NS_MEDIAWIKI,
				$database->expr( 'page_title', IExpression::LIKE, new LikeValue( 'NeoWiki/Model_bundles/', $database->anyString() ) ),
			] )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField() !== false;
	}

}
