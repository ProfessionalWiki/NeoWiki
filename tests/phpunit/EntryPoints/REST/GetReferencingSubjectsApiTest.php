<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use Laudis\Neo4j\Contracts\ClientInterface;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetReferencingSubjectsApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetReferencingSubjectsApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsQuery
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetReferencingSubjectsPresenter
 * @group Database
 */
class GetReferencingSubjectsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SCHEMA = 'GetReferencingSubjectsTestSchema';
	private const string TARGET_ID = 'sTestGRS1111111';

	private int $targetPageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			self::SCHEMA,
			<<<JSON
{
	"title": "GetReferencingSubjectsTestSchema",
	"propertyDefinitions": {
		"Made in": {
			"type": "relation",
			"relation": "Made in",
			"targetSchema": "GetReferencingSubjectsTestSchema"
		},
		"Sold in": {
			"type": "relation",
			"relation": "Sold in",
			"targetSchema": "GetReferencingSubjectsTestSchema"
		}
	}
}
JSON
		);

		$this->targetPageId = $this->createTargetPage()->getPageId();
	}

	public function testNoSubjectReferencesTheTarget(): void {
		$this->assertSame(
			[ 'referencingSubjects' => [], 'truncated' => false ],
			$this->requestReferencingSubjects()
		);
	}

	public function testNamesTheReferringSubjectAndThePropertyPointingHere(): void {
		$pageId = $this->createReferringPage( 'GRSApiTest_Anvil', 'sTestGRS1111112', 'Anvil' )
			->getPage()->getId();

		$body = $this->requestReferencingSubjects();
		$subject = $body['referencingSubjects'][0]['subject'];
		unset( $subject['statements'] );

		$this->assertSame( [ 'Made in' ], $body['referencingSubjects'][0]['propertyNames'] );
		$this->assertSame(
			[
				'id' => 'sTestGRS1111112',
				'label' => 'Anvil',
				'displayName' => 'Anvil',
				'displayNameIsGenerated' => false,
				'schema' => self::SCHEMA,
				'pageId' => $pageId,
				'pageTitle' => 'GRSApiTest Anvil',
				'pageNamespaceId' => 0,
			],
			$subject
		);
	}

	public function testListsEveryPropertyThroughWhichASubjectPointsHere(): void {
		$this->createPageWithSubjects(
			'GRSApiTest_TwoWays',
			mainSubject: $this->referrer( 'sTestGRS1111113', 'Two ways', [ 'Made in', 'Sold in' ] )
		);

		$this->assertSame(
			[ 'Made in', 'Sold in' ],
			$this->requestReferencingSubjects()['referencingSubjects'][0]['propertyNames']
		);
	}

	/**
	 * The names come from the Statements that point here, so a relation the same Subject holds to a
	 * third one names nothing in this row.
	 */
	public function testOmitsAPropertyPointingAtAnotherSubject(): void {
		$this->createPageWithSubjects(
			'GRSApiTest_Elsewhere',
			mainSubject: TestSubject::build(
				id: 'sTestGRS1111126',
				label: new SubjectLabel( 'Elsewhere' ),
				schemaName: new SchemaName( self::SCHEMA ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'Made in', [
						TestRelation::build( targetId: self::TARGET_ID ),
					] ),
					TestStatement::buildRelation( 'Sold in', [
						TestRelation::build( targetId: 'sTestGRS1111127' ),
					] ),
				] )
			)
		);

		$this->assertSame(
			[ 'Made in' ],
			$this->requestReferencingSubjects()['referencingSubjects'][0]['propertyNames']
		);
	}

	/**
	 * By name, which here is neither the order they were created in nor the order of their ids, so
	 * returning the rows as the wiki stored them would not pass.
	 */
	public function testListsEveryReferringSubjectInTheOrderTheWikiNamesThem(): void {
		$this->createReferringPage( 'GRSApiTest_First', 'sTestGRS1111114', 'Zeppelin' );
		$this->createReferringPage( 'GRSApiTest_Second', 'sTestGRS1111115', 'Anvil' );

		$this->assertSame(
			[ 'sTestGRS1111115', 'sTestGRS1111114' ],
			$this->idsOfReferencingSubjects( $this->requestReferencingSubjects() )
		);
	}

	/**
	 * Rows are named the way every other Subject read names them, so a referrer nobody labelled is
	 * shown under the page it is the Main Subject of rather than under its Schema.
	 */
	public function testNamesALabellessReferrerAfterItsPage(): void {
		$this->createPageWithSubjects(
			'GRSApiTest_Unnamed',
			mainSubject: TestSubject::build(
				id: 'sTestGRS1111119',
				label: null,
				schemaName: new SchemaName( self::SCHEMA ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'Made in', [
						TestRelation::build( targetId: self::TARGET_ID ),
					] ),
				] )
			)
		);

		$subject = $this->requestReferencingSubjects()['referencingSubjects'][0]['subject'];

		$this->assertNull( $subject['label'] );
		$this->assertSame( 'GRSApiTest Unnamed', $subject['displayName'] );
	}

	/**
	 * The graph keeps the edge of a relation renamed in the Schema (#1135), and holds edges a page
	 * whose projection failed no longer matches. Statements decide, so neither can be shown.
	 */
	public function testSubjectWhoseStatementsNoLongerPointHereIsOmitted(): void {
		$this->createPageWithSubjects(
			'GRSApiTest_Stale',
			mainSubject: TestSubject::build(
				id: 'sTestGRS1111116',
				label: new SubjectLabel( 'Stale' ),
				schemaName: new SchemaName( self::SCHEMA )
			)
		);

		$this->plantRawEdge( 'sTestGRS1111116' );

		$this->assertSame( [], $this->requestReferencingSubjects()['referencingSubjects'] );
	}

	public function testReferringSubjectOnAnUnreadablePageIsOmitted(): void {
		$this->createReferringPage( 'GRSApiTest_Hidden', 'sTestGRS1111117', 'Hidden' );

		$this->assertSame(
			[ 'referencingSubjects' => [], 'truncated' => false ],
			$this->requestReferencingSubjects( authority: $this->authorityDenying( 'GRSApiTest_Hidden' ) )
		);
	}

	/**
	 * Until the next rebuild the graph still holds the edges of a page that stopped publishing, so
	 * what drops the row is reading the referrer's Statements from what its page publishes.
	 */
	public function testReferringSubjectOnAPageThatPublishesNothingIsOmitted(): void {
		$pageId = $this->createReferringPage( 'GRSApiTest_Unpublished', 'sTestGRS1111125', 'Anvil' )->getPageId();

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothingFromPage( $pageId ) );

		$this->assertSame( [], $this->requestReferencingSubjects()['referencingSubjects'] );
	}

	/**
	 * The referrer's page stays readable, so only the gate on the target itself stands between this
	 * caller and the news that the Subject exists (ADR 27, #1046).
	 */
	public function testTargetOnAnUnreadablePageIsIndistinguishableFromAnAbsentOne(): void {
		$this->createReferringPage( 'GRSApiTest_Visible', 'sTestGRS1111118', 'Anvil' );

		$authority = $this->authorityDenying( 'GRSApiTest_Target' );

		$denied = $this->executeRequest( self::TARGET_ID, authority: $authority );
		$absent = $this->executeRequest( 'sTestGRS9999999', authority: $authority );

		$this->assertSame( 200, $denied->getStatusCode() );
		$this->assertSame( $absent->getBody()->getContents(), $denied->getBody()->getContents() );
	}

	/**
	 * The referring page still publishes, so the empty list is the target's own withheld revision
	 * speaking — the answer `GET /subject/{subjectId}` gives for it too.
	 */
	public function testTargetOnAPageThatPublishesNothingIsIndistinguishableFromAnAbsentOne(): void {
		$this->createReferringPage( 'GRSApiTest_Pointing', 'sTestGRS1111126', 'Anvil' );

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothingFromPage( $this->targetPageId ) );

		$withheld = $this->executeRequest( self::TARGET_ID );
		$absent = $this->executeRequest( 'sTestGRS9999999' );

		$this->assertSame( 200, $withheld->getStatusCode() );
		$this->assertSame( $absent->getBody()->getContents(), $withheld->getBody()->getContents() );
	}

	public function testReportsTruncationOnlyWhenMoreSubjectsAreLeftOut(): void {
		$this->createTwoReferringPages();

		$this->assertTrue( $this->requestReferencingSubjects( limit: 1 )['truncated'] );
		$this->assertFalse( $this->requestReferencingSubjects( limit: 2 )['truncated'] );
	}

	/**
	 * The flag says rows were left out; the limit is what leaves them out. One more row than asked
	 * for is collected to decide the flag, and the page renders every row it is served.
	 */
	public function testReturnsNoMoreSubjectsThanTheLimitAsksFor(): void {
		$this->createTwoReferringPages();

		$this->assertSame(
			[ 'sTestGRS1111121' ],
			$this->idsOfReferencingSubjects( $this->requestReferencingSubjects( limit: 1 ) )
		);
	}

	/**
	 * Truncation is decided after gating, so the flag describes what the caller could have seen
	 * rather than how many rows the graph held.
	 */
	public function testUnreadableSubjectsDoNotCountTowardsTruncation(): void {
		$this->createReferringPage( 'GRSApiTest_Shown', 'sTestGRS1111123', 'Anvil' );
		$this->createReferringPage( 'GRSApiTest_Unreadable', 'sTestGRS1111124', 'Bellows' );

		$body = $this->requestReferencingSubjects(
			limit: 1,
			authority: $this->authorityDenying( 'GRSApiTest_Unreadable' )
		);

		$this->assertSame( [ 'sTestGRS1111123' ], $this->idsOfReferencingSubjects( $body ) );
		$this->assertFalse( $body['truncated'] );
	}

	public function testSubjectOfAnotherSourceIsReferencedByNothing(): void {
		$this->assertSame(
			[ 'referencingSubjects' => [], 'truncated' => false ],
			$this->requestReferencingSubjects( subjectId: 'elsewhere:s1111111111111' )
		);
	}

	public function testMalformedSubjectIdIsRejected(): void {
		$this->assertSame( 400, $this->executeRequest( 'not-a-subject-id' )->getStatusCode() );
	}

	public function testTheMaximumLimitIsServed(): void {
		// The frontend asks for exactly this, so a maximum lowered below it would 400 every page view.
		$this->assertSame( 200, $this->executeRequest( self::TARGET_ID, limit: 50 )->getStatusCode() );
	}

	public function testLimitBeyondTheMaximumIsRejected(): void {
		// Every candidate costs a Subject read and a read-permission check, so an uncapped limit is
		// an unbounded-work vector. The cap is enforced before the handler runs any query (#1060).
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeRequest( self::TARGET_ID, limit: 51 );
	}

	private function createTargetPage(): RevisionRecord {
		return $this->createPageWithSubjects(
			'GRSApiTest_Target',
			mainSubject: TestSubject::build(
				id: self::TARGET_ID,
				label: new SubjectLabel( 'Target' ),
				schemaName: new SchemaName( self::SCHEMA )
			)
		);
	}

	private function createReferringPage( string $pageName, string $subjectId, string $label ): RevisionRecord {
		return $this->createPageWithSubjects(
			$pageName,
			mainSubject: $this->referrer( $subjectId, $label, [ 'Made in' ] )
		);
	}

	private function createTwoReferringPages(): void {
		$this->createReferringPage( 'GRSApiTest_One', 'sTestGRS1111121', 'Anvil' );
		$this->createReferringPage( 'GRSApiTest_Two', 'sTestGRS1111122', 'Bellows' );
	}

	private function authorityDenying( string $pageDbKey ): Authority {
		return $this->mockRegisteredAuthority(
			static fn ( string $permission, ?PageIdentity $page = null ): bool =>
				$page === null || $page->getDBkey() !== $pageDbKey
		);
	}

	/**
	 * @param string[] $properties
	 */
	private function referrer( string $subjectId, string $label, array $properties ): Subject {
		return TestSubject::build(
			id: $subjectId,
			label: new SubjectLabel( $label ),
			schemaName: new SchemaName( self::SCHEMA ),
			statements: new StatementList( array_map(
				static fn ( string $property ) => TestStatement::buildRelation( $property, [
					TestRelation::build( targetId: self::TARGET_ID ),
				] ),
				$properties
			) )
		);
	}

	/**
	 * An edge the graph holds while the Subject's Statements hold nothing of the kind: what a
	 * renamed relation type, and a projection that ran against older content, leave behind.
	 */
	private function plantRawEdge( string $sourceId ): void {
		$this->getClient()->run(
			'MATCH (source:Subject { id: $sourceId }), (target:Subject { id: $targetId })
			 CREATE (source)-[:Gone { id: "rTestGRS111111" }]->(target)',
			[ 'sourceId' => $sourceId, 'targetId' => self::TARGET_ID ]
		);
	}

	private function requestReferencingSubjects(
		string $subjectId = self::TARGET_ID,
		?int $limit = null,
		?Authority $authority = null
	): array {
		return json_decode(
			$this->executeRequest( $subjectId, $limit, $authority )->getBody()->getContents(),
			true
		);
	}

	private function executeRequest( string $subjectId, ?int $limit = null, ?Authority $authority = null ): Response {
		$request = [
			'method' => 'GET',
			'pathParams' => [ 'subjectId' => $subjectId ],
		];

		if ( $limit !== null ) {
			$request['queryParams'] = [ 'limit' => (string)$limit ];
		}

		return $this->executeHandler(
			new GetReferencingSubjectsApi(),
			new RequestData( $request ),
			authority: $authority
		);
	}

	/**
	 * @return string[]
	 */
	private function idsOfReferencingSubjects( array $body ): array {
		return array_map(
			static fn ( array $referencing ): string => $referencing['subject']['id'],
			$body['referencingSubjects']
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

}
