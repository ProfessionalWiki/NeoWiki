<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\SubjectDataLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\SubjectDataLookup
 */
class SubjectDataLookupTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';

	private function createTitle(): Title {
		return $this->createStub( Title::class );
	}

	private function createSubject( Statement ...$statements ): Subject {
		return new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Test Subject' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( $statements ),
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

	private function createDummySubjectLookup(): SubjectLookup {
		return $this->createStub( SubjectLookup::class );
	}

	private function createSubjectLookupReturning( Subject ...$subjects ): SubjectLookup {
		$lookup = $this->createStub( SubjectLookup::class );

		if ( count( $subjects ) === 1 ) {
			$lookup->method( 'getSubject' )->willReturn( $subjects[0] );
		} else {
			$map = [];
			foreach ( $subjects as $subject ) {
				$map[$subject->getId()->text] = $subject;
			}
			$lookup->method( 'getSubject' )
				->willReturnCallback( fn( SubjectId $id ) => $map[$id->text] ?? null );
		}

		return $lookup;
	}

	public function testGetValueReturnsStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ 'Berlin' ], $lookup->getValue( $this->createTitle(), 'City' ) );
	}

	public function testGetValueReturnsMultiValueStringAsArray(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame(
			[ [ 1 => 'alpha', 2 => 'beta', 3 => 'gamma' ] ],
			$lookup->getValue( $this->createTitle(), 'Tags' )
		);
	}

	public function testGetValueReturnsNumber(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ 42 ], $lookup->getValue( $this->createTitle(), 'Age' ) );
	}

	public function testGetValueReturnsFloat(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Price' ), 'number', new NumberValue( 19.99 ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ 19.99 ], $lookup->getValue( $this->createTitle(), 'Price' ) );
	}

	public function testGetValueReturnsTrueBoolean(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ true ], $lookup->getValue( $this->createTitle(), 'Active' ) );
	}

	public function testGetValueReturnsFalseBoolean(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( false ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ false ], $lookup->getValue( $this->createTitle(), 'Active' ) );
	}

	public function testGetValueReturnsRelationLabelWhenTargetExists(): void {
		$targetSubject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Sarah Naumann' ),
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList(),
		);

		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Process owner' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5cccccccc' ),
						targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
						properties: new RelationProperties( [] ),
					)
				)
			)
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createSubjectLookupReturning( $targetSubject )
		);

		$this->assertSame(
			[ 'Sarah Naumann' ],
			$lookup->getValue( $this->createTitle(), 'Process owner' )
		);
	}

	public function testGetValueReturnsTargetIdWhenTargetNotFound(): void {
		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Process owner' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5cccccccc' ),
						targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
						properties: new RelationProperties( [] ),
					)
				)
			)
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame(
			[ self::TARGET_SUBJECT_ID ],
			$lookup->getValue( $this->createTitle(), 'Process owner' )
		);
	}

	public function testGetValueReturnsMultipleRelationLabels(): void {
		$target1 = new Subject(
			id: new SubjectId( 's1test5bbbbbbbb' ),
			label: new SubjectLabel( 'Alice' ),
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList(),
		);
		$target2 = new Subject(
			id: new SubjectId( 's1test5cccccccc' ),
			label: new SubjectLabel( 'Bob' ),
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList(),
		);

		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Members' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5dddddddd' ),
						targetId: new SubjectId( 's1test5bbbbbbbb' ),
						properties: new RelationProperties( [] ),
					),
					new Relation(
						id: new RelationId( 'r1test5eeeeeeee' ),
						targetId: new SubjectId( 's1test5cccccccc' ),
						properties: new RelationProperties( [] ),
					),
				)
			)
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createSubjectLookupReturning( $target1, $target2 )
		);

		$this->assertSame(
			[ [ 1 => 'Alice', 2 => 'Bob' ] ],
			$lookup->getValue( $this->createTitle(), 'Members' )
		);
	}

	public function testGetValueReturnsNullForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'Nonexistent' ) );
	}

	public function testGetValueReturnsNullWhenNoSubjectOnPage(): void {
		$lookup = new SubjectDataLookup(
			$this->createEmptyRepository(),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'City' ) );
	}

	public function testGetValueReturnsNullForEmptyPropertyName(): void {
		$lookup = new SubjectDataLookup(
			$this->createEmptyRepository(),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), '' ) );
	}

	public function testGetValueReturnsNullForEmptyStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'City' ) );
	}

	public function testGetValueWithSubjectOption(): void {
		$targetSubject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Other Subject' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Munich' ) ),
			] ),
		);

		$lookup = new SubjectDataLookup(
			$this->createEmptyRepository(),
			$this->createSubjectLookupReturning( $targetSubject )
		);

		$this->assertSame(
			[ 'Munich' ],
			$lookup->getValue( $this->createTitle(), 'City', [ 'subject' => self::TARGET_SUBJECT_ID ] )
		);
	}

	public function testGetValueWithInvalidSubjectOptionReturnsNull(): void {
		$lookup = new SubjectDataLookup(
			$this->createEmptyRepository(),
			$this->createDummySubjectLookup()
		);

		$this->assertSame(
			[ null ],
			$lookup->getValue( $this->createTitle(), 'City', [ 'subject' => 'invalid' ] )
		);
	}

	public function testGetValueTrimsPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup(
			$this->createRepositoryWithMainSubject( $subject ),
			$this->createDummySubjectLookup()
		);

		$this->assertSame( [ 'Berlin' ], $lookup->getValue( $this->createTitle(), '  City  ' ) );
	}

}
