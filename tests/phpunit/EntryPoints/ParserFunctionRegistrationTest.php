<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserFirstCallInit
 * @group Database
 */
class ParserFunctionRegistrationTest extends NeoWikiIntegrationTestCase {

	public function testFunctionsNeedingNoGraphStoreRegisterWithoutOne(): void {
		$this->runWithoutGraphBackend( function (): void {
			$parser = $this->recordingParser( $names );

			NeoWikiHooks::onParserFirstCallInit( $parser );

			$this->assertContains( 'view', $names );
			$this->assertContains( 'neowiki_value', $names );
			$this->assertContains( 'create_subject', $names );
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
