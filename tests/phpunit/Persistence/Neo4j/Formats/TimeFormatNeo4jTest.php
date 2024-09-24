<?php

declare( strict_types = 1 );

namespace phpunit\Persistence\Neo4j\Formats;

use Laudis\Neo4j\Types\LocalTime;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TimeFormat;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @group Database
 */
class TimeFormatNeo4jTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		self::markTestSkipped( 'Format not supported yet' );
		//$this->setUpNeo4j();
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
						format: TimeFormat::NAME,
						value: new StringValue(
							'13:37',
							'Ignored bad value',
							'21:42:42',
							'00:00:61', // Invalid
							'24:00:01', // Invalid
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
				new LocalTime( 49020000000000 ),
				new LocalTime( 78162000000000 ),
			],
			$result['n.MyProperty']
		);
	}

}
