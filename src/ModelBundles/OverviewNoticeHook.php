<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\ModelBundles;

use MediaWiki\Output\OutputPage;
use ProfessionalWiki\NeoWiki\EntryPoints\Hook\NeoWikiGetFrontendModulesHook;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;

/**
 * Special:NeoWiki shows a user who may install model bundles the setup of the data model while the wiki has no
 * Schemas, and how many installed bundles have a newer version, both as a notice of this extension's module.
 */
class OverviewNoticeHook implements NeoWikiGetFrontendModulesHook {

	public const array INSTALL_RIGHTS = [ 'editsitejson', 'editinterface' ];

	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		if ( !$out->getTitle()?->isSpecial( 'NeoWiki' ) || !$out->getAuthority()->isAllowedAll( ...self::INSTALL_RIGHTS ) ) {
			return;
		}

		$modules[] = 'ext.neowiki.modelBundles';
		$out->addJsConfigVars( 'wgNeoWikiModelBundlesSetupOffered', !self::wikiHasSchemas() );
	}

	public static function wikiHasSchemas(): bool {
		return NeoWikiExtension::getInstance()->getSchemaNameLookup()->getSchemaNamesMatching( '', 1 ) !== [];
	}

}
