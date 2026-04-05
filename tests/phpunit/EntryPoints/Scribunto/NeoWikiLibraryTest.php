<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

/**
 * Lua integration tests for the mw.neowiki Scribunto library.
 *
 * Tests run against all available Lua engines (LuaSandbox and LuaStandalone)
 * via the suite() method inherited from LuaEngineTestBase.
 *
 * @group Lua
 * @group Database
 */
class NeoWikiLibraryTest extends NeoWikiLibraryTestBase {

}
