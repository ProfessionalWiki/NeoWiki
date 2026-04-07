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

	private function createRepositoryWithSubject( Subject $subject ): SubjectContentRepository {
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

	private function createDummyLookup(): SubjectLookup {
		return $this->createStub( SubjectLookup::class );
	}

	private function createLookupReturning( Subject $subject ): SubjectLookup {
		$lookup = $this->createStub( SubjectLookup::class );
		$lookup->method( 'getSubject' )->willReturn( $subject );

		return $lookup;
	}

	private function createPF( SubjectContentRepository $repo, ?SubjectLookup $lookup = null ): NeoWikiValueParserFunction {
		return new NeoWikiValueParserFunction(
			new SubjectResolver( $repo, $lookup ?? $this->createDummyLookup() )
		);
	}

	private function assertNoParseHtml( string $expectedHtml, string|array $result ): void {
		$this->assertIsArray( $result );
		$this->assertSame( $expectedHtml, $result[0] );
		$this->assertTrue( $result['noparse'] );
		$this->assertTrue( $result['isHTML'] );
	}

	// --- String values ---

	public function testReturnsStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'City' );

		$this->assertNoParseHtml( 'Berlin', $result );
	}

	public function testReturnsMultiValueStringWithDefaultSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags' );

		$this->assertNoParseHtml( 'alpha, beta, gamma', $result );
	}

	public function testReturnsMultiValueStringWithCustomSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', 'separator=;' );

		$this->assertNoParseHtml( 'alpha;beta', $result );
	}

	public function testTrimsWhitespaceAroundSeparatorParam(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', ' separator = ; ' );

		$this->assertNoParseHtml( 'alpha;beta', $result );
	}

	public function testEmptySeparatorConcatenatesValues(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'a', 'b', 'c' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', 'separator=' );

		$this->assertNoParseHtml( 'abc', $result );
	}

	// --- Number values ---

	public function testReturnsNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Age' );

		$this->assertNoParseHtml( '42', $result );
	}

	public function testReturnsFloatNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Price' ), 'number', new NumberValue( 19.99 ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Price' );

		$this->assertNoParseHtml( '19.99', $result );
	}

	// --- Boolean values ---

	public function testReturnsTrueBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Active' );

		$this->assertNoParseHtml( 'true', $result );
	}

	public function testReturnsFalseBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( false ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Active' );

		$this->assertNoParseHtml( 'false', $result );
	}

	// --- Relation values ---

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

		$result = $this->createPF(
			$this->createRepositoryWithSubject( $subject ),
			$this->createLookupReturning( $targetSubject )
		)->handle( $this->createMockParser(), 'Process owner' );

		$this->assertNoParseHtml( 'Sarah Naumann', $result );
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

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Process owner' );

		$this->assertNoParseHtml( self::TARGET_SUBJECT_ID, $result );
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

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ), $lookup )
			->handle( $this->createMockParser(), 'Members' );

		$this->assertNoParseHtml( 'Alice, Bob', $result );
	}

	public function testReturnsEmptyStringForEmptyRelationValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Members' ), 'relation', new RelationValue() )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Members' );

		$this->assertSame( '', $result );
	}

	// --- Empty / missing cases ---

	public function testReturnsEmptyStringForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Nonexistent' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWhenNoSubjectOnPage(): void {
		$result = $this->createPF( $this->createEmptyRepository() )
			->handle( $this->createMockParser(), 'City' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringForEmptyPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), '' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWithNoArguments(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringForEmptyStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'City' );

		$this->assertSame( '', $result );
	}

	// --- Parameter handling ---

	public function testTrimsPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), '  City  ' );

		$this->assertNoParseHtml( 'Berlin', $result );
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

		$pf = $this->createPF(
			$this->createEmptyRepository(),
			$this->createLookupReturning( $targetSubject )
		);

		$result = $pf->handle( $this->createMockParser(), 'City', 'subject=' . self::TARGET_SUBJECT_ID );

		$this->assertNoParseHtml( 'Munich', $result );
	}

	public function testInvalidSubjectIdReturnsEmptyString(): void {
		$result = $this->createPF( $this->createEmptyRepository() )
			->handle( $this->createMockParser(), 'City', 'subject=invalid' );

		$this->assertSame( '', $result );
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

		$result = $this->createPF( $repo )
			->handle( $this->createMockParser(), 'City', 'page=Other Page' );

		$this->assertNoParseHtml( 'Hamburg', $result );
	}

	public function testSubjectParamTakesPrecedenceOverPageParam(): void {
		$subjectViaId = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Via ID' ),
			schemaName: new SchemaName( 'TestSchema' ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'FromSubject' ) ),
			] ),
		);

		$subjectViaPage = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'FromPage' ) )
		);

		$result = $this->createPF(
			$this->createRepositoryWithSubject( $subjectViaPage ),
			$this->createLookupReturning( $subjectViaId )
		)->handle(
			$this->createMockParser(),
			'City',
			'subject=' . self::TARGET_SUBJECT_ID,
			'page=Some Page'
		);

		$this->assertNoParseHtml( 'FromSubject', $result );
	}

	public function testReturnsCorrectPropertyFromMultipleStatements(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
			new Statement( new PropertyName( 'Country' ), 'text', new StringValue( 'Germany' ) ),
			new Statement( new PropertyName( 'Population' ), 'number', new NumberValue( 3_700_000 ) ),
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Country' );

		$this->assertNoParseHtml( 'Germany', $result );
	}

	// --- HTML escaping ---

	public function testOutputIsNotParsedAsWikitext(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Name' ), 'text', new StringValue( '<b>bold</b> & "quoted"' ) )
		);

		$result = $this->createPF( $this->createRepositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Name' );

		$this->assertIsArray( $result );
		$this->assertSame( '&lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;', $result[0] );
		$this->assertTrue( $result['noparse'] );
		$this->assertTrue( $result['isHTML'] );
	}

}
