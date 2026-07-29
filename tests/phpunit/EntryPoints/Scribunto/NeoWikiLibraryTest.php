<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

/**
 * Lua integration tests for the mw.neowiki Scribunto library.
 *
 * @group Lua
 * @group Database
 */
class NeoWikiLibraryTest extends NeoWikiLibraryTestBase {

	/**
	 * Scribunto 1.46 onwards has each concrete test class name one engine. LuaStandalone is the
	 * pick because it needs no PHP extension and so is present wherever the suite runs, not
	 * because the library is specific to it.
	 */
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}

}
