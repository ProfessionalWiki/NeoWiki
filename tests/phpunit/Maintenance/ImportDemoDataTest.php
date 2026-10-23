<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\Status\Status;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use ProfessionalWiki\NeoWiki\Maintenance\ImportDemoData;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/ImportDemoData.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\ImportDemoData
 * @group Database
 */
class ImportDemoDataTest extends MaintenanceBaseTestCase {

	private const string REFUSAL = 'Refused by ImportDemoDataTest';

	protected function setUp(): void {
		parent::setUp();
		// The singleton caches Schemas by page and revision id, which repeat across tests' temp tables.
		NeoWikiExtension::resetInstance();
	}

	protected function getMaintenanceClass(): string {
		return ImportDemoData::class;
	}

	public function testDemoDataImportsIntoAFreshWiki(): void {
		$this->assertTrue( $this->maintenance->execute(), $this->getActualOutput() );
	}

	public function testRefusedSaveFailsTheRunNamingThePageAndTheReason(): void {
		$this->refuseEverySave();

		$this->assertFalse( $this->maintenance->execute() );
		$this->expectOutputRegex( '/Mapping:EDM: ' . self::REFUSAL . '/' );
	}

	private function refuseEverySave(): void {
		$this->setTemporaryHook(
			'MultiContentSave',
			static function ( $renderedRevision, $user, $summary, $flags, Status $hookStatus ): bool {
				$hookStatus->fatal( 'rawmessage', self::REFUSAL );
				return false;
			}
		);
	}

}
