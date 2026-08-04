<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use Exception;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\Maintenance\DumpRdf;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingPagePropertyProvider;
use RuntimeException;
use Wikimedia\Rdbms\DBUnexpectedError;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/DumpRdf.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\DumpRdf
 * @group Database
 */
class DumpRdfTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return DumpRdf::class;
	}

	public function testFatalErrorOnAnUnknownProjection(): void {
		// No Mapping page declares "bogus", so it is not a known projection and the run aborts before
		// touching any page.
		$this->maintenance->setOption( 'projection', 'bogus' );

		$this->expectCallToFatalError();

		$this->maintenance->execute();
	}

	public function testEmitsAnEmptyDocumentOnAWikiWithoutPages(): void {
		$this->truncateTable( 'page' );

		// The document is empty: no page named-graph block, which a real dump would open with `{`.
		$this->assertStringNotContainsString( '{', $this->runDump() );
	}

	public function testDumpsPageMetadataOfPagesWithoutSubjects(): void {
		$this->insertPage( 'DumpRdfTest plain page', 'No subjects here.' );

		$this->assertStringContainsString( 'DumpRdfTest plain page', $this->runDump() );
	}

	public function testKeepsTheDocumentWellFormedWhenAPageInTheMiddleIsSkipped(): void {
		$this->insertPage( 'DumpRdfTest first page', 'First.' );
		$this->insertPage( 'DumpRdfTest broken page', 'Cannot be described.' );
		$this->insertPage( 'DumpRdfTest last page', 'Last.' );
		$this->failPagePropertiesFor( 'DumpRdfTest broken page', new RuntimeException( 'provider is down' ) );

		$output = $this->runDumpExpectingIncompleteDump();

		$this->assertStringContainsString( 'DumpRdfTest first page', $output );
		$this->assertStringContainsString( 'DumpRdfTest last page', $output );
		$this->assertStringNotContainsString( 'DumpRdfTest broken page', $output );
		$this->assertSame(
			substr_count( $output, '{' ),
			substr_count( $output, '}' ),
			'the skipped page leaves no half-written named graph behind'
		);
	}

	/**
	 * A dump missing pages is still a well-formed document, so the exit code is the only signal a
	 * caller redirecting stdout to a file has that it is incomplete.
	 */
	public function testFailsWhenAPageWasSkipped(): void {
		$this->insertPage( 'DumpRdfTest broken page', 'Cannot be described.' );
		$this->failPagePropertiesFor( 'DumpRdfTest broken page', new RuntimeException( 'provider is down' ) );

		$this->expectCallToFatalError();

		$this->runDump();
	}

	/**
	 * Skipping is for page state the dump cannot describe. A database failure is not that: swallowing
	 * it would turn every remaining page into a skip and hand back an all-but-empty document.
	 */
	public function testDatabaseFailureEndsTheRunInsteadOfSkippingEveryPage(): void {
		$this->insertPage( 'DumpRdfTest plain page', 'No subjects here.' );
		$this->failPagePropertiesFor(
			'DumpRdfTest plain page',
			new DBUnexpectedError( null, 'the database went away' )
		);

		$this->expectException( DBUnexpectedError::class );

		$this->runDump();
	}

	private function runDumpExpectingIncompleteDump(): string {
		ob_start();
		$reportedIncomplete = false;

		try {
			$this->maintenance->execute();
		} catch ( MaintenanceFatalError ) {
			$reportedIncomplete = true;
		} finally {
			$output = (string)ob_get_clean();
		}

		$this->assertTrue( $reportedIncomplete, 'the run reports the dump as incomplete' );

		return $output;
	}

	private function failPagePropertiesFor( string $pageTitle, Exception $failure ): void {
		$this->setTemporaryHook(
			'NeoWikiRegistration',
			static function ( NeoWikiRegistrar $registrar ) use ( $pageTitle, $failure ): void {
				$registrar->addPagePropertyProvider(
					new ThrowingPagePropertyProvider( $pageTitle, $failure )
				);
			}
		);

		NeoWikiExtension::resetInstance();
	}

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	private function runDump(): string {
		ob_start();

		try {
			$this->maintenance->execute();
		} finally {
			$output = ob_get_clean();
		}

		return (string)$output;
	}

}
