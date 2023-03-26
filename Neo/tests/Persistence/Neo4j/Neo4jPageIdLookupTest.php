<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jPageIdLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jPageIdLookup
 */
class Neo4jPageIdLookupTest extends TestCase {

	public function testReturnsNullOnEmptyGraph(): void {
		$this->assertNull( $this->newLookup()->getPageIdOfSubject( new SubjectId( '404' ) ) );
	}

	private function newLookup(): Neo4jPageIdLookup {
		try {
			$client = NeoWikiExtension::getInstance()->getNeo4jClient();
			$client->run( 'MATCH (n) DETACH DELETE n' );
		}
		catch ( \Exception $e ) {
			$this->markTestSkipped( 'Neo4j not available' );
		}

		return new Neo4jPageIdLookup( $client );
	}

}
