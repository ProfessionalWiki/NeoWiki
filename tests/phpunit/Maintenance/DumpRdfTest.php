<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use ProfessionalWiki\NeoWiki\Maintenance\DumpRdf;

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

	public function testEmitsAnEmptyDocumentWhenNoSubjectSlotRoleExists(): void {
		// A wiki that has never stored a Subject has no 'neowiki-subjects' slot role, so the role-id
		// lookup throws NameTableAccessException. Forcing that state (empty table + a store without a
		// warmed cache) proves the dump degrades to an empty document instead of crashing.
		$this->truncateTable( 'slot_roles' );
		$this->getServiceContainer()->resetServiceForTesting( 'SlotRoleStore' );

		ob_start();
		try {
			$this->maintenance->execute();
		} finally {
			$output = ob_get_clean();
		}

		// Reaching this point means execute() did not throw on the missing slot role (the N1 fix).
		// The document is empty: no page named-graph block (which a real dump would open with `{`).
		$this->assertStringNotContainsString( '{', $output );
	}

}
