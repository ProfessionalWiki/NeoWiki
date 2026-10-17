<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * Needs the integration base class for the pages whose saves fill the tables the lookup reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectLabelLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LowestPageRows
 * @group Database
 */
class DatabaseSubjectLabelLookupTest extends NeoWikiIntegrationTestCase {

	public function testFindsTheSubjectsWhoseNameStartsWithTheSearchInNameOrder(): void {
		$this->createPageWithoutMainSubject(
			'Fruit',
			self::subject( 'sTestDSL1111111', 'Apple Pie' ),
			self::subject( 'sTestDSL1111112', 'Pineapple' ),
			self::subject( 'sTestDSL1111113', 'apple crumble' ),
			self::subject( 'sTestDSL1111114', 'Banana' ),
		);

		$this->assertEquals(
			[
				new SubjectLabelLookupResult( 'sTestDSL1111113', 'apple crumble', 'Fruit' ),
				new SubjectLabelLookupResult( 'sTestDSL1111111', 'Apple Pie', 'Fruit' ),
			],
			$this->labelsMatching( 'Apple' )
		);
	}

	public function testIgnoresCaseAndHowCharactersAreComposed(): void {
		$this->createPageWithoutMainSubject( 'Desserts', self::subject( 'sTestDSL1111111', 'Crème brûlée' ) );

		// A capital E followed by a combining grave accent, where the name holds a precomposed small è.
		$this->assertSame(
			[ 'Crème brûlée' ],
			array_column( $this->labelsMatching( "CRE\u{0300}ME" ), 'label' )
		);
	}

	public function testFindsAnUnlabelledMainSubjectByThePageNameItIsShownUnder(): void {
		$this->createPageWithSubjects( 'Apple Pie', mainSubject: self::subject( 'sTestDSL1111111', null ) );

		$this->assertSame( [ 'Apple Pie' ], array_column( $this->labelsMatching( 'Apple' ), 'label' ) );
	}

	public function testReturnsNoMoreThanTheLimit(): void {
		$this->createPageWithoutMainSubject(
			'Fruit',
			self::subject( 'sTestDSL1111111', 'Apple 1' ),
			self::subject( 'sTestDSL1111112', 'Apple 2' ),
			self::subject( 'sTestDSL1111113', 'Apple 3' ),
		);

		$this->assertSame( [ 'Apple 1', 'Apple 2' ], array_column( $this->labelsMatching( 'Apple', limit: 2 ), 'label' ) );
	}

	public function testFindsNothingWithinAnEmptyListOfSchemas(): void {
		$this->createPageWithoutMainSubject( 'Apples', self::subject( 'sTestDSL1111111', 'Apple Pie', 'Recipe' ) );

		$this->assertSame( [], $this->labelsMatching( 'Apple', schemaNames: [] ) );
	}

	public function testFindsSubjectsOfEachListedSchemaInNameOrder(): void {
		$this->createPageWithoutMainSubject(
			'Apples',
			self::subject( 'sTestDSL1111111', 'Apple Pie', 'Recipe' ),
			self::subject( 'sTestDSL1111112', 'Apple Tree', 'Plant' ),
			self::subject( 'sTestDSL1111113', 'Apple Inc.', 'Company' ),
		);

		$this->assertSame(
			[ 'Apple Inc.', 'Apple Pie' ],
			array_column( $this->labelsMatching( 'Apple', schemaNames: [ 'Recipe', 'Company' ] ), 'label' )
		);
	}

	/**
	 * The ids run opposite to the order the pages were created in.
	 */
	public function testOrdersNamesakesByIdEachWithItsOwnPage(): void {
		$this->createPageWithoutMainSubject( 'Meeting 2', self::subject( 'sTestDSL1111152', 'Attendance' ) );
		$this->createPageWithoutMainSubject( 'Meeting 1', self::subject( 'sTestDSL1111151', 'Attendance' ) );

		$this->assertEquals(
			[
				new SubjectLabelLookupResult( 'sTestDSL1111151', 'Attendance', 'Meeting 1' ),
				new SubjectLabelLookupResult( 'sTestDSL1111152', 'Attendance', 'Meeting 2' ),
			],
			$this->labelsMatching( 'Attendance' )
		);
	}

