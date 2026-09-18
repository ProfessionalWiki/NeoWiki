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
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\MarkedMatchesTrait;

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

		$this->assertSame( 'Rijksmuseum', $this->landingOf( $hit )->subjectName );
		$this->assertFalse( $this->landingOf( $hit )->subjectNameIsGenerated );
	}

	public function testALabellessLandingSubjectOfAPageWithAChosenTitleIsNamedByThePage(): void {
		$subject = $this->subjectWith( self::MAIN_ID, null, TestStatement::build( property: 'City', value: 'Amsterdam' ) );

		$hit = $this->hitFor( new PageSubjects( $subject, new SubjectMap() ), false, 'amsterdam' );

		$this->assertSame( self::PAGE_NAME, $this->landingOf( $hit )->subjectName );
		$this->assertFalse( $this->landingOf( $hit )->subjectNameIsGenerated );
	}

	public function testALabellessMainSubjectOfAPageTitledByItsIdFallsBackToItsSchemaName(): void {
		$subject = $this->subjectWith( self::MAIN_ID, null, TestStatement::build( property: 'City', value: 'Amsterdam' ) );

		$hit = $this->hitFor( new PageSubjects( $subject, new SubjectMap() ), false, 'amsterdam', self::MAIN_ID );

		$this->assertSame( 'Museum', $this->landingOf( $hit )->subjectName );
		$this->assertTrue( $this->landingOf( $hit )->subjectNameIsGenerated );
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

	private function museumIn( string $city ): Subject {
		return $this->subjectWith(
			self::MAIN_ID,
			'Rijksmuseum',
			TestStatement::build( property: 'City', value: $city )
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
				PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
				TestSources::newSchemaResolver()
			)
		);
	}

}
