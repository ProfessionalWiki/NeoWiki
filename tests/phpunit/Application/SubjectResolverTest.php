<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
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
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectResolver
 */
class SubjectResolverTest extends TestCase {

	private const SUBJECT_ID = 's1test5aaaaaaaa';
	private const TARGET_SUBJECT_ID = 's1test5bbbbbbbb';

	private function createSubject( string $id = self::SUBJECT_ID, string $label = 'Test Subject' ): Subject {
		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $label ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList(),
		);
	}

	private function createRepositoryWithMainSubject( Subject $subject ): SubjectContentRepository {
		$pageSubjects = new PageSubjects( $subject, new SubjectMap() );

		$subjectContent = $this->createStub( SubjectContent::class );
		$subjectContent->method( 'getPageSubjects' )->willReturn( $pageSubjects );

		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( $subjectContent );

		return $repo;
	}

	private function createEmptyRepository(): SubjectContentRepository {
		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( null );

		return $repo;
	}

	public function testResolveByIdReturnsSubject(): void {
		$subject = $this->createSubject();

		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( $subject );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$this->assertSame( $subject, $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullForInvalidId(): void {
		$resolver = new SubjectResolver(
			$this->createEmptyRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->resolveById( 'invalid' ) );
	}

	public function testResolveByIdReturnsNullWhenLookupReturnsNull(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( null );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenLookupThrows(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willThrowException( new \RuntimeException( 'db error' ) );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveMainByTitleReturnsMainSubject(): void {
		$subject = $this->createSubject();

		$resolver = new SubjectResolver(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertSame( $subject, $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsNullWhenNoContent(): void {
		$resolver = new SubjectResolver(
			$this->createEmptyRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsPageSubjects(): void {
		$subject = $this->createSubject();
		$repo = $this->createRepositoryWithMainSubject( $subject );

		$resolver = new SubjectResolver( $repo, $this->createStub( SubjectLookup::class ) );

		$pageSubjects = $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) );

		$this->assertNotNull( $pageSubjects );
		$this->assertSame( $subject, $pageSubjects->getMainSubject() );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenNoContent(): void {
		$resolver = new SubjectResolver(
			$this->createEmptyRepository(),
			$this->createStub( SubjectLookup::class )
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveRelationLabelReturnsLabelWhenTargetExists(): void {
		$target = $this->createSubject( self::TARGET_SUBJECT_ID, 'Jane Doe' );

		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( $target );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$relation = new Relation(
			id: new RelationId( 'r1test5cccccccc' ),
			targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
			properties: new RelationProperties( [] ),
		);

		$this->assertSame( 'Jane Doe', $resolver->resolveRelationLabel( $relation ) );
	}

	public function testResolveRelationLabelFallsBackToIdWhenTargetNotFound(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( null );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$relation = new Relation(
			id: new RelationId( 'r1test5cccccccc' ),
			targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
			properties: new RelationProperties( [] ),
		);

		$this->assertSame( self::TARGET_SUBJECT_ID, $resolver->resolveRelationLabel( $relation ) );
	}

	public function testResolveRelationLabelFallsBackToIdWhenLookupThrows(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willThrowException( new \RuntimeException( 'db error' ) );

		$resolver = new SubjectResolver( $this->createEmptyRepository(), $lookup );

		$relation = new Relation(
			id: new RelationId( 'r1test5cccccccc' ),
			targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
			properties: new RelationProperties( [] ),
		);

		$this->assertSame( self::TARGET_SUBJECT_ID, $resolver->resolveRelationLabel( $relation ) );
	}

}
