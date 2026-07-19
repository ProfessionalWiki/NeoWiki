<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery
 * @group Database
 */
class GetSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			'GetSubjectApiTestSchema',
			<<<JSON
{
	"title": "GetSubjectApiTestSchema",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"relation": "MyRelation",
			"targetSchema": "GetSubjectApiTestSchema"
		}
	}
}
JSON
		);
	}

	public function testSubjectIsFound(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111114',
				label: new SubjectLabel( 'Test subject sTestGSA1111114' ),
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => 'sTestGSA1111114'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "sTestGSA1111114",
    "subjects": {
        "sTestGSA1111114": {
            "id": "sTestGSA1111114",
            "label": "Test subject sTestGSA1111114",
            "schema": "GetSubjectApiTestSchema",
            "statements": []
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testSubjectIsNotFound(): void {
		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => 'sTestGSA1111115'
				]
			] )
		);

		$this->assertSame(
			'{"subject":null}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testReturnsSubjectDataFromSpecificRevision(): void {
		$originalSubject = TestSubject::build(
			id: 'sTestGSA1111116',
			label: new SubjectLabel( 'original label' ),
			schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
		);

		$revision = $this->createPageWithSubjects( 'GetSubjectApiTestRevision', mainSubject: $originalSubject );

		$updatedSubject = TestSubject::build(
			id: 'sTestGSA1111116',
			label: new SubjectLabel( 'updated label' ),
			schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
		);
		$this->createPageWithSubjects( 'GetSubjectApiTestRevision', mainSubject: $updatedSubject );

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111116' ],
				'queryParams' => [ 'revisionId' => (string)$revision->getId() ],
			] )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'original label', $body['subjects']['sTestGSA1111116']['label'] );
	}

	public function testReturns404ForNonExistentRevision(): void {
		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111116' ],
				'queryParams' => [ 'revisionId' => '999999999' ],
			] )
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testFullExpansion(): void {
		$firstPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0000',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111111',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: 'rTestGSA1111rr1', targetId: 'sTestGSA1111112' ),
							TestRelation::build( id: 'rTestGSA1111rr2', targetId: 'sTestGSA1111113' ),
						]
					)
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGSA1111112',
					schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
				)
			)
		)->getPage()->getId();

		$secondPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0002',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111113',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: 'rTestGSA1111rr3', targetId: 'sTestGSA1111112' ),
						]
					)
				] )
			),
		)->getPage()->getId();

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => 'sTestGSA1111111'
				],
				'queryParams' => [
					'expand' => 'page|relations'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "sTestGSA1111111",
    "subjects": {
        "sTestGSA1111111": {
            "id": "sTestGSA1111111",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "pageNamespaceId": 0,
            "statements": {
                "MyRelation": {
                	"type": "relation",
                	"value": [
						{
							"id": "rTestGSA1111rr1",
							"target": "sTestGSA1111112"
						},
						{
							"id": "rTestGSA1111rr2",
							"target": "sTestGSA1111113"
						}
					]
                }
            }
        },
        "sTestGSA1111112": {
            "id": "sTestGSA1111112",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "pageNamespaceId": 0,
            "statements": []
        },
        "sTestGSA1111113": {
            "id": "sTestGSA1111113",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $secondPageId,
            "pageTitle": "GetSubjectApiTest0002",
            "pageNamespaceId": 0,
            "statements": {
                "MyRelation": {
                "type": "relation",
				"value": [
						{
							"id": "rTestGSA1111rr3",
							"target": "sTestGSA1111112"
						}
					]
                }
            }
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testSubjectOnAnUnreadablePageIsIndistinguishableFromAnAbsentSubject(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest_Restricted',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111244',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$denied = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'subjectId' => 'sTestGSA1111244' ] ] ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$absent = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'subjectId' => 'sTestGSA9999999' ] ] )
		);

		$this->assertSame( 200, $denied->getStatusCode() );
		$this->assertSame( $absent->getBody()->getContents(), $denied->getBody()->getContents() );
	}

	public function testRevisionOnAnUnreadablePageIsIndistinguishableFromAnAbsentRevision(): void {
		$revisionId = $this->createPageWithSubjects(
			'GetSubjectApiTest_RestrictedRevision',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111245',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		)->getId();

		$deniedResponse = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111245' ],
				'queryParams' => [ 'revisionId' => (string)$revisionId ],
			] ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$absentResponse = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111245' ],
				'queryParams' => [ 'revisionId' => '999999999' ],
			] ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( 404, $deniedResponse->getStatusCode() );
		$this->assertSame( $absentResponse->getStatusCode(), $deniedResponse->getStatusCode() );

		$denied = json_decode( $deniedResponse->getBody()->getContents(), true );
		$absent = json_decode( $absentResponse->getBody()->getContents(), true );

		// Only the revision id embedded in the message may differ between "denied" and "nonexistent".
		$absent['message'] = str_replace( '999999999', (string)$revisionId, $absent['message'] );
		$this->assertSame( $absent, $denied );
	}

	public function testReferencedSubjectOnAnUnreadablePageIsOmitted(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest_HiddenTarget',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111246',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$this->createPageWithSubjects(
			'GetSubjectApiTest_VisibleSource',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111247',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'MyRelation', [
						TestRelation::build( id: 'rTestGSA1111rr9', targetId: 'sTestGSA1111246' ),
					] ),
				] )
			)
		);

		$denyTargetPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GetSubjectApiTest_HiddenTarget';

		$body = json_decode( $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111247' ],
				'queryParams' => [ 'expand' => 'relations' ],
			] ),
			authority: $this->mockRegisteredAuthority( $denyTargetPage )
		)->getBody()->getContents(), true );

		$this->assertArrayHasKey( 'sTestGSA1111247', $body['subjects'] );
		$this->assertArrayNotHasKey( 'sTestGSA1111246', $body['subjects'] );
	}

	/**
	 * PointInTimeSubjectLookup::getSubjectFromOtherPage() resolves a requested Subject via the
	 * graph and reads a DIFFERENT page's revision content when the Subject isn't in the primary
	 * (caller-supplied) revision. The handler's revision gate only authorizes the primary
	 * revision's page, so a caller can name a revision on a page they CAN read while asking for
	 * a Subject that actually lives on a page they CANNOT read. GetSubjectQuery::pageIsReadable()
	 * is the only thing standing between that request and the restricted page's data.
	 *
	 * The denial here must be selective (deny only the restricted page, not global read) -
	 * authorityWithGlobalReadButNoPageRead() would be stopped at the handler's revision gate
	 * (on the readable page) and this test would pass without ever reaching the query gate.
	 */
	public function testCrossPageRevisionCannotLeakSubjectFromARestrictedPage(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest_CrossPageRestricted',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111248',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$readableRevisionId = $this->createPageWithSubjects(
			'GetSubjectApiTest_CrossPageReadable',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111249',
				schemaName: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		)->getId();

		$denyRestrictedPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GetSubjectApiTest_CrossPageRestricted';

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => 'sTestGSA1111248' ],
				'queryParams' => [ 'revisionId' => (string)$readableRevisionId ],
			] ),
			authority: $this->mockRegisteredAuthority( $denyRestrictedPage )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '{"subject":null}', $response->getBody()->getContents() );
	}

}
