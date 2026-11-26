<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\FunctionCallTracking
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @group Database
 */
class FunctionCallTrackingTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_PROP = 'neowiki_functions';
	private const string PAGE = 'FunctionCallTrackingTestPage';
	private const string MODULE = 'FunctionCallTrackingTestModule';

	/**
	 * @dataProvider parserFunctionCallProvider
	 */
	public function testPageCallingAParserFunctionHasThePageProp( string $wikitext ): void {
		$this->assertContains( self::PAGE_PROP, $this->pagePropsOf( $wikitext ) );
	}

	public static function parserFunctionCallProvider(): iterable {
		yield 'view' => [ '{{#view:}}' ];
		yield 'main_subject' => [ '{{#main_subject:}}' ];
		yield 'neowiki_value' => [ '{{#neowiki_value: Motto}}' ];
		yield 'create_subject' => [ '{{#create_subject: schema=Company}}' ];
		yield 'cypher_raw' => [ '{{#cypher_raw: RETURN 1}}' ];
	}

	public function testPageWithoutNeoWikiFunctionsLacksThePageProp(): void {
		$this->assertNotContains(
			self::PAGE_PROP,
			$this->pagePropsOf( "Plain [[text]] with {{PAGENAME}} and {{#tag:nowiki|a tag}}" )
		);
	}

	public function testPageCallingALuaFunctionHasThePageProp(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertContains(
			self::PAGE_PROP,
			$this->pagePropsOfModuleInvocation(
				"local p = {}\n"
				. "function p.run() return tostring( mw.neowiki.getSubject( 's11111111111111' ) ) end\n"
				. 'return p'
			)
		);
	}

	public function testPageInvokingAModuleThatCallsNoNeoWikiFunctionLacksThePageProp(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertNotContains(
			self::PAGE_PROP,
			$this->pagePropsOfModuleInvocation(
				"local p = {}\n"
				. "function p.run() return 'plain' end\n"
				. 'return p'
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function pagePropsOf( string $wikitext ): array {
		$properties = $this->parserOutputOn( self::PAGE, $wikitext, ParserOptions::newFromAnon() )->getPageProperties();

		return array_keys( $properties );
	}

	/**
	 * @return string[]
	 */
	private function pagePropsOfModuleInvocation( string $moduleCode ): array {
		$this->editPage( Title::makeTitle( NS_MODULE, self::MODULE ), new ScribuntoContent( $moduleCode ) );

		return $this->pagePropsOf( '{{#invoke:' . self::MODULE . '|run}}' );
	}

}
