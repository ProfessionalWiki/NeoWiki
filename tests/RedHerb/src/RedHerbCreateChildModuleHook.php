<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use MediaWiki\Output\OutputPage;
use ProfessionalWiki\NeoWiki\EntryPoints\Hook\NeoWikiGetFrontendModulesHook;
use Skin;

class RedHerbCreateChildModuleHook implements NeoWikiGetFrontendModulesHook {

	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.redherb-create-child';
	}

}
