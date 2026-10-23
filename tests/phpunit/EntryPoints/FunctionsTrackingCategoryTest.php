<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\FunctionsTrackingCategory
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @group Database
 */
class FunctionsTrackingCategoryTest extends NeoWikiIntegrationTestCase {

	private const string CATEGORY = 'Pages_using_NeoWiki_functions';
	private const string PAGE = 'FunctionsTrackingCategoryTestPage';
	private const string MODULE = 'FunctionsTrackingCategoryTestModule';

	/**
	 * @dataProvider parserFunctionCallProvider
	 */
	public function testPageCallingAParserFunctionIsInTheCategory( string $wikitext ): void {
		$this->assertContains( self::CATEGORY, $this->categoriesOf( $wikitext ) );
	}

	public static function parserFunctionCallProvider(): iterable {
		yield 'view' => [ '{{#view:}}' ];
		yield 'main_subject' => [ '{{#main_subject:}}' ];
		yield 'neowiki_value' => [ '{{#neowiki_value: Motto}}' ];
		yield 'create_subject' => [ '{{#create_subject: schema=Company}}' ];
		yield 'cypher_raw' => [ '{{#cypher_raw: RETURN 1}}' ];
	}

	public function testPageWithoutNeoWikiFunctionsIsNotInTheCategory(): void {
		$this->assertNotContains(
			self::CATEGORY,
			$this->categoriesOf( "Plain [[text]] with {{PAGENAME}} and {{#tag:nowiki|a tag}}" )
		);
	}

	public function testPageCallingALuaFunctionIsInTheCategory(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertContains(
			self::CATEGORY,
			$this->categoriesOfModuleInvocation(
				"local p = {}\n"
				. "function p.run() return tostring( mw.neowiki.getSubject( 's11111111111111' ) ) end\n"
				. 'return p'
			)
		);
	}

	/**
	 * Scribunto hands Lua numbers to PHP as numbers, which a string parameter accepts only outside strict mode.
	 */
	public function testALuaFunctionStillAcceptsANumberForAString(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertContains(
			self::CATEGORY,
			$this->categoriesOfModuleInvocation(
				"local p = {}\n"
				. "function p.run() return tostring( mw.neowiki.getMainSubject( 2024 ) ) end\n"
				. 'return p'
			)
		);
	}

	public function testPageInvokingAModuleThatCallsNoNeoWikiFunctionIsNotInTheCategory(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertNotContains(
			self::CATEGORY,
			$this->categoriesOfModuleInvocation(
				"local p = {}\n"
				. "function p.run() return 'plain' end\n"
				. 'return p'
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function categoriesOf( string $wikitext ): array {
		return $this->parserOutputOn( self::PAGE, $wikitext, ParserOptions::newFromAnon() )->getCategoryNames();
	}

	/**
	 * @return string[]
	 */
	private function categoriesOfModuleInvocation( string $moduleCode ): array {
		$this->editPage( Title::makeTitle( NS_MODULE, self::MODULE ), new ScribuntoContent( $moduleCode ) );

		return $this->categoriesOf( '{{#invoke:' . self::MODULE . '|run}}' );
	}

}
