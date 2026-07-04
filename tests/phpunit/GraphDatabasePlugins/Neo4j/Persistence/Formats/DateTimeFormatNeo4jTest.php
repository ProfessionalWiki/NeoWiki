<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
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

	private function savePageWithDateTimeStatement(): SubjectId {
		$subjectId = TestSubject::uniqueId();

		$this->newProjectionStore()->savePage( TestPage::build(
			mainSubject: TestSubject::build(
				id: $subjectId,
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'MyProperty' ),
						propertyType: DateTimeType::NAME,
						value: new StringValue(
							'2023-09-13T14:22:23Z',
							'Ignored bad value',
							'2150-12-07T13:37:42.123+02:00',
							'2150-12-07T13:37:61.000Z', // Seconds too high
							'2150-12-07', // No time and timezone
						)
					),
				] )
			),
		) );

		return $subjectId;
	}

	public function testStoresParseableValuesAsNativeDatetimes(): void {
		$subjectId = $this->savePageWithDateTimeStatement();

		$result = $this->readGraph(
			"MATCH (n {id: '$subjectId'}) RETURN [ value IN n.MyProperty | value.epochMillis ] AS millis"
		);

		$this->assertSame(
			[ 1694614943000, 5709699462123 ],
			$result->first()->get( 'millis' )->toArray(),
			'parseable values should be stored as Neo4j datetimes, malformed ones omitted'
		);
	}

	public function testDatetimeOperationsWorkOnStoredValues(): void {
		$subjectId = $this->savePageWithDateTimeStatement();

		$result = $this->readGraph(
			"MATCH (n {id: '$subjectId'})
				WHERE n.MyProperty[0] < datetime('2024-01-01T00:00:00Z')
					AND n.MyProperty[1] > datetime('2150-12-07T11:00:00Z')
				RETURN n.id AS id"
		);

		$this->assertSame( $subjectId->text, $result->first()->get( 'id' ) );
	}

}
