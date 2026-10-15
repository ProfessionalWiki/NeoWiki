<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectCountLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the integration base class for the pages whose saves fill the table the lookup reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectCountLookup
 * @group Database
 */
class DatabaseSubjectCountLookupTest extends NeoWikiIntegrationTestCase {

	public function testCountsTheSubjectsOfEachSchemaThatHasSome(): void {
		$this->createPageWithoutMainSubject(
			'Page one',
			self::subject( 'sTestDSC1111111', 'Computer' ),
			self::subject( 'sTestDSC1111112', 'Person' )
		);
		$this->createPageWithoutMainSubject( 'Page two', self::subject( 'sTestDSC1111113', 'Computer' ) );

		$this->assertSame( [ 'Computer' => 2, 'Person' => 1 ], $this->counts() );
	}

	public function testCountsASubjectThatTwoPagesHoldOnce(): void {
		$this->createPageWithoutMainSubject( 'Page one', self::subject( 'sTestDSC1111111', 'Computer' ) );
		$this->createPageWithoutMainSubject( 'Page two', self::subject( 'sTestDSC1111111', 'Computer' ) );

		$this->assertSame( [ 'Computer' => 1 ], $this->counts() );
	}

	private static function subject( string $id, string $schema ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( $schema ) );
	}

	/**
	 * @return array<string, int> Sorted by Schema name, which the lookup does not promise.
	 */
	private function counts(): array {
		$lookup = new DatabaseSubjectCountLookup( $this->getServiceContainer()->getConnectionProvider() );
		$counts = $lookup->getSubjectCountsBySchema();
		ksort( $counts );

		return $counts;
	}

}
