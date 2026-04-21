<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Hook;

use MediaWiki\Output\OutputPage;
use Skin;

/**
 * Hook interface for extensions to declare ResourceLoader modules that NeoWiki
 * should load alongside ext.neowiki. Implementations should append module names
 * to $modules. $out and $skin are provided for conditional loading (e.g.
 * per-namespace or per-user).
 */
interface NeoWikiGetFrontendModulesHook {

	/**
	 * @param string[] &$modules
	 */
	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void;

}
