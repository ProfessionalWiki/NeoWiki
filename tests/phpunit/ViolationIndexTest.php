<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use JobSpecification;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckSchemaViolationsJob;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * The Violations of a page's Subjects are recorded after the page changes, by the jobs its change queues.
 *
 * Needs the integration base and the database for the pages it saves, the jobs they queue and the table
 * the jobs write.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\ViolationCheckingPublishedSubjectIndex
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckPageViolationsJob
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\MediaWikiViolationCheckQueue
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckSchemaViolationsJob
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\CheckReferringPagesViolationsJob
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 * @group Database
 */
class ViolationIndexTest extends NeoWikiIntegrationTestCase {

	private const string PAGE = 'ViolationIndexTestPage';
	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string ACME = 's1cacme11111111';

	private const string NAME_REQUIRED = '{"propertyDefinitions":{"Name":{"type":"text","required":true}}}';
	private const string NAME_OPTIONAL = '{"propertyDefinitions":{"Name":{"type":"text"}}}';
	private const string EMPLOYED = '{"propertyDefinitions":{"Name":{"type":"text","required":true},'
		. '"Employer":{"type":"relation","targetSchemas":["Company"]}}}';

	protected function setUp(): void {
		parent::setUp();

		$this->createSchema( 'Person', self::NAME_REQUIRED );
	}

	public function testSavingAPageRecordsTheViolationsOfItsSubjects(): void {
		$pageId = $this->savePageWith( $this->ada( new StatementList() ) );

		$this->assertSame( [ 'required' ], $this->violationCodesOf( $pageId ) );
	}

	public function testFixingTheSubjectsOfAPageClearsTheirViolations(): void {
		$this->savePageWith( $this->ada( new StatementList() ) );

		$pageId = $this->savePageWith( $this->namedAda() );

		$this->assertSame( [], $this->violationCodesOf( $pageId ) );
	}

	public function testDeletingAPageClearsTheViolationsOfItsSubjects(): void {
		$pageId = $this->savePageWith( $this->ada( new StatementList() ) );

		$this->deletePageByName( self::PAGE );
		$this->runPendingUpdates();

		$this->assertSame( [], $this->violationCodesOf( $pageId ) );
	}

	public function testEditingASchemaChecksItsSubjectsAgainstTheNewVersion(): void {
		$this->createSchema( 'Person', self::NAME_OPTIONAL );
		$pageId = $this->savePageWith( $this->ada( new StatementList() ) );

		$this->createSchema( 'Person', self::NAME_REQUIRED );
		$this->runPendingUpdates();

		$this->assertSame( [ 'required' ], $this->violationCodesOf( $pageId ) );
	}

	public function testDeletingASchemaChecksItsSubjectsAgain(): void {
		$pageId = $this->savePageWith( $this->namedAda() );

		$this->deletePageByName( 'Schema:Person' );
		$this->runPendingUpdates();

		$this->assertSame( [ 'schema-not-found' ], $this->violationCodesOf( $pageId ) );
	}

	public function testRestoringASchemaChecksItsSubjectsAgain(): void {
		$pageId = $this->savePageWith( $this->namedAda() );
		$this->deletePageByName( 'Schema:Person' );
		$this->runPendingUpdates();

		$this->undeleteArchivedRevisions( 'Schema:Person' );
		$this->runPendingUpdates();

		$this->assertSame( [], $this->violationCodesOf( $pageId ) );
	}

	public function testCreatingTheTargetOfARelationChecksTheSubjectRelatingToItAgain(): void {
		$this->createSchema( 'Company', '{"propertyDefinitions":{}}' );
		$this->createSchema( 'Person', self::EMPLOYED );
		$pageId = $this->savePageWith( $this->ada( $this->employedBy( self::ACME ) ) );

		$this->savePageWith( TestSubject::build( id: self::ACME, schemaName: new SchemaName( 'Company' ) ), 'ViolationIndexTestCompany' );

		$this->assertSame( [], $this->violationCodesOf( $pageId ) );
	}

	public function testDeletingTheTargetOfARelationChecksTheSubjectRelatingToItAgain(): void {
		$this->createSchema( 'Company', '{"propertyDefinitions":{}}' );
		$this->createSchema( 'Person', self::EMPLOYED );
		$this->savePageWith( TestSubject::build( id: self::ACME, schemaName: new SchemaName( 'Company' ) ), 'ViolationIndexTestCompany' );
		$pageId = $this->savePageWith( $this->ada( $this->employedBy( self::ACME ) ) );

		$this->deletePageByName( 'ViolationIndexTestCompany' );
		$this->runPendingUpdates();

		$this->assertSame( [ 'relation-target-not-found' ], $this->violationCodesOf( $pageId ) );
	}

	private function employedBy( string $targetId ): StatementList {
		return new StatementList( [
			TestStatement::build( 'Name', 'Ada' ),
			TestStatement::buildRelation( 'Employer', [ TestRelation::build( targetId: $targetId ) ] ),
		] );
	}

	public function testCheckingASchemaReachesSubjectsBeyondItsFirstBatch(): void {
		$adaPageId = $this->savePageWith( $this->ada( new StatementList() ) );
		$gracePageId = $this->savePageWith(
			TestSubject::build( id: self::GRACE, schemaName: new SchemaName( 'Person' ) ),
			'ViolationIndexTestOtherPage'
		);
		$this->truncateTable( DatabaseViolationIndex::TABLE );

		$this->getServiceContainer()->getJobQueueGroup()->push(
			new JobSpecification( CheckSchemaViolationsJob::TYPE, [ 'schemaName' => 'Person', 'batchSize' => 1 ] )
		);
		$this->runPendingUpdates();

		$this->assertSame(
			[ [ 'required' ], [ 'required' ] ],
			[ $this->violationCodesOf( $adaPageId ), $this->violationCodesOf( $gracePageId ) ]
		);
	}

	private function namedAda(): Subject {
		return $this->ada( new StatementList( [ TestStatement::build( 'Name', 'Ada' ) ] ) );
	}

	private function ada( StatementList $statements ): Subject {
		return TestSubject::build( id: self::ADA, schemaName: new SchemaName( 'Person' ), statements: $statements );
	}

	private function savePageWith( Subject $mainSubject, string $pageName = self::PAGE ): int {
		$revision = $this->createPageWithSubjects( $pageName, $mainSubject );
		$this->assertNotNull( $revision );
		$this->runPendingUpdates();

		return $revision->getPageId();
	}

	private function runPendingUpdates(): void {
		DeferredUpdates::doUpdates();
		$this->runJobs( [ 'minJobs' => 0 ] );
	}

	/**
	 * @return string[]
	 */
	private function violationCodesOf( int $pageId ): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'nwv_code' )
			->from( DatabaseViolationIndex::TABLE )
			->where( [ 'nwv_page_id' => $pageId ] )
			->orderBy( 'nwv_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

}