	public function testLeavesOutSubjectsOnPagesTheCallerCannotRead(): void {
		$this->createPageWithoutMainSubject( 'Open', self::subject( 'sTestDSL1111111', 'Apple Pie' ) );
		$hiddenPageId = $this->createPageWithoutMainSubject( 'Hidden', self::subject( 'sTestDSL1111112', 'Apple Tart' ) );

		$this->assertSame(
			[ 'Apple Pie' ],
			array_column(
				$this->labelsMatching( 'Apple', readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] ) ),
				'label'
			)
		);
	}

	public function testFillsTheLimitFromBeyondUnreadableSubjects(): void {
		$this->createPageWithoutMainSubject( 'Page 1', self::subject( 'sTestDSL1111141', 'Apple 1' ) );
		// Sorts between the two readable Subjects, so reading only as many rows as the limit would return one.
		$hiddenPageId = $this->createPageWithoutMainSubject( 'Page 2', self::subject( 'sTestDSL1111142', 'Apple 2' ) );
		$this->createPageWithoutMainSubject( 'Page 3', self::subject( 'sTestDSL1111143', 'Apple 3' ) );

		$this->assertSame(
			[ 'Apple 1', 'Apple 3' ],
			array_column(
				$this->labelsMatching( 'Apple', limit: 2, readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] ) ),
				'label'
			)
		);
	}

	/**
	 * Every page holds the Subject under a name of its own, and the lowest page id is neither the first nor the last of
	 * the three pages to have stored its row.
	 */
	public function testSuggestsASubjectThatSeveralPagesHoldOnceUnderTheLowestPageId(): void {
		$this->createPageWithoutMainSubject( 'Lowest', self::subject( 'sTestDSL1111111', 'Apple old' ) );
		$this->createPageWithoutMainSubject( 'Middle', self::subject( 'sTestDSL1111111', 'Apple middle' ) );
		$this->createPageWithoutMainSubject( 'Highest', self::subject( 'sTestDSL1111111', 'Apple old' ) );
		$this->createPageWithoutMainSubject( 'Lowest', self::subject( 'sTestDSL1111111', 'Apple lowest' ) );
		$this->createPageWithoutMainSubject( 'Highest', self::subject( 'sTestDSL1111111', 'Apple highest' ) );

		$this->assertEquals(
			[ new SubjectLabelLookupResult( 'sTestDSL1111111', 'Apple lowest', 'Lowest' ) ],
			$this->labelsMatching( 'Apple' )
		);
	}

	/**
	 * Only data written past the rules gives a Dependent Subject a label to match.
	 */
	public function testNeverOffersADependentSubject(): void {
		$this->createIngredientSchema();
		$this->createPageWithoutMainSubject(
			'Recipes',
			self::subject( 'sTestDSL1111111', 'Apple Pie' ),
			self::subject( 'sTestDSL1111112', 'Apple', 'Ingredient' ),
		);

		$this->assertSame( [ 'Apple Pie' ], array_column( $this->labelsMatching( 'Apple' ), 'label' ) );
	}

	public function testDependentSubjectsDoNotCrowdOutTheSubjectsAfterThem(): void {
		$this->createIngredientSchema();
		$this->createPageWithoutMainSubject(
			'Recipes',
			self::subject( 'sTestDSL1111121', 'Apple 1', 'Ingredient' ),
			self::subject( 'sTestDSL1111122', 'Apple 2', 'Ingredient' ),
			self::subject( 'sTestDSL1111123', 'Apple 3', 'Ingredient' ),
			self::subject( 'sTestDSL1111124', 'Apple 4', 'Ingredient' ),
			self::subject( 'sTestDSL1111111', 'Apple Pie' ),
		);

		$this->assertSame( [ 'Apple Pie' ], array_column( $this->labelsMatching( 'Apple', limit: 1 ), 'label' ) );
	}

	/**
	 * In a LIKE pattern, a percent sign matches any text and an underscore any one character.
	 *
	 * @dataProvider likeWildcardProvider
	 */
	public function testMatchesPercentSignsAndUnderscoresInTheSearchLiterally( string $search, string $match, string $nonMatch ): void {
		$this->createPageWithoutMainSubject(
			'Wildcards',
			self::subject( 'sTestDSL1111111', $match ),
			self::subject( 'sTestDSL1111112', $nonMatch ),
		);

		$this->assertSame( [ $match ], array_column( $this->labelsMatching( $search ), 'label' ) );
	}

	public static function likeWildcardProvider(): iterable {
		yield 'percent sign' => [ '100%', '100% cotton', '1000 threads' ];
		yield 'underscore' => [ 'snake_', 'snake_case', 'snakes and ladders' ];
	}

	public function testFindsNothingForASearchOfOnlySpaces(): void {
		$this->createPageWithoutMainSubject( 'Spaced', self::subject( 'sTestDSL1111111', ' Apple' ) );

		$this->assertSame( [], $this->labelsMatching( ' ' ) );
	}

	private function createIngredientSchema(): void {
		$this->assertNotNull( $this->createSchema( 'Ingredient', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' ) );
	}

	private static function subject( string $id, ?string $label, string $schema = TestSubject::DEFAULT_SCHEMA_ID ): Subject {
		return TestSubject::build( id: $id, label: $label, schemaName: new SchemaName( $schema ) );
	}

	/**
	 * @param ?string[] $schemaNames
	 * @return SubjectLabelLookupResult[]
	 */
	private function labelsMatching(
		string $search,
		int $limit = 10,
		?array $schemaNames = null,
		?PageReadAuthorizer $readAuthorizer = null
	): array {
		return ( new DatabaseSubjectLabelLookup(
			connectionProvider: $this->getServiceContainer()->getConnectionProvider(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
		) )->getSubjectLabelsMatching( $search, $limit, $schemaNames );
	}

}
