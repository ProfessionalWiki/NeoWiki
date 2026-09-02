<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectContentRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectResolver
 */
class SubjectResolverTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';
	private const int TARGET_PAGE_ID = 42;
	private const string TARGET_PAGE_NAME = 'Marie Curie';

	private function createSubject( string $id = self::SUBJECT_ID, string $label = 'Test Subject' ): Subject {
		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $label ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList(),
		);
	}

	private function repositoryWithMainSubject( Subject $subject ): InMemorySubjectContentRepository {
		return new InMemorySubjectContentRepository( new PageSubjects( $subject, new SubjectMap() ) );
	}

	private function newResolver(
		SubjectContentRepository $contentRepository,
		?PageIdentifiersLookup $pageIdentifiersLookup = null,
		?PageReadAuthorizer $readAuthorizer = null
	): SubjectResolver {
		return new SubjectResolver(
			$contentRepository,
			$pageIdentifiersLookup ?? new InMemoryPageIdentifiersLookup(),
			$readAuthorizer ?? new StubPageReadAuthorizer( true )
		);
	}

	/**
	 * The production lookups find a Subject through the page hosting it, so a Subject that exists
	 * is one the page index maps to a page.
	 */
	private function hostedOnTargetPage( string $subjectId ): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( $subjectId ), $this->newTargetPageIdentifiers() ],
		] );
	}

	private function repositoryHostingOnTargetPage( PageSubjects $pageSubjects ): InMemorySubjectContentRepository {
		$repository = new InMemorySubjectContentRepository();
		$repository->setContentForPageId( self::TARGET_PAGE_ID, $pageSubjects );

		return $repository;
	}

	public function testResolveByIdReturnsSubject(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( null, new SubjectMap( $subject ) ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID )
		);

		$this->assertSame( $subject, $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenTheHostingPageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID ),
			new SelectivePageReadAuthorizer( [ self::TARGET_PAGE_ID ] )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenNoPageHostsTheSubject(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenTheHostingPageNoLongerHoldsTheSubject(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createTargetSubject( 'Someone else' ), new SubjectMap() ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullForInvalidId(): void {
		$resolver = $this->newResolver( new InMemorySubjectContentRepository() );

		$this->assertNull( $resolver->resolveById( 'invalid' ) );
	}

	public function testResolveByIdReturnsNullWhenThePageLookupThrows(): void {
		$pageIdentifiersLookup = $this->createStub( PageIdentifiersLookup::class );
		$pageIdentifiersLookup->method( 'getPageIdOfSubject' )
			->willThrowException( new RuntimeException( 'db error' ) );

		$resolver = $this->newResolver( new InMemorySubjectContentRepository(), $pageIdentifiersLookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveMainByTitleReturnsNullWhenThePageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $this->createSubject() ),
			readAuthorizer: new StubPageReadAuthorizer( false )
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenThePageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $this->createSubject() ),
			readAuthorizer: new StubPageReadAuthorizer( false )
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsMainSubject(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
		);

		$this->assertSame( $subject, $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsPageSubjects(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
		);

		$pageSubjects = $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) );

		$this->assertNotNull( $pageSubjects );
		$this->assertSame( $subject, $pageSubjects->getMainSubject() );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	private function newResolverWithTargetOnPage(
		PageSubjects $hostingPageSubjects,
		?PageReadAuthorizer $readAuthorizer = null
	): SubjectResolver {
		return $this->newResolver(
			$this->repositoryHostingOnTargetPage( $hostingPageSubjects ),
			$this->hostedOnTargetPage( self::TARGET_SUBJECT_ID ),
			$readAuthorizer
		);
	}

	private function newTargetPageIdentifiers(): PageIdentifiers {
		return new PageIdentifiers(
			id: new PageId( self::TARGET_PAGE_ID ),
			title: self::TARGET_PAGE_NAME,
			namespaceId: 0,
		);
	}

	private function createTargetSubject( ?string $label ): Subject {
		return new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: $label === null ? null : new SubjectLabel( $label ),
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList(),
		);
	}

	private function newRelationToTarget(): Relation {
		return new Relation(
			id: new RelationId( 'r1test5cccccccc' ),
			targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
			properties: new RelationProperties( [] ),
		);
	}

	public function testResolveRelationLabelReturnsLabelWhenTargetExists(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( null, new SubjectMap( $this->createTargetSubject( 'Jane Doe' ) ) )
		);

		$this->assertSame( 'Jane Doe', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelPrefersAStoredLabelOverThePageNameOfAMainSubjectTarget(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( 'Jane Doe' ), new SubjectMap() )
		);

		$this->assertSame( 'Jane Doe', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToThePageNameWhenTheTargetIsALabellessMainSubject(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( null ), new SubjectMap() )
		);

		$this->assertSame( self::TARGET_PAGE_NAME, $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToTheSchemaNameWhenTheTargetIsALabellessChildSubject(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects(
				$this->createSubject( self::SUBJECT_ID, 'The Page Topic' ),
				new SubjectMap( $this->createTargetSubject( null ) )
			)
		);

		$this->assertSame( 'Person', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToIdWhenTheHostingPageIsNotReadable(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( 'Jane Doe' ), new SubjectMap() ),
			new SelectivePageReadAuthorizer( [ self::TARGET_PAGE_ID ] )
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenTargetNotFound(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenTheHostingPageNoLongerHoldsTheTarget(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createSubject( self::SUBJECT_ID, 'The Page Topic' ), new SubjectMap() )
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenLookupThrows(): void {
		$pageIdentifiersLookup = $this->createStub( PageIdentifiersLookup::class );
		$pageIdentifiersLookup->method( 'getPageIdOfSubject' )
			->willThrowException( new RuntimeException( 'db error' ) );

		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$pageIdentifiersLookup
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

}
