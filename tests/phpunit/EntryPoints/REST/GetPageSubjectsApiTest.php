<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySource;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetPageSubjectsPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\LatestSubjectLookup
 * @group Database
 */
class GetPageSubjectsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

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

	public function testReturnsMainAndOtherSubjects(): void {
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
			otherSubjects: new SubjectMap(
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
			[ 'propertyType' => 'text', 'value' => [ '3700000' ] ],
			$body['subjects']['sTestGPS1111111']['statements']['population']
		);
		$this->assertSame( 'Population 2024', $body['subjects']['sTestGPS1111112']['label'] );
		$this->assertSame( 'Berlin', $body['subjects']['sTestGPS1111111']['displayName'] );
	}

	public function testLabellessSubjectsAreNamedAfterThePageAndTheSchema(): void {
		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_Unnamed',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111131',
				label: null,
				schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' )
			),
			otherSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGPS1111132',
					label: null,
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

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertNull( $body['subjects']['sTestGPS1111131']['label'] );
		$this->assertSame(
			'GetPageSubjectsApiTest Unnamed',
			$body['subjects']['sTestGPS1111131']['displayName']
		);

		$this->assertNull( $body['subjects']['sTestGPS1111132']['label'] );
		$this->assertSame(
			'GetPageSubjectsApiTestSchema',
			$body['subjects']['sTestGPS1111132']['displayName']
		);
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
			"targetSchemas": [ "GetPageSubjectsApiTestSchema" ]
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
						TestRelation::build(
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

	/**
	 * A relation target from another Source has no page of this wiki to authorize against, and its
	 * Source vouches for it (ADR 23), so the expansion serves it rather than dropping it.
	 */
	public function testReferencedSubjectFromAnotherSourceIsIncluded(): void {
		$this->createSchema(
			'GetPageSubjectsApiTestSourcedRelationSchema',
			<<<JSON
{
	"title": "GetPageSubjectsApiTestSourcedRelationSchema",
	"propertyDefinitions": {
		"partner": {
			"type": "relation",
			"targetSchemas": [ "GetPageSubjectsApiTestSchema" ]
		}
	}
}
JSON
		);

		$this->registerSources( [
			'catalog' => new InMemorySource(
				TestSubject::build(
					id: 'catalog:widget-7',
					label: new SubjectLabel( 'Widget 7' ),
					schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' )
				)
			),
		] );

		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_WithSourcedRelation',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111aa3',
				label: new SubjectLabel( 'Source' ),
				schemaName: new SchemaName( 'GetPageSubjectsApiTestSourcedRelationSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'partner', [
						TestRelation::build( id: 'rTestGPS1111aa3', targetId: 'catalog:widget-7' ),
					] ),
				] )
			),
		);

		$response = $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$revision->getPage()->getId() ],
				'queryParams' => [ 'expand' => 'relations' ],
			] )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertArrayHasKey( 'catalog:widget-7', $body['referencedSubjects'] );
		$this->assertSame( 'Widget 7', $body['referencedSubjects']['catalog:widget-7']['label'] );
	}

	public function testReturnsOtherSubjectsWhenMainSubjectIsAbsent(): void {
		$revision = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_NoMain',
			mainSubject: null,
			otherSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGPS1111121',
					label: new SubjectLabel( 'orphan' ),
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

	public function testSubjectsOnAnUnreadablePageAreAbsent(): void {
		$restrictedPageId = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_Restricted',
			mainSubject: TestSubject::build( id: 'sTestGPS1111141' )
		)->getPage()->getId();

		$emptyPageId = $this->insertPage(
			'GetPageSubjectsApiTest_NoSubjects',
			'Just wikitext, no NeoWiki subjects.'
		)['id'];

		$denied = json_decode( $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$restrictedPageId ],
				'queryParams' => [ 'expand' => 'schemas|relations' ],
			] ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		)->getBody()->getContents(), true );

		$empty = json_decode( $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$emptyPageId ],
				'queryParams' => [ 'expand' => 'schemas|relations' ],
			] )
		)->getBody()->getContents(), true );

		// Only the echoed page id may differ between "denied" and "has no Subjects".
		$empty['pageId'] = $restrictedPageId;
		$this->assertSame( $empty, $denied );
	}

	public function testReferencedSubjectOnAnUnreadablePageIsOmitted(): void {
		$this->createPageWithSubjects(
			'GetPageSubjectsApiTest_RestrictedTarget',
			mainSubject: TestSubject::build(
				id: 'sTestGPS1111151',
				schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' ),
			)
		);

		$sourcePageId = $this->createSourceReferencing( 'sTestGPS1111152', 'sTestGPS1111151' );

		$denyTargetPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GetPageSubjectsApiTest_RestrictedTarget';

		$body = json_decode( $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$sourcePageId ],
				'queryParams' => [ 'expand' => 'relations' ],
			] ),
			authority: $this->mockRegisteredAuthority( $denyTargetPage )
		)->getBody()->getContents(), true );

		$this->assertArrayHasKey( 'sTestGPS1111152', $body['subjects'] );
		$this->assertSame( [], $body['referencedSubjects'] );
	}

	public function testServesTheRevisionThePagePublishes(): void {
		$published = $this->createPageWithADraft();
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $published ) );

		$body = $this->requestPageSubjects( $published->getPageId(), [] );

		$this->assertSame( [ 'sTestGPS1111161' ], array_keys( $body['subjects'] ) );
		$this->assertSame( 'published label', $body['subjects']['sTestGPS1111161']['label'] );
	}

	public function testLatestServesTheCurrentRevisionToAViewerThePolicyLetsSeeIt(): void {
		$published = $this->createPageWithADraft();
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $published ) );

		$body = $this->requestPageSubjects( $published->getPageId(), [ 'latest' => '1' ] );

		$this->assertSame( [ 'sTestGPS1111161', 'sTestGPS1111162' ], array_keys( $body['subjects'] ) );
		$this->assertSame( 'draft label', $body['subjects']['sTestGPS1111161']['label'] );
	}

	public function testLatestRefusedByThePolicyIsIndistinguishableFromAPageWithoutSubjects(): void {
		$hiddenPageId = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_LatestHidden',
			mainSubject: $this->newSubject( 'sTestGPS1111191', 'draft label' )
		)->getPageId();

		$emptyPageId = $this->insertPage(
			'GetPageSubjectsApiTest_LatestNoSubjects',
			'Just wikitext, no NeoWiki subjects.'
		)['id'];

		$this->registerRevisionPolicy( FixedRevisionPolicy::hidingEveryRevision() );

		$query = [ 'latest' => '1', 'expand' => 'schemas|relations' ];
		$hidden = $this->requestPageSubjects( $hiddenPageId, $query );
		$empty = $this->requestPageSubjects( $emptyPageId, $query );

		// Only the echoed page id may differ between "hidden" and "has no Subjects".
		$empty['pageId'] = $hiddenPageId;
		$this->assertSame( $empty, $hidden );
	}

	public function testReferencedSubjectComesFromItsOwnPagesPublishedRevision(): void {
		[ $sourcePageId, $publishedTarget ] = $this->createSourceReferencingATargetWithADraft();
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $publishedTarget ) );

		$body = $this->requestPageSubjects( $sourcePageId, [ 'expand' => 'relations' ] );

		$this->assertSame( 'published target', $body['referencedSubjects']['sTestGPS11111b1']['label'] );
	}

	public function testReferencedSubjectIsNamedAsItsPagesPublishedRevisionNamesIt(): void {
		$publishedTarget = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_NamedTarget',
			mainSubject: $this->newSubject( 'sTestGPS11111c1', null )
		);

		$this->createPageWithSubjects(
			'GetPageSubjectsApiTest_NamedTarget',
			mainSubject: $this->newSubject( 'sTestGPS11111c2', 'main Subject of the draft' ),
			otherSubjects: new SubjectMap( $this->newSubject( 'sTestGPS11111c1', null ) )
		);

		$sourcePageId = $this->createSourceReferencing( 'sTestGPS11111c3', 'sTestGPS11111c1' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $publishedTarget ) );

		$body = $this->requestPageSubjects( $sourcePageId, [ 'expand' => 'relations' ] );

		$this->assertSame(
			'GetPageSubjectsApiTest NamedTarget',
			$body['referencedSubjects']['sTestGPS11111c1']['displayName']
		);
	}

	public function testLatestTakesReferencedSubjectsFromTheirPagesLatestRevisions(): void {
		[ $sourcePageId, $publishedTarget ] = $this->createSourceReferencingATargetWithADraft();
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $publishedTarget ) );

		$body = $this->requestPageSubjects( $sourcePageId, [ 'latest' => '1', 'expand' => 'relations' ] );

		$this->assertSame( 'draft target', $body['referencedSubjects']['sTestGPS11111b1']['label'] );
	}

	public function testLatestOmitsAReferencedSubjectWhosePageThePolicyRefuses(): void {
		[ $sourcePageId, $publishedTarget ] = $this->createSourceReferencingATargetWithADraft();

		$this->registerRevisionPolicy( new class( $publishedTarget->getPageId() ) extends NullRevisionPolicy {

			public function __construct( private readonly int $refusedPageId ) {
			}

			public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
				return $revision->getPageId() !== $this->refusedPageId;
			}

		} );

		$body = $this->requestPageSubjects( $sourcePageId, [ 'latest' => '1', 'expand' => 'relations' ] );

		$this->assertArrayHasKey( 'sTestGPS11111b2', $body['subjects'] );
		$this->assertArrayNotHasKey( 'sTestGPS11111b1', $body['referencedSubjects'] );
	}

	/**
	 * The page's revision before its draft, which relabels its Subject and adds another.
	 */
	private function createPageWithADraft(): RevisionRecord {
		$published = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_WithADraft',
			mainSubject: $this->newSubject( 'sTestGPS1111161', 'published label' )
		);

		$this->createPageWithSubjects(
			'GetPageSubjectsApiTest_WithADraft',
			mainSubject: $this->newSubject( 'sTestGPS1111161', 'draft label' ),
			otherSubjects: new SubjectMap( $this->newSubject( 'sTestGPS1111162', 'only in the draft' ) )
		);

		return $published;
	}

	/**
	 * @return array{int, RevisionRecord} The source page's id, and the target's revision before its draft
	 */
	private function createSourceReferencingATargetWithADraft(): array {
		$publishedTarget = $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_PublishedTarget',
			mainSubject: $this->newSubject( 'sTestGPS11111b1', 'published target' )
		);

		$this->createPageWithSubjects(
			'GetPageSubjectsApiTest_PublishedTarget',
			mainSubject: $this->newSubject( 'sTestGPS11111b1', 'draft target' )
		);

		return [ $this->createSourceReferencing( 'sTestGPS11111b2', 'sTestGPS11111b1' ), $publishedTarget ];
	}

	private function createSourceReferencing( string $sourceId, string $targetId ): int {
		$this->createSchema(
			'GetPageSubjectsApiTestRelationSchema3',
			<<<JSON
{
	"title": "GetPageSubjectsApiTestRelationSchema3",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"targetSchemas": [ "GetPageSubjectsApiTestSchema" ]
		}
	}
}
JSON
		);

		return $this->createPageWithSubjects(
			'GetPageSubjectsApiTest_Source_' . $sourceId,
			mainSubject: TestSubject::build(
				id: $sourceId,
				schemaName: new SchemaName( 'GetPageSubjectsApiTestRelationSchema3' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'MyRelation', [
						TestRelation::build( targetId: $targetId ),
					] ),
				] )
			)
		)->getPageId();
	}

	private function newSubject( string $id, ?string $label ): Subject {
		return TestSubject::build(
			id: $id,
			label: $label === null ? null : new SubjectLabel( $label ),
			schemaName: new SchemaName( 'GetPageSubjectsApiTestSchema' )
		);
	}

	/**
	 * @param array<string, string> $queryParams
	 */
	private function requestPageSubjects( int $pageId, array $queryParams ): array {
		return json_decode( $this->executeHandler(
			new GetPageSubjectsApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$pageId ],
				'queryParams' => $queryParams,
			] )
		)->getBody()->getContents(), true );
	}

}
