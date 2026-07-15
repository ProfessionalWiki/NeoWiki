<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

/**
 * Lua integration tests for the mw.neowiki Scribunto library.
 *
 * Tests run against every Lua engine the platform can run — both LuaSandbox and
 * LuaStandalone on x86 — via NeoWikiLibraryTestBase::suite().
 *
 * @group Lua
 * @group Database
 */
class NeoWikiLibraryTest extends NeoWikiLibraryTestBase {

}
