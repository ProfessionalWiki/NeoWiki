<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetPageSubjectsPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery
 * @group Database
 */
class GetPageSubjectsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			'GetPageSubjectsApiTestSchema',
			<<<JSON
{
	"title": "GetPageSubjectsApiTestSchema",
	"propertyDefinitions": {}
}
JSON
		);
	}

	public function testReturnsMainAndChildSubjects(): void {
		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_Berlin',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111111',
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::build( 'population', '3700000' ),
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGPS1111112',
					label: new SubjectLabel( 'Population 2024' ),
					schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' )
				),
			),
		);

		$pageId = $revision->getPage()->getId();

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$pageId ]
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( $pageId, $body['pageId'] );
		$this->assertSame( 'sTestGPS1111111', $body['mainSubjectId'] );
		$this->assertSame( [ 'sTestGPS1111111', 'sTestGPS1111112' ], array_keys( $body['subjects'] ) );
		$this->assertSame( 'Berlin', $body['subjects']['sTestGPS1111111']['label'] );
		$this->assertSame( 'GetPageSubjectsApiTestSchema', $body['subjects']['sTestGPS1111111']['schema'] );
		$this->assertSame(
			[ 'type' => 'text', 'value' => [ '3700000' ] ],
			$body['subjects']['sTestGPS1111111']['statements']['population']
		);
		$this->assertSame( 'Population 2024', $body['subjects']['sTestGPS1111112']['label'] );
	}

	public function testReturnsEmptyResponseForPageWithoutSubjects(): void {
		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => '999999' ]
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString(
			'{"pageId":999999,"mainSubjectId":null,"subjects":[]}',
			$response->getBody()->getContents()
		);
	}

	public function testIncludesSchemasAndReferencedSubjectsWhenExpanded(): void {
		$this->createSchema(
			'GetPageSubjectsApiTestRelationSchema',
			<<<JSON
{
	"title": "GetPageSubjectsApiTestRelationSchema",
	"propertyDefinitions": {
		"partner": {
			"type": "relation",
			"relation": "Partner",
			"targetSchema": "GetPageSubjectsApiTestSchema"
		}
	}
}
JSON
		);

		$this->createPageWithSubjects(
			'GetPageSubjectsApiTest_RelationTarget',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111aa1',
				label: new SubjectLabel( 'Target' ),
				schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' ),
			),
		);

		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_WithRelation',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111aa2',
				label: new SubjectLabel( 'Source' ),
				schemaName: new SchemaName( 'GetPageSubjectsApiTestRelationSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'partner', [
						\ProfessionalWiki\NeoWiki\Tests\Data\TestRelation::build(
							id: 'rTestGPS1111aa2',
							targetId: 'sTestGPS1111aa1',
						),
					] ),
				] )
			),
		);

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$revision->getPage()->getId() ],
				'queryParams' => [ 'expand' => 'schemas|relations' ],
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertArrayHasKey( 'schemas', $body );
		$this->assertArrayHasKey( 'GetPageSubjectsApiTestRelationSchema', $body['schemas'] );
		$this->assertArrayHasKey( 'GetPageSubjectsApiTestSchema', $body['schemas'] );
		$this->assertArrayHasKey( 'partner', $body['schemas']['GetPageSubjectsApiTestRelationSchema']['propertyDefinitions'] );

		$this->assertArrayHasKey( 'referencedSubjects', $body );
		$this->assertArrayHasKey( 'sTestGPS1111aa1', $body['referencedSubjects'] );
		$this->assertSame( 'Target', $body['referencedSubjects']['sTestGPS1111aa1']['label'] );
		$this->assertSame(
			'GetPageSubjectsApiTest RelationTarget',
			$body['referencedSubjects']['sTestGPS1111aa1']['pageTitle']
		);
	}

	public function testReturnsChildrenWhenMainSubjectIsAbsent(): void {
		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_NoMain',
			mainSubject: null,
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGPS1111121',
					label: new SubjectLabel( 'orphan child' ),
					schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' )
				),
			),
		);

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$revision->getPage()->getId() ]
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertNull( $body['mainSubjectId'] );
		$this->assertSame( [ 'sTestGPS1111121' ], array_keys( $body['subjects'] ) );
	}

}
