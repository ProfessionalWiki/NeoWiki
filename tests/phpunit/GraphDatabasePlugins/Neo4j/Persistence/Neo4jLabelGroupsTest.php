<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jLabelGroups;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jLabelGroups
 */
class Neo4jLabelGroupsTest extends TestCase {

	public function testNoSubjectsGiveNoGroups(): void {
		$this->assertSame( [], Neo4jLabelGroups::build( [] ) );
	}

	public function testSubjectsSharingTheirLabelsEndUpInOneGroup(): void {
		$this->assertSame(
			[ [ 'labels' => [ 'Country' ], 'subjectIds' => [ 's1', 's2', 's3' ] ] ],
			Neo4jLabelGroups::build( [
				's1' => [ 'Country' ],
				's2' => [ 'Country' ],
				's3' => [ 'Country' ],
			] )
		);
	}

	public function testSubjectsWithDifferentLabelsEndUpInDifferentGroups(): void {
		$this->assertSame(
			[
				[ 'labels' => [ 'Country' ], 'subjectIds' => [ 's1', 's3' ] ],
				[ 'labels' => [ 'City' ], 'subjectIds' => [ 's2' ] ],
			],
			Neo4jLabelGroups::build( [
				's1' => [ 'Country' ],
				's2' => [ 'City' ],
				's3' => [ 'Country' ],
			] )
		);
	}

	public function testLabelsInADifferentOrderAreTheSameGroup(): void {
		$this->assertSame(
			[ [ 'labels' => [ 'Country', 'Subject' ], 'subjectIds' => [ 's1', 's2' ] ] ],
			Neo4jLabelGroups::build( [
				's1' => [ 'Subject', 'Country' ],
				's2' => [ 'Country', 'Subject' ],
			] )
		);
	}

	public function testSubjectsWithoutLabelsAreLeftOut(): void {
		$this->assertSame(
			[ [ 'labels' => [ 'Country' ], 'subjectIds' => [ 's2' ] ] ],
			Neo4jLabelGroups::build( [
				's1' => [],
				's2' => [ 'Country' ],
				's3' => [],
			] )
		);
	}

	public function testRepeatedLabelsOfOneSubjectAreCollapsed(): void {
		$this->assertSame(
			[ [ 'labels' => [ 'Country' ], 'subjectIds' => [ 's1', 's2' ] ] ],
			Neo4jLabelGroups::build( [
				's1' => [ 'Country', 'Country' ],
				's2' => [ 'Country' ],
			] )
		);
	}

}
