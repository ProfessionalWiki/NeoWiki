<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\BooleanValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiValueParserFunction;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiValueParserFunction
 */
class NeoWikiValueParserFunctionTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';

	private function createMockParser(): Parser {
		$title = $this->createStub( Title::class );

		$parser = $this->createStub( Parser::class );
		$parser->method( 'getTitle' )->willReturn( $title );

		return $parser;
	}

	private function createSubject( Statement ...$statements ): Subject {
		return new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Test Subject' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( $statements ),
		);
	}

	private function createResolverWithSubject( Subject $subject, ?SubjectLookup $subjectLookup = null ): SubjectResolver {
		$pageSubjects = new PageSubjects( $subject, new SubjectMap() );

		$subjectContent = $this->createStub( SubjectContent::class );
		$subjectContent->method( 'getPageSubjects' )->willReturn( $pageSubjects );

		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( $subjectContent );

		return new SubjectResolver( $repo, $subjectLookup ?? $this->createStub( SubjectLookup::class ) );
	}

	private function createEmptyResolver( ?SubjectLookup $subjectLookup = null ): SubjectResolver {
		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturn( null );

		return new SubjectResolver( $repo, $subjectLookup ?? $this->createStub( SubjectLookup::class ) );
	}

	private function createSubjectLookupReturning( Subject $subject ): SubjectLookup {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( $subject );

		return $lookup;
	}

	public function testReturnsStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'Berlin', $pf->handle( $this->createMockParser(), 'City' ) );
	}

	public function testReturnsMultiValueStringWithDefaultSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'alpha, beta, gamma', $pf->handle( $this->createMockParser(), 'Tags' ) );
	}

	public function testReturnsMultiValueStringWithCustomSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'alpha;beta', $pf->handle( $this->createMockParser(), 'Tags', 'separator=;' ) );
	}

	public function testReturnsNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '42', $pf->handle( $this->createMockParser(), 'Age' ) );
	}

	public function testReturnsFloatNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Price' ), 'number', new NumberValue( 19.99 ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '19.99', $pf->handle( $this->createMockParser(), 'Price' ) );
	}

	public function testReturnsTrueBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'true', $pf->handle( $this->createMockParser(), 'Active' ) );
	}

	public function testReturnsFalseBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( false ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'false', $pf->handle( $this->createMockParser(), 'Active' ) );
	}

	public function testReturnsRelationLabelWhenTargetExists(): void {
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

		$subjectLookup = $this->createSubjectLookupReturning( $targetSubject );
		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject, $subjectLookup ) );

		$this->assertSame( 'Sarah Naumann', $pf->handle( $this->createMockParser(), 'Process owner' ) );
	}

	public function testReturnsRelationIdWhenTargetNotFound(): void {
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

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( self::TARGET_SUBJECT_ID, $pf->handle( $this->createMockParser(), 'Process owner' ) );
	}

	public function testReturnsEmptyStringForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), 'Nonexistent' ) );
	}

	public function testReturnsEmptyStringWhenNoSubjectOnPage(): void {
		$pf = new NeoWikiValueParserFunction( $this->createEmptyResolver() );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), 'City' ) );
	}

	public function testReturnsEmptyStringForEmptyPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), '' ) );
	}

	public function testReturnsEmptyStringForEmptyStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), 'City' ) );
	}

	public function testTrimsPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'Berlin', $pf->handle( $this->createMockParser(), '  City  ' ) );
	}

	public function testSubjectIdParam(): void {
		$targetSubject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Other Subject' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Munich' ) ),
			] ),
		);

		$subjectLookup = $this->createSubjectLookupReturning( $targetSubject );
		$pf = new NeoWikiValueParserFunction( $this->createEmptyResolver( $subjectLookup ) );

		$this->assertSame(
			'Munich',
			$pf->handle( $this->createMockParser(), 'City', 'subject=' . self::TARGET_SUBJECT_ID )
		);
	}

	public function testInvalidSubjectIdReturnsEmptyString(): void {
		$pf = new NeoWikiValueParserFunction( $this->createEmptyResolver() );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), 'City', 'subject=invalid' ) );
	}

	public function testPageParam(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Hamburg' ) )
		);

		$repo = $this->createStub( SubjectContentRepository::class );
		$repo->method( 'getSubjectContentByPageTitle' )->willReturnCallback(
			function ( $title ) use ( $subject ) {
				if ( $title->getText() === 'Other Page' ) {
					$pageSubjects = new PageSubjects( $subject, new SubjectMap() );
					$subjectContent = $this->createStub( SubjectContent::class );
					$subjectContent->method( 'getPageSubjects' )->willReturn( $pageSubjects );
					return $subjectContent;
				}
				return null;
			}
		);

		$resolver = new SubjectResolver( $repo, $this->createStub( SubjectLookup::class ) );
		$pf = new NeoWikiValueParserFunction( $resolver );

		$this->assertSame( 'Hamburg', $pf->handle( $this->createMockParser(), 'City', 'page=Other Page' ) );
	}

	public function testReturnsCorrectPropertyFromMultipleStatements(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
			new Statement( new PropertyName( 'Country' ), 'text', new StringValue( 'Germany' ) ),
			new Statement( new PropertyName( 'Population' ), 'number', new NumberValue( 3_700_000 ) ),
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( 'Germany', $pf->handle( $this->createMockParser(), 'Country' ) );
	}

	public function testRelationLabelFallsBackToIdWhenLookupThrows(): void {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willThrowException( new \RuntimeException( 'Neo4j down' ) );

		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Owner' ),
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

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject, $lookup ) );

		$this->assertSame( self::TARGET_SUBJECT_ID, $pf->handle( $this->createMockParser(), 'Owner' ) );
	}

	public function testReturnsEmptyStringForEmptyRelationValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Members' ), 'relation', new RelationValue() )
		);

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject ) );

		$this->assertSame( '', $pf->handle( $this->createMockParser(), 'Members' ) );
	}

	public function testMultipleRelationLabels(): void {
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

		$lookup = $this->createMock( SubjectLookup::class );
		$lookup->method( 'getSubject' )
			->willReturnCallback( fn( SubjectId $id ) => match ( $id->text ) {
				's1test5bbbbbbbb' => $target1,
				's1test5cccccccc' => $target2,
				default => null,
			} );

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

		$pf = new NeoWikiValueParserFunction( $this->createResolverWithSubject( $subject, $lookup ) );

		$this->assertSame( 'Alice, Bob', $pf->handle( $this->createMockParser(), 'Members' ) );
	}

}
