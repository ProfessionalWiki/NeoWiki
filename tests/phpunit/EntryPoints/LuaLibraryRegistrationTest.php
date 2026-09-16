<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onScribuntoExternalLibraries
 * @group Database
 */
class LuaLibraryRegistrationTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_PAGE = 'LuaLibraryRegistrationTestSubject';
	private const string MODULE = 'LuaLibraryRegistrationTest';
	private const string MOTTO = 'Read through mw.neowiki';

	public function testTheLibraryIsRegistered(): void {
		$libraries = [];

		NeoWikiHooks::onScribuntoExternalLibraries( 'lua', $libraries );

		$this->assertSame( [ 'mw.neowiki' => ScribuntoLuaLibrary::class ], $libraries );
	}

	public function testNothingIsRegisteredWhenLuaIsDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableLua', false );
		$libraries = [];

		NeoWikiHooks::onScribuntoExternalLibraries( 'lua', $libraries );

		$this->assertSame( [], $libraries );
	}

	public function testAModuleReadsASubject(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->createSubjectPageAndModule();

		$html = $this->invokeTheModule();

		$this->assertStringContainsString( self::MOTTO, $html );
		$this->assertStringNotContainsString( 'scribunto-error', $html );
	}

	public function testAModuleReadsNothingWhenLuaIsDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->overrideConfigValue( 'NeoWikiEnableLua', false );
		$this->createSubjectPageAndModule();

		$html = $this->invokeTheModule();

		$this->assertStringNotContainsString( self::MOTTO, $html );
		$this->assertStringContainsString( 'scribunto-error', $html );
	}

	private function createSubjectPageAndModule(): void {
		$this->createPageWithSubjects(
			self::SUBJECT_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( self::MOTTO ) ),
			] ) )
		);

		$this->editPage(
			Title::makeTitle( NS_MODULE, self::MODULE ),
			new ScribuntoContent(
				"local nw = require( 'mw.neowiki' )\n" .
				"local p = {}\n" .
				"function p.motto( frame ) return nw.getValue( 'Motto', { page = frame.args[1] } ) or '' end\n" .
				"return p"
			)
		);
	}

	private function invokeTheModule(): string {
		return $this->parseWikitextOn(
			'LuaLibraryRegistrationTestPage',
			'{{#invoke:' . self::MODULE . '|motto|' . self::SUBJECT_PAGE . '}}'
		);
	}

}
