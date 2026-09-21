<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi
 * @group Database
 */
class GetSubjectLabelsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testRejectsLimitAboveMaximum(): void {
		// The endpoint runs a per-result read-permission check, so an uncapped limit is an
		// unbounded-work vector. The cap is enforced before the handler runs any query (#1060).
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'schema' => 'Person', 'limit' => '100000' ],
			] )
		);
	}

	/**
	 * What Special:Subject's own lookup sends. A default standing in for the omitted `schema`
	 * would name a Schema no Subject has, and find nothing.
	 */
	public function testASearchNamingNoSchemaFindsSubjectsOfEverySchema(): void {
		$this->setUpNeo4j();
		$this->createSchema( 'Recipe' );
		$this->createSchema( 'Plant' );
		$this->createPageWithSubjects(
			'GetSubjectLabelsApiTest_Apples',
			otherSubjects: new SubjectMap(
				TestSubject::build( id: 'sTestGSL1111111', label: 'Apple Pie', schemaName: new SchemaName( 'Recipe' ) ),
				TestSubject::build( id: 'sTestGSL1111112', label: 'Apple Tree', schemaName: new SchemaName( 'Plant' ) ),
			)
		);

		$data = $this->executeHandlerAndGetBodyData(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'search' => 'Apple' ],
			] )
		);

		$this->assertSame( [ 'sTestGSL1111111', 'sTestGSL1111112' ], array_column( $data, 'id' ) );
	}

}
