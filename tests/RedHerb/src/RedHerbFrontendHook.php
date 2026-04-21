<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use MediaWiki\Output\OutputPage;
use Skin;

class RedHerbFrontendHook {

	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.redherb';
	}

}
