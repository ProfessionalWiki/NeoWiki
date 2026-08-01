<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use RuntimeException;

/**
 * Guards the split between the two write paths at the wiring level: the maintenance rebuild path must
 * project through the scoped store unwrapped, so a backend failure escapes the rebuilder to the
 * rebuild, which decides whether it costs one page or the whole run. Wiring the rebuild path with the
 * hook path's failure-isolating decorator instead would swallow the throw and report every page as
 * rebuilt.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder
 * @group Database
 */
class RebuildPathPropagatesProjectionFailureTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testRebuildPropagatesBackendFailureSoTheRebuildCanReportIt(): void {
		$this->createPageWithSubjects( 'Rebuild failure page', TestSubject::build() );

		$rebuilder = NeoWikiExtension::getInstance()->newSubjectPageRebuilderFor( new ThrowingGraphDatabasePlugin() );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE );

		$rebuilder->rebuild( Title::newFromText( 'Rebuild failure page' ) );
	}

}
