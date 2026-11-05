<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the integration base for a real parser, and the database for the page lookup a record makes.
 *
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder
 * @group Database
 */
class ParserPageDependencyRecorderTest extends NeoWikiIntegrationTestCase {

	public function testRecordsIntoTheOutputTheParserHasWhenRecording(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( Title::newFromText( 'Reading page' ), ParserOptions::newFromAnon(), Parser::OT_HTML );
		$recorder = ParserPageDependencyRecorder::forParser( $parser );

		$parser->resetOutput();
		$recorder->recordDependencyOn( Title::newFromText( 'Read page' ) );

		$this->assertSame( [ 'Read page' ], $this->templatesOf( $parser->getOutput() ) );
	}

}
