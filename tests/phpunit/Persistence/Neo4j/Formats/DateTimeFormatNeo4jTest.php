<?php

declare( strict_types = 1 );

namespace phpunit\Persistence\Neo4j\Formats;

use Laudis\Neo4j\Types\LocalDateTime;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\DateTimeFormat;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @group Database
 */
class DateTimeFormatNeo4jTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		self::markTestSkipped( 'Format not supported yet' );
		//$this->setUpNeo4j();
	}

	public function testStoresAsDateTimes(): void {
		$store = $this->newNeo4jQueryStore();
		$subjectId = TestSubject::uniqueId();

		$store->savePage( TestPage::build(
			mainSubject: TestSubject::build(
				id: $subjectId,
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'MyProperty' ),
						format: DateTimeFormat::NAME,
						value: new StringValue(
							'2023-09-13T14:22:23.000Z',
							'Ignored bad value',
							'2150-12-07T13:37:42.000Z',
							'2150-12-07T13:37:42.123Z',
							'2150-12-07T13:37:61.000Z', // Seconds too high
							'2150-12-07', // Still valid
						)
					),
				] )
			),
		) );

		$result = $store->runReadQuery(
			"MATCH (n {id: '$subjectId'}) RETURN n.MyProperty"
		)->toRecursiveArray()[0];

		$this->assertEquals(
			[
				new LocalDateTime( 1694614943, 0 ),
				new LocalDateTime( 5709706662, 0 ),
				new LocalDateTime( 5709706662, 123000000 ),
				new LocalDateTime( 5709657600, 0 ),
			],
			$result['n.MyProperty']
		);
	}

}
