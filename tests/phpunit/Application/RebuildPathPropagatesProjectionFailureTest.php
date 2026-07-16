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
 * Guards the split between the two write paths at the wiring level: the maintenance rebuild path
 * must run the propagating composite, so a backend failure escapes the rebuilder to where the
 * RebuildGraphDatabases per-page catch reports it. If the rebuild path were wired with the hook
 * path's failure-isolating composite instead, the throw would be swallowed and the script would
 * falsely report every page as rebuilt.
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
		$this->markPageTableAsUsed();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a throwing plugin registered; reset it so later tests
		// get a clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRebuildPropagatesBackendFailureSoTheScriptCanReportIt(): void {
		$this->createPageWithSubjects( 'Rebuild failure page', TestSubject::build() );

		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );
		$rebuilder = NeoWikiExtension::getInstance()->newSubjectPageRebuilder();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE );

		$rebuilder->rebuild( Title::newFromText( 'Rebuild failure page' ) );
	}

}
