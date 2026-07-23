<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectContentRepository;

/**
 * What a reader ends up with, rather than what the parser function returns: the corruption this
 * guards against happens in MediaWiki's parser, after the function has handed its text back, so it
 * is invisible to a test that only inspects the return value.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ViewParserFunction
 * @group Database
 */
class ViewParserFunctionParsingTest extends MediaWikiIntegrationTestCase {

	private const string URL = 'https://example.com/x';

	private function parseView(): string {
		$parser = $this->getServiceContainer()->getParserFactory()->create();

		$parser->setFunctionHook(
			'view',
			static function ( Parser $parser, string ...$args ): string|array {
				return ( new ViewParserFunction( new InMemorySubjectContentRepository() ) )->handle( $parser, ...$args );
			}
		);

		return $parser->parse(
			'{{#view: Foo | "' . self::URL . '" }}',
			Title::makeTitle( NS_MAIN, 'ViewParsingTest' ),
			ParserOptions::newFromAnon()
		)->getText();
	}

	public function testErrorUrlSurvivesParsingUnchanged(): void {
		$html = html_entity_decode( $this->parseView() );

		$this->assertStringContainsString( '"' . self::URL . '"', $html );
	}

	public function testErrorUrlIsNotTurnedIntoLink(): void {
		$html = $this->parseView();

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( '%22', $html );
	}

}
