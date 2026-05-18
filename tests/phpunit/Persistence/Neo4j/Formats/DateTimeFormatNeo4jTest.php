<?php

declare( strict_types = 1 );

namespace phpunit\Persistence\Neo4j\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @group Database
 */
class DateTimeFormatNeo4jTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
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
						propertyType: DateTimeType::NAME,
						value: new StringValue(
							'2023-09-13T14:22:23Z',
							'2150-12-07T13:37:42+02:00',
						)
					),
				] )
			),
		) );

		$result = $store->runReadQuery(
			"MATCH (n {id: '$subjectId'})
				RETURN n.MyProperty = [
					datetime('2023-09-13T14:22:23Z'),
					datetime('2150-12-07T13:37:42+02:00')
				] AS isDatetimeList"
		);

		$this->assertTrue(
			$result->first()->toRecursiveArray()['isDatetimeList'],
			'dateTime statement values should be stored as a list of Neo4j datetimes'
		);
	}

}
