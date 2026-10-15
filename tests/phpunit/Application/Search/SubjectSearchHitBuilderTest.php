<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Search;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchLanding;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchTextBuilder;
use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestNaming;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\MarkedMatchesTrait;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchLanding
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine
 */
class SubjectSearchHitBuilderTest extends TestCase {

	use MarkedMatchesTrait;

	private const string MAIN_ID = 's11111111111111';
	private const string OTHER_ID = 's22222222222222';
	private const string ATTENDANCE_ID = 's33333333333331';
	private const string SOURCE_ID = 's33333333333332';
	private const string PAGE_NAME = 'Rijksmuseum';

	public function testAPageWithoutSubjectsHasNothingToShow(): void {
		$this->assertNull( $this->buildHit( PageSubjects::newEmpty(), true, 'amsterdam' ) );
	}

	public function testAPageWithContentAndWithoutAMatchIsLeftAlone(): void {
		$this->assertNull( $this->buildHit( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), true, 'berlin' ) );
	}

	public function testAMatchOnAnOtherSubjectOfAPageWithContentStaysOnThePageAndShowsThatSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith( $this->museumIn( 'Amsterdam' ), $this->otherSubjectIn( 'Berlin' ) ),
			true,
			'berlin'
		);

		$this->assertNull( $hit->landing );
		$this->assertSame( 'Gemaldegalerie', $this->matchOf( $hit )->subjectName );
	}

	public function testAMatchOnTheMainSubjectOfAPageWithContentLandsOnThePage(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), true, 'amsterdam' );

		$this->assertNull( $hit->landing );
	}

	public function testAMatchOnTheMainSubjectOfAPageWithoutContentLandsOnTheMainSubject(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), false, 'amsterdam' );

		$this->assertSame( self::MAIN_ID, $this->landingOf( $hit )->subjectId->text );
	}

	public function testAPageWithoutContentLandsOnItsMainSubjectEvenWithoutAMatch(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), false, 'berlin' );

		$this->assertSame( self::MAIN_ID, $this->landingOf( $hit )->subjectId->text );
		$this->assertNull( $hit->match );
	}

	public function testAPageWithoutContentAndWithoutAMainSubjectLeadsNowhereElse(): void {
		$hit = $this->hitFor(
			new PageSubjects( null, new SubjectMap( $this->otherSubjectIn( 'Berlin' ) ) ),
			false,
			'berlin'
		);

		$this->assertNull( $hit->landing );
		$this->assertNotNull( $hit->match );
	}

	public function testTheMainSubjectIsPreferredWhenSeveralSubjectsMatch(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->museumIn( 'Amsterdam' ),
				$this->subjectWith( self::OTHER_ID, 'Gemaldegalerie', TestStatement::build( property: 'Region', value: 'Amsterdam' ) )
			),
			true,
			'amsterdam'
		);

		$this->assertSame( [ 'City' ], $this->propertyNamesOf( $hit ) );
	}

	public function testTheOtherSubjectsAreTriedInPageOrder(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->museumIn( 'Amsterdam' ),
				$this->otherSubjectIn( 'Berlin' ),
				$this->subjectWith( 's33333333333333', 'Bode Museum', TestStatement::build( property: 'City', value: 'Berlin' ) )
			),
			true,
			'berlin'
		);

		$this->assertSame( 'Gemaldegalerie', $this->matchOf( $hit )->subjectName );
	}

	public function testAMatchedLabelIsALineWithoutAPropertyName(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), true, 'rijksmuseum' );

		$this->assertSame( [ null ], $this->propertyNamesOf( $hit ) );
		$this->assertSame( '[Rijksmuseum]', $this->markedLine( $this->matchOf( $hit )->lines[0] ) );
	}

	public function testAMatchedValueIsALineNamingItsProperty(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), true, 'amsterdam' );

		$this->assertSame( [ 'City' ], $this->propertyNamesOf( $hit ) );
		$this->assertSame( '[Amsterdam]', $this->markedLine( $this->matchOf( $hit )->lines[0] ) );
	}

	public function testOnlyTheMatchedLinesOfTheMatchedSubjectAreShown(): void {
		$hit = $this->hitFor(
			$this->pageWith( $this->subjectWith(
				self::MAIN_ID,
				'Rijksmuseum',
				TestStatement::build( property: 'City', value: 'Amsterdam' ),
				TestStatement::build( property: 'Founded', value: '1800' ),
				TestStatement::build( property: 'Country', value: 'The Netherlands' )
			) ),
			true,
			'amsterdam netherlands'
		);

		$this->assertSame( [ 'City', 'Country' ], $this->propertyNamesOf( $hit ) );
	}

	public function testAtMostThreeMatchedLinesAreShown(): void {
		$hit = $this->hitFor(
			$this->pageWith( $this->subjectWith(
				self::MAIN_ID,
				'Amsterdam',
				TestStatement::build( property: 'City', value: 'Amsterdam' ),
				TestStatement::build( property: 'Region', value: 'Amsterdam' ),
				TestStatement::build( property: 'Municipality', value: 'Amsterdam' ),
				TestStatement::build( property: 'Port', value: 'Amsterdam' )
			) ),
			true,
			'amsterdam'
		);

		$this->assertSame( [ null, 'City', 'Region' ], $this->propertyNamesOf( $hit ) );
	}

	public function testTheLandingSubjectIsNamedByItsLabel(): void {
		$hit = $this->hitFor( $this->pageWith( $this->museumIn( 'Amsterdam' ) ), false, 'amsterdam' );

		$this->assertSame( 'Rijksmuseum', $this->landingOf( $hit )->chosenName );
	}

	public function testALabellessLandingSubjectOfAPageWithAChosenTitleIsNamedByThePage(): void {
		$subject = $this->subjectWith( self::MAIN_ID, null, TestStatement::build( property: 'City', value: 'Amsterdam' ) );

		$hit = $this->hitFor( new PageSubjects( $subject, new SubjectMap() ), false, 'amsterdam' );

		$this->assertSame( self::PAGE_NAME, $this->landingOf( $hit )->chosenName );
	}

	public function testALabellessMainSubjectOfAPageTitledByItsIdHasNoChosenName(): void {
		$subject = $this->subjectWith( self::MAIN_ID, null, TestStatement::build( property: 'City', value: 'Amsterdam' ) );

		$hit = $this->hitFor( new PageSubjects( $subject, new SubjectMap() ), false, 'amsterdam', self::MAIN_ID );

		$this->assertNull( $this->landingOf( $hit )->chosenName );
	}

	public function testTheMatchedSubjectIsNamedInTheExtractWhenThePageTitleDoesNotNameIt(): void {
		$hit = $this->hitFor(
			$this->pageWith( $this->subjectWith(
				self::MAIN_ID,
				'Van Gogh Museum',
				TestStatement::build( property: 'City', value: 'Amsterdam' )
			) ),
			true,
			'amsterdam'
		);

		$this->assertSame( 'Van Gogh Museum', $this->matchOf( $hit )->subjectName );
	}

	public function testTheMatchedSubjectIsNamedWhenTheRowLinksToAnotherSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith( $this->museumIn( 'Amsterdam' ), $this->otherSubjectIn( 'Berlin' ) ),
			false,
			'berlin'
		);

		$this->assertSame( self::MAIN_ID, $this->landingOf( $hit )->subjectId->text );
		$this->assertSame( 'Gemaldegalerie', $this->matchOf( $hit )->subjectName );
	}

	/**
	 * The row names its landing Subject by id, so a label matching that Subject's Schema name is not
	 * one the row already carries.
	 */
	public function testTheMatchedSubjectIsNamedWhenTheRowNamesTheLandingSubjectByItsId(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->subjectWith( self::MAIN_ID, null, TestStatement::build( property: 'City', value: 'Amsterdam' ) ),
				$this->subjectWith( self::OTHER_ID, 'Museum', TestStatement::build( property: 'City', value: 'Berlin' ) )
			),
			false,
			'berlin',
			self::MAIN_ID
		);

		$this->assertSame( 'Museum', $this->matchOf( $hit )->subjectName );
	}

	public function testAnEngineThatMatchedNothingKnowableStillRetargetsAnEmptyPage(): void {
		$hit = $this->newBuilder()->build(
			$this->pageWith( $this->museumIn( 'Amsterdam' ) ),
			self::PAGE_NAME,
			false,
			null
		);

		$this->assertNotNull( $hit );
		$this->assertSame( self::MAIN_ID, $this->landingOf( $hit )->subjectId->text );
		$this->assertNull( $hit->match );
	}

	public function testAMatchInADependentSubjectIsAMatchInItsHostSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->museumIn( 'Amsterdam', $this->relationTo( 'Attendance', self::ATTENDANCE_ID ) ),
				$this->attendance( self::ATTENDANCE_ID, TestStatement::build( property: 'Year', value: '2019' ) )
			),
			true,
			'2019'
		);

		$this->assertSame( 'Museum', $this->matchOf( $hit )->schemaName );
		$this->assertSame( [ 'Attendance / Year' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testTheHostSubjectsOwnLinesComeBeforeThoseOfItsDependentSubjects(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->attendanceHostingMuseum(),
				$this->attendance( self::ATTENDANCE_ID, TestStatement::build( property: 'Note', value: 'Amsterdam crowds' ) )
			),
			true,
			'amsterdam'
		);

		$this->assertSame( [ 'City', 'Attendance / Note' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testALineOfADependentSubjectOfADependentSubjectNamesEachRelationPropertyOnTheWay(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->attendanceHostingMuseum(),
				$this->attendance( self::ATTENDANCE_ID, $this->relationTo( 'Source', self::SOURCE_ID ) ),
				$this->source( TestStatement::build( property: 'Publisher', value: 'Annual report' ) )
			),
			true,
			'annual'
		);

		$this->assertSame( [ 'Attendance / Source / Publisher' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testTheDependentSubjectsOfTheMainSubjectAreMatchedBeforeAnotherSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->attendanceHostingMuseum(),
				$this->otherSubjectIn( 'Berlin' ),
				$this->attendance( self::ATTENDANCE_ID, TestStatement::build( property: 'Note', value: 'Loan from Berlin' ) )
			),
			true,
			'berlin'
		);

		$this->assertSame( [ 'Attendance / Note' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testAMatchInADependentSubjectBeforeItsHostSubjectInPageOrderIsAMatchInItsHostSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->museumIn( 'Amsterdam' ),
				$this->attendance( self::ATTENDANCE_ID, TestStatement::build( property: 'Note', value: 'Loan from Berlin' ) ),
				$this->subjectWith(
					self::OTHER_ID,
					'Gemaldegalerie',
					TestStatement::build( property: 'City', value: 'Dahlem' ),
					$this->relationTo( 'Attendance', self::ATTENDANCE_ID )
				)
			),
			true,
			'berlin'
		);

		$this->assertSame( 'Gemaldegalerie', $this->matchOf( $hit )->subjectName );
		$this->assertSame( [ 'Attendance / Note' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testAMatchInAStandaloneSubjectTheHostSubjectPointsAtIsAMatchInThatSubject(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->museumIn( 'Amsterdam', $this->relationTo( 'Architect', self::OTHER_ID ) ),
				$this->otherSubjectIn( 'Berlin' )
			),
			true,
			'berlin'
		);

		$this->assertSame( [ 'City' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	public function testDependentSubjectsThatPointAtEachOtherAreMatchedOnce(): void {
		$hit = $this->hitFor(
			$this->pageWith(
				$this->attendanceHostingMuseum(),
				$this->attendance( self::ATTENDANCE_ID, $this->relationTo( 'Source', self::SOURCE_ID ) ),
				$this->source(
					TestStatement::build( property: 'Publisher', value: 'Annual report' ),
					$this->relationTo( 'Counted', self::ATTENDANCE_ID )
				)
			),
			true,
			'annual'
		);

		$this->assertSame( [ 'Attendance / Source / Publisher' ], $this->qualifiedPropertyNamesOf( $hit ) );
	}

	private function landingOf( SubjectSearchHit $hit ): SubjectSearchLanding {
		$this->assertNotNull( $hit->landing );

		return $hit->landing;
	}

	private function matchOf( SubjectSearchHit $hit ): SubjectSearchMatch {
		$this->assertNotNull( $hit->match );

		return $hit->match;
	}

	/**
	 * @return array<int, ?string>
	 */
	private function propertyNamesOf( SubjectSearchHit $hit ): array {
		return array_map(
			static fn ( MatchedSearchLine $line ): ?string => $line->propertyName,
			$this->matchOf( $hit )->lines
		);
	}

	/**
	 * @return string[]
	 */
	private function qualifiedPropertyNamesOf( SubjectSearchHit $hit ): array {
		return array_map(
			static fn ( MatchedSearchLine $line ): string => implode(
				' / ',
				[ ...$line->relationPropertyNames, $line->propertyName ?? '' ]
			),
			$this->matchOf( $hit )->lines
		);
	}

	private function museumIn( string $city, Statement ...$statements ): Subject {
		return $this->subjectWith(
			self::MAIN_ID,
			'Rijksmuseum',
			TestStatement::build( property: 'City', value: $city ),
			...$statements
		);
	}

	private function attendanceHostingMuseum(): Subject {
		return $this->museumIn( 'Amsterdam', $this->relationTo( 'Attendance', self::ATTENDANCE_ID ) );
	}

	private function attendance( string $id, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: null,
			schemaName: new SchemaName( 'Attendance' ),
			statements: new StatementList( $statements )
		);
	}

	private function source( Statement ...$statements ): Subject {
		return TestSubject::build(
			id: self::SOURCE_ID,
			label: null,
			schemaName: new SchemaName( 'Source' ),
			statements: new StatementList( $statements )
		);
	}

	private function relationTo( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

	private function schemaLookup(): InMemorySchemaLookup {
		return new InMemorySchemaLookup(
			TestSchema::build( name: 'Attendance', subjectsAreDependent: true ),
			TestSchema::build( name: 'Source', subjectsAreDependent: true ),
		);
	}

	private function otherSubjectIn( string $city ): Subject {
		return $this->subjectWith(
			self::OTHER_ID,
			'Gemaldegalerie',
			TestStatement::build( property: 'City', value: $city )
		);
	}

	private function subjectWith( string $id, ?string $label, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: $label,
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList( $statements )
		);
	}

	private function pageWith( Subject $mainSubject, Subject ...$otherSubjects ): PageSubjects {
		return new PageSubjects( $mainSubject, new SubjectMap( ...$otherSubjects ) );
	}

	/**
	 * For the tests that expect the page's Subjects to say something, so that an assertion about
	 * what the hit holds cannot pass on a hit that was never built.
	 */
	private function hitFor(
		PageSubjects $pageSubjects,
		bool $pageHasContent,
		string $query,
		string $pageName = self::PAGE_NAME
	): SubjectSearchHit {
		$hit = $this->buildHit( $pageSubjects, $pageHasContent, $query, $pageName );
		$this->assertNotNull( $hit );

		return $hit;
	}

	private function buildHit(
		PageSubjects $pageSubjects,
		bool $pageHasContent,
		string $query,
		string $pageName = self::PAGE_NAME
	): ?SubjectSearchHit {
		return $this->newBuilder()->build(
			$pageSubjects,
			$pageName,
			$pageHasContent,
			SearchTermMatcher::fromQuery( $query )
		);
	}

	private function newBuilder(): SubjectSearchHitBuilder {
		return new SubjectSearchHitBuilder(
			new SubjectSearchTextBuilder(
				PropertyTypeRegistry::withCoreTypes( TestSources::newSchemaReferenceParser() ),
				TestSources::newSchemaResolver( $this->schemaLookup() )
			),
			TestNaming::newDependentSubjectRules( $this->schemaLookup() )
		);
	}

}
