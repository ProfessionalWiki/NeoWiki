<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;

/**
 * Needs the database for the pages the Subjects are on.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectSummariesApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetRecentlyEditedSubjectsApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetReferencingSubjectsApi
 * @group Database
 */
class DependentSubjectRestReadsTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const string PERSON = 'sDSRRPerson1111';
	private const string BIRTH = 'sDSRRBirth11111';
	private const string PLACE = 'sDSRRSpain11111';

	private const array PICASSO = [
		'id' => self::PERSON,
		'displayName' => 'Pablo Picasso',
		'displayNameIsGenerated' => false,
		'schema' => 'Person',
	];

	private RevisionRecord $firstRevision;

	public function setUp(): void {
		$this->createSchema( 'Person', '{"propertyDefinitions":{"Birth":{"type":"relation","targetSchemas":["Birth"]}}}' );
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{'
			. '"Place":{"type":"relation","targetSchemas":["Place"]}}}' );
		$this->createSchema( 'Place', '{"propertyDefinitions":{}}' );

		$this->createPageWithSubjects(
			'Málaga',
			mainSubject: TestSubject::build( id: self::PLACE, label: 'Málaga', schemaName: TestSchema::reference( 'Place' ) )
		);

		$this->firstRevision = $this->createPageWithSubjects( 'Pablo Picasso', $this->picasso( 'Pablo Picasso' ), $this->birth() );
	}

	private function picasso( string $label ): Subject {
		return TestSubject::build(
			id: self::PERSON,
			label: $label,
			schemaName: TestSchema::reference( 'Person' ),
			statements: new StatementList( [ $this->relation( 'Birth', self::BIRTH ) ] )
		);
	}

	private function birth(): SubjectMap {
		return new SubjectMap( TestSubject::build(
			id: self::BIRTH,
			label: null,
			schemaName: TestSchema::reference( 'Birth' ),
			statements: new StatementList( [ $this->relation( 'Place', self::PLACE ) ] )
		) );
	}

	private function renamePicassoAfterPublishingTheFirstRevision(): void {
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $this->firstRevision ) );
		$this->changeSubjectsOfPage( 'Pablo Picasso', $this->picasso( 'Picasso' ), $this->birth() );
	}

	public function testTheSubjectReadNamesADependentSubjectAfterItsHostSubject(): void {
		$body = $this->get( new GetSubjectApi(), pathParams: [ 'subjectId' => self::BIRTH ] );

		$this->assertSame( self::PICASSO, $body['subjects'][self::BIRTH]['hostSubject'] );
	}

	public function testTheSubjectReadEmbedsWhatTheDependentSubjectsOfTheSubjectPointAt(): void {
		$body = $this->get(
			new GetSubjectApi(),
			pathParams: [ 'subjectId' => self::PERSON ],
			queryParams: [ 'expand' => 'relations' ]
		);

		$this->assertSame( [ self::PERSON, self::BIRTH, self::PLACE ], array_keys( $body['subjects'] ) );
	}

	public function testThePageSubjectsReadNamesADependentSubjectAfterItsHostSubject(): void {
		$body = $this->get( new GetPageSubjectsApi(), pathParams: [ 'pageId' => (string)$this->firstRevision->getPageId() ] );

		$this->assertSame( self::PICASSO, $body['subjects'][self::BIRTH]['hostSubject'] );
	}

	public function testTheSubjectListOfASchemaOfDependentSubjectsNamesThemAfterTheirHostSubjects(): void {
		$body = $this->get( NeoWikiExtension::newGetSubjectSummariesApi(), queryParams: [ 'schema' => 'Birth' ] );

		$this->assertSame( self::BIRTH, $body['subjects'][0]['id'] );
		$this->assertSame( self::PICASSO, $body['subjects'][0]['hostSubject'] );
	}

	public function testTheRecentlyEditedSubjectsNameADependentSubjectAfterItsHostSubject(): void {
		$body = $this->get( NeoWikiExtension::newGetRecentlyEditedSubjectsApi() );

		$this->assertSame(
			self::PICASSO,
			array_column( $body['subjects'], null, 'id' )[self::BIRTH]['hostSubject']
		);
	}

	public function testTheSubjectReadOfAnEarlierRevisionNamesTheHostSubjectAsThatRevisionDoes(): void {
		$this->changeSubjectsOfPage( 'Pablo Picasso', $this->picasso( 'Picasso' ), $this->birth() );

		$body = $this->get(
			new GetSubjectApi(),
			pathParams: [ 'subjectId' => self::BIRTH ],
			queryParams: [ 'revisionId' => (string)$this->firstRevision->getId() ]
		);

		$this->assertSame( self::PICASSO, $body['subjects'][self::BIRTH]['hostSubject'] );
	}

	public function testTheSubjectReadNamesTheHostSubjectAsThePublishedRevisionDoes(): void {
		$this->renamePicassoAfterPublishingTheFirstRevision();

		$body = $this->get( new GetSubjectApi(), pathParams: [ 'subjectId' => self::BIRTH ] );

		$this->assertSame( self::PICASSO, $body['subjects'][self::BIRTH]['hostSubject'] );
	}

	public function testTheSubjectListNamesTheHostSubjectAsThePublishedRevisionDoes(): void {
		$this->renamePicassoAfterPublishingTheFirstRevision();

		$body = $this->get( NeoWikiExtension::newGetSubjectSummariesApi(), queryParams: [ 'schema' => 'Birth' ] );

		$this->assertSame( self::PICASSO, $body['subjects'][0]['hostSubject'] );
	}

	public function testTheReferencingSubjectsNameTheHostSubjectAsThePublishedRevisionDoes(): void {
		$this->renamePicassoAfterPublishingTheFirstRevision();

		$body = $this->get( NeoWikiExtension::newGetReferencingSubjectsApi(), pathParams: [ 'subjectId' => self::PLACE ] );

		$this->assertSame( self::PICASSO, $body['referencingSubjects'][0]['subject']['hostSubject'] );
	}

	public function testTheRecentlyEditedSubjectsAnswerForAPageHoldingAChainOfHostSubjectsLongerThanJsonNests(): void {
		$this->createSchema( 'Record', '{"subjectsAreDependent":true,"propertyDefinitions":{'
			. '"Record":{"type":"relation","targetSchemas":["Record"]}}}' );
		$this->createPageWithSubjects( 'Records', otherSubjects: new SubjectMap( ...$this->chainOfRecords( 520 ) ) );

		$response = $this->executeHandler(
			NeoWikiExtension::newGetRecentlyEditedSubjectsApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * @return Subject[] Records each pointed at by the next.
	 */
	private function chainOfRecords( int $length ): array {
		$ids = array_map(
			static fn ( int $index ): string => 'sRecordChain'
				. strtr( str_pad( base_convert( (string)$index, 10, 9 ), 3, '0', STR_PAD_LEFT ), '012345678', '123456789' ),
			range( 0, $length - 1 )
		);

		return array_map(
			fn ( int $index ): Subject => TestSubject::build(
				id: $ids[$index],
				label: null,
				schemaName: TestSchema::reference( 'Record' ),
				statements: new StatementList( $index === 0 ? [] : [ $this->relation( 'Record', $ids[$index - 1] ) ] )
			),
			range( 0, $length - 1 )
		);
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

	/**
	 * @param array<string, string> $pathParams
	 * @param array<string, string> $queryParams
	 * @return array<string, mixed>
	 */
	private function get( Handler $handler, array $pathParams = [], array $queryParams = [] ): array {
		return json_decode( $this->executeHandler(
			$handler,
			new RequestData( [ 'method' => 'GET', 'pathParams' => $pathParams, 'queryParams' => $queryParams ] )
		)->getBody()->getContents(), true );
	}

}
