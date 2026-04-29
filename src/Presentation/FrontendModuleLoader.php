<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use Skin;

class FrontendModuleLoader {

	public function __construct(
		private readonly HookContainer $hookContainer,
	) {
	}

	public function load( OutputPage $out, Skin $skin ): void {
		$out->addModules( 'ext.neowiki' );
		$out->addModuleStyles( 'ext.neowiki.styles' );

		/** @var list<string> $modules populated by hook handlers */
		$modules = [];
		$this->hookContainer->run( 'NeoWikiGetFrontendModules', [ &$modules, $out, $skin ] );

		foreach ( $modules as $module ) {
			$out->addModules( $module );
		}
	}

}
