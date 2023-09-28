<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j\Formats;

use Laudis\Neo4j\Types\Date;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\DateFormat;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\DateFormat
 * @group database
 */
class DateFormatNeo4jTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testStoresAsDates(): void {
		$store = $this->newNeo4jQueryStore();
		$subjectId = TestSubject::uniqueId();

		$store->savePage( TestPage::build(
			mainSubject: TestSubject::build(
				id: $subjectId,
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'MyProperty' ),
						format: DateFormat::NAME,
						value: new StringValue( '2023-09-28', 'Ignored bad value', '2023-09-29' )
					),
				] )
			),
		) );

		$result = $store->runReadQuery(
			"MATCH (n {id: '$subjectId'}) RETURN n.MyProperty[0] + duration('P3D') AS ModifiedDate1, n.MyProperty[0] as Date2"
		)->toRecursiveArray()[0];

		$this->assertEquals(
			new Date( 19631 ),
			$result['ModifiedDate1']
		);

		$this->assertEquals(
			new Date( 19628 ),
			$result['Date2']
		);
	}

}
