<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
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
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
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
			schema: SchemaReference::local( new SchemaName( 'TestSchema' ) ),
			statements: new StatementList(),
		);
	}

	private function repositoryWithMainSubject( Subject $subject ): InMemorySubjectContentRepository {
		return new InMemorySubjectContentRepository( new PageSubjects( $subject, new SubjectMap() ) );
	}

	private function newResolver(
		SubjectContentRepository $contentRepository,
		SubjectLookup $subjectLookup
	): SubjectResolver {
		return new SubjectResolver(
			$contentRepository,
			$subjectLookup,
			new InMemoryPageIdentifiersLookup(),
			TestSubjectIds::newParser()
		);
	}

	public function testResolveByIdReturnsSubject(): void {
		$subject = $this->createSubject();

		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( $subject );

		$resolver = $this->newResolver( new InMemorySubjectContentRepository(), $lookup );

		$this->assertSame( $subject, $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullForInvalidId(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->resolveById( 'invalid' ) );
	}

	public function testResolveByIdReturnsNullWhenLookupReturnsNull(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( null );

		$resolver = $this->newResolver( new InMemorySubjectContentRepository(), $lookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenLookupThrows(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willThrowException( new RuntimeException( 'db error' ) );

		$resolver = $this->newResolver( new InMemorySubjectContentRepository(), $lookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveMainByTitleReturnsMainSubject(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertSame( $subject, $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsPageSubjects(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
			$this->createStub( SubjectLookup::class )
		);

		$pageSubjects = $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) );

		$this->assertNotNull( $pageSubjects );
		$this->assertSame( $subject, $pageSubjects->getMainSubject() );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	/**
	 * The target Subject as the production lookups see one that exists: hosted by a page, whose
	 * content holds it either as the Main Subject or as a child.
	 */
	private function newResolverWithTargetOnPage( PageSubjects $hostingPageSubjects ): SubjectResolver {
		$contentRepository = new InMemorySubjectContentRepository();
		$contentRepository->setContentForPageId( self::TARGET_PAGE_ID, $hostingPageSubjects );

		return new SubjectResolver(
			$contentRepository,
			$this->createStub( SubjectLookup::class ),
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::TARGET_SUBJECT_ID ), $this->newTargetPageIdentifiers() ],
			] ),
			TestSubjectIds::newParser()
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
			schema: SchemaReference::local( new SchemaName( 'Person' ) ),
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

	public function testResolveRelationLabelFallsBackToIdWhenTargetNotFound(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$this->createStub( SubjectLookup::class )
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

		$resolver = new SubjectResolver(
			new InMemorySubjectContentRepository(),
			$this->createStub( SubjectLookup::class ),
			$pageIdentifiersLookup,
			TestSubjectIds::newParser()
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

}
