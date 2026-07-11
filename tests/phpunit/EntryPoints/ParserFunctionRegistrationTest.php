<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserFirstCallInit
 * @group Database
 */
class ParserFunctionRegistrationTest extends NeoWikiIntegrationTestCase {

	public function testCypherRawRegisteredWhenConfigured(): void {
		$parser = $this->recordingParser( $names );

		NeoWikiHooks::onParserFirstCallInit( $parser );

		$this->assertContains( 'cypher_raw', $names );
		$this->assertContains( 'view', $names );
		$this->assertContains( 'neowiki_value', $names );
	}

	public function testCypherRawNotRegisteredWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$parser = $this->recordingParser( $names );

			NeoWikiHooks::onParserFirstCallInit( $parser );

			$this->assertNotContains( 'cypher_raw', $names );
			$this->assertContains( 'view', $names );
			$this->assertContains( 'neowiki_value', $names );
		} );
	}

	private function recordingParser( ?array &$names ): Parser {
		$names = [];
		$parser = $this->createMock( Parser::class );
		$parser->method( 'setFunctionHook' )->willReturnCallback(
			static function ( string $name ) use ( &$names ): void {
				$names[] = $name;
			}
		);
		return $parser;
	}

}
