<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence\Formats;

use Laudis\Neo4j\Types\Date;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @group Database
 */
class DateFormatNeo4jTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	private function savePageWithDateStatement(): SubjectId {
		$subjectId = TestSubject::uniqueId();

		$this->newProjectionStore()->savePage( TestPage::build(
			mainSubject: TestSubject::build(
				id: $subjectId,
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'MyProperty' ),
						propertyType: DateType::NAME,
						value: new StringValue( '2023-09-28', 'Ignored bad value', '2023-09-29' )
					),
				] )
			),
		) );

		return $subjectId;
	}

	public function testStoresAsDates(): void {
		$subjectId = $this->savePageWithDateStatement();

		$result = $this->readGraph(
			"MATCH (n {id: '$subjectId'}) RETURN n.MyProperty[0] + duration('P3D') AS ModifiedDate1, n.MyProperty[1] as Date2"
		)->toRecursiveArray()[0];

		$this->assertEquals(
			new Date( 19631 ),
			$result['ModifiedDate1']
		);

		$this->assertEquals(
			new Date( 19629 ),
			$result['Date2']
		);
	}

	public function testStoredDatesNormalizeToIsoStrings(): void {
		$subjectId = $this->savePageWithDateStatement();

		$rows = ( new Neo4jResultNormalizer() )->convertRows(
			$this->readGraph(
				"MATCH (n {id: '$subjectId'}) RETURN n.MyProperty AS dates"
			)
		);

		$this->assertSame(
			[ 1 => [ 'dates' => [
				1 => '2023-09-28',
				2 => '2023-09-29',
			] ] ],
			$rows
		);
	}

	public function testDateOperationsWorkOnStoredValues(): void {
		$subjectId = $this->savePageWithDateStatement();

		$result = $this->readGraph(
			"MATCH (n {id: '$subjectId'})
				WHERE n.MyProperty[0] < date('2023-09-29')
					AND n.MyProperty[1] > date('2023-09-28')
				RETURN n.id AS id"
		);

		$this->assertSame( $subjectId->text, $result->first()->get( 'id' ) );
	}

}
