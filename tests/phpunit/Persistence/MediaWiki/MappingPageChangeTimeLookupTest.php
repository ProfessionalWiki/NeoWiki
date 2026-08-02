<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\ProjectionChangeTimeLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPageChangeTimeLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPageChangeTimeLookup
 * @group Database
 */
class MappingPageChangeTimeLookupTest extends NeoWikiIntegrationTestCase {

	public function testAProjectionChangedWhenItsMappingPageWasLastEdited(): void {
		$this->createMapping( 'EDM', '{"version":1,"schemas":{}}' );
		$latestRevision = $this->createMapping( 'EDM', '{"version":1,"prefixes":{},"schemas":{}}' );

		$this->assertSame(
			$latestRevision?->getTimestamp(),
			$this->newLookup()->getLastChangeTime( 'EDM' )
		);
	}

	public function testAProjectionNoMappingPageDefinesHasNeverChanged(): void {
		$this->assertNull( $this->newLookup()->getLastChangeTime( 'EDM' ) );
	}

	public function testAProjectionNameNoPageCouldBeCalledHasNeverChanged(): void {
		$this->assertNull( $this->newLookup()->getLastChangeTime( '<not a title>' ) );
	}

	private function newLookup(): ProjectionChangeTimeLookup {
		return new MappingPageChangeTimeLookup(
			MediaWikiServices::getInstance()->getTitleFactory(),
			MediaWikiServices::getInstance()->getRevisionLookup(),
		);
	}

}
