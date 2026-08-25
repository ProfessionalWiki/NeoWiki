<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
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
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SubjectDataLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectContentRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SubjectDataLookup
 */
class SubjectDataLookupTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';
	private const string CHILD_SUBJECT_ID = 's1test5cccccccc';

	private function createTitle(): Title {
		return $this->createTitleNamed( 'Test Page' );
	}

	/**
	 * A bare Title stub answers null where production always has a page name, which the display-name
	 * rule takes a string of, so every Title double here is given one.
	 */
	private function createTitleNamed( string $pageName ): Title {
		$title = $this->createStub( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( $pageName );

		return $title;
	}

	private function createSubject( Statement ...$statements ): Subject {
		return new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Test Subject' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( $statements ),
		);
	}

	private function resolverWithMainSubject( Subject $subject, ?SubjectLookup $subjectLookup = null ): SubjectResolver {
		return $this->resolverWithPageSubjects( new PageSubjects( $subject, new SubjectMap() ), $subjectLookup );
	}

	private function resolverWithPageSubjects( ?PageSubjects $pageSubjects, ?SubjectLookup $subjectLookup = null ): SubjectResolver {
		return $this->newResolver( $pageSubjects, $subjectLookup );
	}

	private function emptyResolver( ?SubjectLookup $subjectLookup = null ): SubjectResolver {
		return $this->resolverWithPageSubjects( null, $subjectLookup );
	}

	private function resolverWithMainSubjectAndRelationTargets( Subject $subject, Subject ...$targets ): SubjectResolver {
		return $this->newResolver( new PageSubjects( $subject, new SubjectMap() ), null, ...$targets );
	}

	/**
	 * Relation targets are resolved through the page hosting them, so a target that exists is one
	 * placed on a page of its own, as that page's Main Subject.
	 */
	private function newResolver(
		?PageSubjects $pageSubjects,
		?SubjectLookup $subjectLookup,
		Subject ...$relationTargets
	): SubjectResolver {
		$contentRepository = new InMemorySubjectContentRepository( $pageSubjects );
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageId = 1000;

		foreach ( $relationTargets as $target ) {
			$pageId++;

			$contentRepository->setContentForPageId( $pageId, new PageSubjects( $target, new SubjectMap() ) );
			$pageIdentifiersLookup->addIdentifiers(
				$target->getId(),
				new PageIdentifiers( new PageId( $pageId ), 'Page ' . $pageId, 0 )
			);
		}

		return new SubjectResolver(
			$contentRepository,
			$subjectLookup ?? $this->createStub( SubjectLookup::class ),
			$pageIdentifiersLookup,
			TestSubjectIds::newParser()
		);
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

	// === getValue tests ===

	public function testGetValueReturnsStringScalar(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ 'Berlin' ], $lookup->getValue( $this->createTitle(), 'City' ) );
	}

	public function testGetValueReturnsNullForValueOfUnregisteredType(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Swatch' ), 'color', new UnregisteredTypeValue( 'color', [ '#ff5733' ] ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'Swatch' ) );
	}

	public function testGetValueReturnsFirstStringForMultiValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ 'alpha' ], $lookup->getValue( $this->createTitle(), 'Tags' ) );
	}

	public function testGetValueReturnsNumber(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ 42 ], $lookup->getValue( $this->createTitle(), 'Age' ) );
	}

	public function testGetValueReturnsFloat(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Price' ), 'number', new NumberValue( 19.99 ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ 19.99 ], $lookup->getValue( $this->createTitle(), 'Price' ) );
	}

	public function testGetValueReturnsTrueBoolean(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ true ], $lookup->getValue( $this->createTitle(), 'Active' ) );
	}

	public function testGetValueReturnsFalseBoolean(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( false ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

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
			$this->resolverWithMainSubjectAndRelationTargets( $subject, $targetSubject )
		);

		$this->assertSame(
			[ 'Sarah Naumann' ],
			$lookup->getValue( $this->createTitle(), 'Process owner' )
		);
	}

	public function testGetValueReturnsFirstRelationLabelForMultiRelation(): void {
		$target1 = new Subject(
			id: new SubjectId( 's1test5bbbbbbbb' ),
			label: new SubjectLabel( 'Alice' ),
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
			$this->resolverWithMainSubjectAndRelationTargets( $subject, $target1 )
		);

		$this->assertSame(
			[ 'Alice' ],
			$lookup->getValue( $this->createTitle(), 'Members' )
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

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame(
			[ self::TARGET_SUBJECT_ID ],
			$lookup->getValue( $this->createTitle(), 'Process owner' )
		);
	}

	public function testGetValueReturnsNullForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'Nonexistent' ) );
	}

	public function testGetValueReturnsNullWhenNoSubjectOnPage(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), 'City' ) );
	}

	public function testGetValueReturnsNullForEmptyPropertyName(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ null ], $lookup->getValue( $this->createTitle(), '' ) );
	}

	public function testGetValueReturnsNullForEmptyStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

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

		$subjectLookup = $this->createSubjectLookupReturning( $targetSubject );
		$lookup = new SubjectDataLookup( $this->emptyResolver( $subjectLookup ) );

		$this->assertSame(
			[ 'Munich' ],
			$lookup->getValue( $this->createTitle(), 'City', [ 'subject' => self::TARGET_SUBJECT_ID ] )
		);
	}

	public function testGetValueWithInvalidSubjectOptionReturnsNull(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame(
			[ null ],
			$lookup->getValue( $this->createTitle(), 'City', [ 'subject' => 'invalid' ] )
		);
	}

	public function testGetValueTrimsPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ 'Berlin' ], $lookup->getValue( $this->createTitle(), '  City  ' ) );
	}

	// === getAll tests ===

	public function testGetAllReturnsSingleStringAsTable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ [ 1 => 'Berlin' ] ], $lookup->getAll( $this->createTitle(), 'City' ) );
	}

	public function testGetAllReturnsMultiStringAsTable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame(
			[ [ 1 => 'alpha', 2 => 'beta', 3 => 'gamma' ] ],
			$lookup->getAll( $this->createTitle(), 'Tags' )
		);
	}

	public function testGetAllReturnsNumberAsTable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ [ 1 => 42 ] ], $lookup->getAll( $this->createTitle(), 'Age' ) );
	}

	public function testGetAllReturnsBooleanAsTable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ [ 1 => true ] ], $lookup->getAll( $this->createTitle(), 'Active' ) );
	}

	public function testGetAllReturnsRelationLabelsAsTable(): void {
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
			$this->resolverWithMainSubjectAndRelationTargets( $subject, $target1, $target2 )
		);

		$this->assertSame(
			[ [ 1 => 'Alice', 2 => 'Bob' ] ],
			$lookup->getAll( $this->createTitle(), 'Members' )
		);
	}

	public function testGetAllReturnsNullForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ null ], $lookup->getAll( $this->createTitle(), 'Nonexistent' ) );
	}

	public function testGetAllReturnsNullForEmptyValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$this->assertSame( [ null ], $lookup->getAll( $this->createTitle(), 'City' ) );
	}

	// === getMainSubjectData tests ===

	public function testGetMainSubjectReturnsSubjectTable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
			new Statement( new PropertyName( 'Population' ), 'number', new NumberValue( 3645000 ) ),
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$result = $lookup->getMainSubjectData( $this->createTitle() );

		$this->assertSame( self::SUBJECT_ID, $result[0]['id'] );
		$this->assertSame( 'Test Subject', $result[0]['label'] );
		$this->assertSame( 'TestSchema', $result[0]['schema'] );
		$this->assertSame( 'text', $result[0]['statements']['City']['propertyType'] );
		$this->assertSame( [ 1 => 'Berlin' ], $result[0]['statements']['City']['values'] );
		$this->assertSame( 'number', $result[0]['statements']['Population']['propertyType'] );
		$this->assertSame( [ 1 => 3645000 ], $result[0]['statements']['Population']['values'] );
	}

	public function testSubjectTableRepeatsTheStoredLabelAsTheDisplayedOne(): void {
		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $this->createSubject() ) );

		$result = $lookup->getMainSubjectData( $this->createTitle() );

		$this->assertSame( 'Test Subject', $result[0]['label'] );
		$this->assertSame( 'Test Subject', $result[0]['storedLabel'] );
	}

	/**
	 * `label` is the name to show and never nil, so a template concatenating it cannot break on a
	 * Subject nobody named. `storedLabel` is the one that reports the absence.
	 */
	public function testMainSubjectWithoutALabelIsNamedAfterItsPage(): void {
		$subject = new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: null,
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList(),
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$result = $lookup->getMainSubjectData( $this->createTitleNamed( 'Rijksmuseum' ) );

		$this->assertSame( 'Rijksmuseum', $result[0]['label'] );
		$this->assertNull( $result[0]['storedLabel'] );
	}

	public function testGetMainSubjectReturnsNullWhenNoSubject(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ null ], $lookup->getMainSubjectData( $this->createTitle() ) );
	}

	public function testGetMainSubjectReturnsNullWhenPageHasNoMainSubject(): void {
		$pageSubjects = new PageSubjects( null, new SubjectMap() );

		$lookup = new SubjectDataLookup( $this->resolverWithPageSubjects( $pageSubjects ) );

		$this->assertSame( [ null ], $lookup->getMainSubjectData( $this->createTitle() ) );
	}

	// === getSubjectData tests ===

	public function testGetSubjectReturnsSubjectTable(): void {
		$subject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'ACME Corp' ),
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'Founded' ), 'number', new NumberValue( 1985 ) ),
			] ),
		);

		$subjectLookup = $this->createSubjectLookupReturning( $subject );
		$lookup = new SubjectDataLookup( $this->emptyResolver( $subjectLookup ) );

		$result = $lookup->getSubjectData( self::TARGET_SUBJECT_ID );

		$this->assertSame( self::TARGET_SUBJECT_ID, $result[0]['id'] );
		$this->assertSame( 'ACME Corp', $result[0]['label'] );
		$this->assertSame( 'Company', $result[0]['schema'] );
		$this->assertSame( [ 1 => 1985 ], $result[0]['statements']['Founded']['values'] );
	}

	public function testGetSubjectReturnsNullForInvalidId(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ null ], $lookup->getSubjectData( 'invalid' ) );
	}

	public function testGetSubjectReturnsNullForUnknownId(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ null ], $lookup->getSubjectData( self::TARGET_SUBJECT_ID ) );
	}

	public function testGetSubjectIncludesRelationDetails(): void {
		$targetSubject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Jane Doe' ),
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList(),
		);

		$subject = new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'ACME Corp' ),
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				new Statement(
					new PropertyName( 'CEO' ),
					'relation',
					new RelationValue(
						new Relation(
							id: new RelationId( 'r1test5cccccccc' ),
							targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
							properties: new RelationProperties( [] ),
						)
					)
				),
			] ),
		);

		$lookup = new SubjectDataLookup(
			$this->newResolver( null, $this->createSubjectLookupReturning( $subject ), $targetSubject )
		);

		$result = $lookup->getSubjectData( self::SUBJECT_ID );

		$this->assertSame( 'relation', $result[0]['statements']['CEO']['propertyType'] );
		$this->assertSame( 'r1test5cccccccc', $result[0]['statements']['CEO']['values'][1]['id'] );
		$this->assertSame( self::TARGET_SUBJECT_ID, $result[0]['statements']['CEO']['values'][1]['target'] );
		$this->assertSame( 'Jane Doe', $result[0]['statements']['CEO']['values'][1]['label'] );
	}

	// === getChildSubjectsData tests ===

	public function testGetChildSubjectsReturnsArrayOfSubjectTables(): void {
		$mainSubject = $this->createSubject();

		$child1 = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Child One' ),
			schemaName: new SchemaName( 'ChildSchema' ),
			statements: new StatementList(),
		);
		$child2 = new Subject(
			id: new SubjectId( self::CHILD_SUBJECT_ID ),
			label: new SubjectLabel( 'Child Two' ),
			schemaName: new SchemaName( 'ChildSchema' ),
			statements: new StatementList(),
		);

		$pageSubjects = new PageSubjects(
			$mainSubject,
			new SubjectMap( $child1, $child2 )
		);

		$lookup = new SubjectDataLookup( $this->resolverWithPageSubjects( $pageSubjects ) );

		$result = $lookup->getChildSubjectsData( $this->createTitle() );

		$this->assertCount( 2, $result[0] );
		$this->assertSame( self::TARGET_SUBJECT_ID, $result[0][1]['id'] );
		$this->assertSame( 'Child One', $result[0][1]['label'] );
		$this->assertSame( self::CHILD_SUBJECT_ID, $result[0][2]['id'] );
		$this->assertSame( 'Child Two', $result[0][2]['label'] );
	}

	public function testChildSubjectWithoutALabelIsNamedAfterItsSchema(): void {
		$child = new Subject(
			id: new SubjectId( self::CHILD_SUBJECT_ID ),
			label: null,
			schemaName: new SchemaName( 'Attendance' ),
			statements: new StatementList(),
		);

		$pageSubjects = new PageSubjects( $this->createSubject(), new SubjectMap( $child ) );

		$lookup = new SubjectDataLookup( $this->resolverWithPageSubjects( $pageSubjects ) );

		$result = $lookup->getChildSubjectsData( $this->createTitleNamed( 'Rijksmuseum' ) );

		$this->assertSame( 'Attendance', $result[0][1]['label'] );
		$this->assertNull( $result[0][1]['storedLabel'] );
	}

	public function testGetChildSubjectsReturnsEmptyArrayWhenNoChildren(): void {
		$mainSubject = $this->createSubject();

		$pageSubjects = new PageSubjects( $mainSubject, new SubjectMap() );

		$lookup = new SubjectDataLookup( $this->resolverWithPageSubjects( $pageSubjects ) );

		$this->assertSame( [ [] ], $lookup->getChildSubjectsData( $this->createTitle() ) );
	}

	public function testGetChildSubjectsReturnsEmptyArrayWhenNoContent(): void {
		$lookup = new SubjectDataLookup( $this->emptyResolver() );

		$this->assertSame( [ [] ], $lookup->getChildSubjectsData( $this->createTitle() ) );
	}

	public function testGetMainSubjectIncludesBooleanStatementValues(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) ),
		);

		$lookup = new SubjectDataLookup( $this->resolverWithMainSubject( $subject ) );

		$result = $lookup->getMainSubjectData( $this->createTitle() );

		$this->assertSame( 'boolean', $result[0]['statements']['Active']['propertyType'] );
		$this->assertSame( [ 1 => true ], $result[0]['statements']['Active']['values'] );
	}

}
