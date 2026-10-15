<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Page;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules
 */
class DependentSubjectRulesTest extends TestCase {

	private const string PERSON = 'sPerson11111111';
	private const string OTHER_PERSON = 'sPerson22222222';
	private const string BIRTH = 'sBirth111111111';
	private const string OTHER_BIRTH = 'sBirth222222222';
	private const string RECORD = 'sRecord11111111';
	private const string NAME = 'sName1111111111';
	private const string OTHER_NAME = 'sName2222222222';
	private const string PLACE = 'sSpain111111111';
	private const string GHOST = 'sGhost111111111';
	private const string ELSEWHERE = 'sAway1111111111';

	private function newRules(): DependentSubjectRules {
		return new DependentSubjectRules( TestSources::newSchemaResolver( new InMemorySchemaLookup(
			TestSchema::build(
				name: 'Person',
				properties: new PropertyDefinitions( [
					'Birth' => TestProperty::buildRelation( targetSchemas: [ 'Birth' ] ),
					'Names' => TestProperty::buildRelation( targetSchemas: [ 'Name' ], multiple: true ),
					'Friend' => TestProperty::buildRelation( targetSchemas: [ 'Person' ] ),
					'Origin' => TestProperty::buildRelation( targetSchemas: [ 'Missing', 'Birth' ] ),
					'Kin' => TestProperty::buildRelation( targetSchemas: [ 'Missing' ] ),
				] )
			),
			TestSchema::build(
				name: 'Birth',
				properties: new PropertyDefinitions( [
					'Place' => TestProperty::buildRelation( targetSchemas: [ 'Place' ] ),
					'Record' => TestProperty::buildRelation( targetSchemas: [ 'Record' ] ),
				] ),
				subjectsAreDependent: true
			),
			TestSchema::build( name: 'Record', subjectsAreDependent: true ),
			TestSchema::build( name: 'Name', subjectsAreDependent: true ),
			TestSchema::build( name: 'Place' )
		) ) );
	}

	private function subject( string $id, string $schema, ?string $label = null, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: $label,
			schemaName: TestSchema::reference( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function person( string $id, Statement ...$statements ): Subject {
		return $this->subject( $id, 'Person', 'Pablo Picasso', ...$statements );
	}

	private function relation( string $property, string ...$targetIds ): Statement {
		return TestStatement::buildRelation(
			$property,
			array_map( static fn ( string $targetId ) => TestRelation::build( targetId: $targetId ), $targetIds )
		);
	}

	private function page( Subject ...$subjects ): PageSubjects {
		return new PageSubjects( null, new SubjectMap( ...$subjects ) );
	}

	/**
	 * @return Violation[]
	 */
	private function newViolations( PageSubjects $before, PageSubjects $after, Subject ...$subjectsOnOtherPages ): array {
		return $this->newRules()->newViolations( $before, $after, new SubjectMap( ...$subjectsOnOtherPages ), new SubjectIdList( [] ) );
	}

	/**
	 * @return string[]
	 */
	private function codesOf( array $violations ): array {
		return array_map( static fn ( Violation $violation ): string => $violation->code, $violations );
	}

	/**
	 * @return string[]
	 */
	private function idsOn( PageSubjects $pageSubjects ): array {
		return $pageSubjects->getAllSubjects()->getIdsAsTextArray();
	}

	public function testAHostSubjectWithItsDependentSubjectBreaksNoRule(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame( [], $this->newViolations( PageSubjects::newEmpty(), $after ) );
	}

	public function testRefusesASecondRelationToADependentSubject(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' ),
			$this->person( self::OTHER_PERSON, $this->relation( 'Birth', self::BIRTH ) )
		);

		$this->assertEquals(
			[ new Violation( null, DependentSubjectRules::REFERENCED_TWICE, severity: Severity::Error, subjectId: new SubjectId( self::BIRTH ) ) ],
			$this->newViolations( $before, $after )
		);
	}

	public function testAViolationThePageAlreadyHasIsNotNew(): void {
		$page = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' ),
			$this->person( self::OTHER_PERSON, $this->relation( 'Birth', self::BIRTH ) )
		);

		$this->assertSame( [], $this->newViolations( $page, $page ) );
	}

	public function testRefusesARelationToDependentSubjectsWhoseTargetIsNotOnThePage(): void {
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::ELSEWHERE ) ) );

		$this->assertEquals(
			[
				new Violation(
					propertyName: new PropertyName( 'Birth' ),
					code: DependentSubjectRules::TARGET_NOT_ON_PAGE,
					args: [ self::ELSEWHERE ],
					valuePartIndex: 0,
					severity: Severity::Error,
					subjectId: new SubjectId( self::PERSON ),
				),
			],
			$this->newViolations( PageSubjects::newEmpty(), $after )
		);
	}

	public function testARelationToDependentSubjectsMayPointAtAStandaloneSubjectTheWriteRemoved(): void {
		$before = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::PLACE ) ), $this->subject( self::PLACE, 'Place' ) );
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::PLACE ) ) );

		$this->assertSame( [], $this->newViolations( $before, $after ) );
	}

	public function testTheFirstTargetSchemaThatResolvesDecidesWhatAPropertyTargets(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Origin', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame( [], $this->newViolations( PageSubjects::newEmpty(), $after ) );
	}

	public function testRefusesARelationToADependentSubjectFromAPropertyNoneOfWhoseTargetSchemasResolve(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Kin', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame(
			[ DependentSubjectRules::RELATION_TO_DEPENDENT_SUBJECT ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after ) )
		);
	}

	public function testARelationToStandaloneSubjectsMayPointAtASubjectNotOnThePage(): void {
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Friend', self::ELSEWHERE ) ) );

		$this->assertSame( [], $this->newViolations( PageSubjects::newEmpty(), $after ) );
	}

	public function testRefusesARelationToADependentSubjectFromAPropertyThatTargetsStandaloneSubjects(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Friend', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertEquals(
			[
				new Violation(
					propertyName: new PropertyName( 'Friend' ),
					code: DependentSubjectRules::RELATION_TO_DEPENDENT_SUBJECT,
					args: [ self::BIRTH ],
					valuePartIndex: 0,
					severity: Severity::Error,
					subjectId: new SubjectId( self::PERSON ),
				),
			],
			$this->newViolations( PageSubjects::newEmpty(), $after )
		);
	}

	public function testRefusesARelationToADependentSubjectFromAPropertyTheSchemaDoesNotDeclare(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Undeclared', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame(
			[ DependentSubjectRules::RELATION_TO_DEPENDENT_SUBJECT ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after ) )
		);
	}

	public function testASubjectWhoseSchemaDoesNotResolveMayPointAtADependentSubjectOnItsPage(): void {
		$after = $this->page(
			$this->subject( self::GHOST, 'Missing', null, $this->relation( 'Anything', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame( [], $this->newViolations( PageSubjects::newEmpty(), $after ) );
	}

	public function testRefusesANewRelationToADependentSubjectOnAnotherPage(): void {
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Friend', self::ELSEWHERE ) ) );

		$this->assertSame(
			[ DependentSubjectRules::RELATION_TO_DEPENDENT_SUBJECT ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after, $this->subject( self::ELSEWHERE, 'Birth' ) ) )
		);
	}

	public function testARelationToADependentSubjectOnAnotherPageThatThePageAlreadyHadIsNotNew(): void {
		$page = $this->page( $this->person( self::PERSON, $this->relation( 'Friend', self::ELSEWHERE ) ) );

		$this->assertSame(
			[],
			$this->newViolations( $page, $page, $this->subject( self::ELSEWHERE, 'Birth' ) )
		);
	}

	public function testRefusesADependentSubjectAsMainSubject(): void {
		$after = new PageSubjects(
			$this->subject( self::BIRTH, 'Birth' ),
			new SubjectMap( $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ) )
		);

		$this->assertSame(
			[ DependentSubjectRules::IS_MAIN ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after ) )
		);
	}

	public function testRefusesADependentSubjectWithALabel(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', 'Birth of Pablo Picasso' )
		);

		$this->assertSame(
			[ DependentSubjectRules::HAS_LABEL ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after ) )
		);
	}

	public function testRefusesACreatedDependentSubjectThatNoSubjectPointsAt(): void {
		$after = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertEquals(
			[ new Violation( null, DependentSubjectRules::WITHOUT_HOST, severity: Severity::Error, subjectId: new SubjectId( self::BIRTH ) ) ],
			$this->newViolations( $this->page( $this->person( self::PERSON ) ), $after )
		);
	}

	public function testAnExistingDependentSubjectTheWriteLeavesUnreachedIsNotRefused(): void {
		$before = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ), $this->subject( self::BIRTH, 'Birth' ) );
		$after = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertSame( [], $this->newViolations( $before, $after ) );
	}

	public function testRefusesASuppliedDependentSubjectTheWriteLeavesUnreached(): void {
		$before = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ), $this->subject( self::BIRTH, 'Birth' ) );
		$after = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertEquals(
			[ new Violation( null, DependentSubjectRules::WITHOUT_HOST, severity: Severity::Error, subjectId: new SubjectId( self::BIRTH ) ) ],
			$this->newRules()->newViolations( $before, $after, new SubjectMap(), new SubjectIdList( [ new SubjectId( self::BIRTH ) ] ) )
		);
	}

	public function testADependentSubjectReachedThroughAnotherDependentSubjectHasAHost(): void {
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ) ),
			$this->subject( self::RECORD, 'Record' )
		);

		$this->assertSame( [], $this->newViolations( PageSubjects::newEmpty(), $after ) );
	}

	public function testADependentSubjectThatOnlyAnUnreachedDependentSubjectPointsAtHasNoHost(): void {
		$after = $this->page(
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ) ),
			$this->subject( self::RECORD, 'Record' )
		);

		$this->assertSame(
			[ DependentSubjectRules::WITHOUT_HOST, DependentSubjectRules::WITHOUT_HOST ],
			$this->codesOf( $this->newViolations( PageSubjects::newEmpty(), $after ) )
		);
	}

	public function testNamesTheTargetsOnOtherPagesOfTheRelationsAWriteAdds(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Friend', self::GHOST ) ),
			$this->person( self::OTHER_PERSON )
		);
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Friend', self::GHOST, self::OTHER_PERSON, self::ELSEWHERE ) ),
			$this->person( self::OTHER_PERSON )
		);

		$this->assertSame(
			[ self::ELSEWHERE ],
			$this->newRules()->targetsOnOtherPages( $before, $after )->asStringArray()
		);
	}

	public function testDoesNotNameTheTargetsOfRelationsToDependentSubjects(): void {
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::ELSEWHERE ) ) );

		$this->assertSame( [], $this->newRules()->targetsOnOtherPages( PageSubjects::newEmpty(), $after )->asStringArray() );
	}

	public function testRemovesADependentSubjectTheWriteLeavesUnreached(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' ),
			$this->subject( self::PLACE, 'Place' )
		);
		$after = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ), $this->subject( self::PLACE, 'Place' ) );

		$this->assertSame(
			[ self::PERSON, self::PLACE ],
			$this->idsOn( $this->newRules()->withoutDependentSubjectsTheWriteLeavesUnreached( $before, $after ) )
		);
	}

	public function testKeepsADependentSubjectThatWasUnreachedBeforeTheWrite(): void {
		$page = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertSame(
			[ self::PERSON, self::BIRTH ],
			$this->idsOn( $this->newRules()->withoutDependentSubjectsTheWriteLeavesUnreached( $page, $page ) )
		);
	}

	public function testKeepsADependentSubjectReachedThroughAnotherDependentSubject(): void {
		$page = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ) ),
			$this->subject( self::RECORD, 'Record' )
		);

		$this->assertSame(
			[ self::PERSON, self::BIRTH, self::RECORD ],
			$this->idsOn( $this->newRules()->withoutDependentSubjectsTheWriteLeavesUnreached( $page, $page ) )
		);
	}

	public function testKeepsWhatASubjectWhoseSchemaDoesNotResolvePointsAt(): void {
		$ghost = $this->subject( self::GHOST, 'Missing', null, $this->relation( 'Anything', self::BIRTH ) );
		$before = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ), $ghost, $this->subject( self::BIRTH, 'Birth' ) );
		$after = $this->page( $this->person( self::PERSON ), $ghost, $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertSame(
			[ self::PERSON, self::GHOST, self::BIRTH ],
			$this->idsOn( $this->newRules()->withoutDependentSubjectsTheWriteLeavesUnreached( $before, $after ) )
		);
	}

	public function testRemovesDependentSubjectsThatOnlyPointAtEachOther(): void {
		$birth = $this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ) );
		$record = $this->subject( self::RECORD, 'Record', null, $this->relation( 'Back', self::BIRTH ) );
		$before = $this->page( $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ), $birth, $record );
		$after = $this->page( $this->person( self::PERSON ), $birth, $record );

		$this->assertSame(
			[ self::PERSON ],
			$this->idsOn( $this->newRules()->withoutDependentSubjectsTheWriteLeavesUnreached( $before, $after ) )
		);
	}

	public function testRemovesTheRelationToADependentSubjectTheWriteRemoved(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ), $this->relation( 'Friend', self::OTHER_PERSON ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ), $this->relation( 'Friend', self::OTHER_PERSON ) )
		);

		$person = $this->newRules()->withoutRelationsToRemovedDependentSubjects( $before, $after )
			->getAllSubjects()->getSubject( new SubjectId( self::PERSON ) );

		$this->assertSame( [ self::OTHER_PERSON ], $person->getReferencedSubjects()->asStringArray() );
	}

	public function testKeepsTheOtherRelationsOfThePropertyThatPointedAtTheRemovedDependentSubject(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Names', self::NAME, self::OTHER_NAME ) ),
			$this->subject( self::NAME, 'Name' ),
			$this->subject( self::OTHER_NAME, 'Name' )
		);
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Names', self::NAME, self::OTHER_NAME ) ),
			$this->subject( self::OTHER_NAME, 'Name' )
		);

		$person = $this->newRules()->withoutRelationsToRemovedDependentSubjects( $before, $after )
			->getAllSubjects()->getSubject( new SubjectId( self::PERSON ) );

		$this->assertSame( [ self::OTHER_NAME ], $person->getReferencedSubjects()->asStringArray() );
	}

	public function testRemovingTheOnlyDependentSubjectAStatementPointsAtRemovesTheStatement(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ), $this->relation( 'Friend', self::OTHER_PERSON ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);
		$after = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ), $this->relation( 'Friend', self::OTHER_PERSON ) )
		);

		$person = $this->newRules()->withoutRelationsToRemovedDependentSubjects( $before, $after )
			->getAllSubjects()->getSubject( new SubjectId( self::PERSON ) );

		$this->assertSame( [ 'Friend' ], array_keys( $person->getStatements()->asArray() ) );
	}

	public function testKeepsTheRelationToARemovedStandaloneSubject(): void {
		$before = $this->page(
			$this->person( self::PERSON, $this->relation( 'Friend', self::OTHER_PERSON ) ),
			$this->person( self::OTHER_PERSON )
		);
		$after = $this->page( $this->person( self::PERSON, $this->relation( 'Friend', self::OTHER_PERSON ) ) );

		$person = $this->newRules()->withoutRelationsToRemovedDependentSubjects( $before, $after )
			->getAllSubjects()->getSubject( new SubjectId( self::PERSON ) );

		$this->assertSame( [ self::OTHER_PERSON ], $person->getReferencedSubjects()->asStringArray() );
	}

	public function testNamesTheDependentSubjectsASubjectReachesInPageOrder(): void {
		$person = $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ), $this->relation( 'Names', self::NAME ) );
		$page = $this->page(
			$person,
			$this->subject( self::NAME, 'Name' ),
			$this->subject( self::PLACE, 'Place' ),
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ), $this->relation( 'Place', self::PLACE ) ),
			$this->subject( self::RECORD, 'Record' )
		);

		$this->assertSame(
			[ self::NAME, self::BIRTH, self::RECORD ],
			$this->newRules()->dependentSubjectsReachedFrom( $person, $page )->getIdsAsTextArray()
		);
	}

	public function testDoesNotNameTheDependentSubjectsOfAnotherSubject(): void {
		$person = $this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) );
		$page = $this->page(
			$person,
			$this->subject( self::BIRTH, 'Birth' ),
			$this->person( self::OTHER_PERSON, $this->relation( 'Birth', self::OTHER_BIRTH ) ),
			$this->subject( self::OTHER_BIRTH, 'Birth' )
		);

		$this->assertSame(
			[ self::BIRTH ],
			$this->newRules()->dependentSubjectsReachedFrom( $person, $page )->getIdsAsTextArray()
		);
	}

	public function testTheHostSubjectOfADependentSubjectIsTheSubjectThatPointsAtIt(): void {
		$page = $this->page(
			$this->person( self::OTHER_PERSON, $this->relation( 'Birth', self::OTHER_BIRTH ) ),
			$this->subject( self::OTHER_BIRTH, 'Birth' ),
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' ),
			$this->subject( self::PLACE, 'Place' )
		);

		$this->assertSame(
			[ self::OTHER_BIRTH => self::OTHER_PERSON, self::BIRTH => self::PERSON ],
			$this->hostSubjectIdsOn( $page )
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function hostSubjectIdsOn( PageSubjects $pageSubjects ): array {
		return array_map(
			static fn ( Subject $hostSubject ): string => $hostSubject->id->text,
			$this->newRules()->hostSubjectsOn( $pageSubjects )
		);
	}

	public function testTheHostSubjectOfADependentSubjectCanBeADependentSubject(): void {
		$page = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Record', self::RECORD ) ),
			$this->subject( self::RECORD, 'Record' )
		);

		$this->assertSame( self::BIRTH, $this->hostSubjectIdsOn( $page )[self::RECORD] );
	}

	public function testAStandaloneSubjectHasNoHostSubjectWhenAnotherSubjectPointsAtIt(): void {
		$page = $this->page(
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', null, $this->relation( 'Place', self::PLACE ) ),
			$this->subject( self::PLACE, 'Place' )
		);

		$this->assertArrayNotHasKey( self::PLACE, $this->hostSubjectIdsOn( $page ) );
	}

	public function testTheHostSubjectOfADependentSubjectSeveralSubjectsPointAtIsTheFirstInPageOrder(): void {
		$page = $this->page(
			$this->subject( self::PLACE, 'Place' ),
			$this->person( self::PERSON, $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' ),
			$this->person( self::OTHER_PERSON, $this->relation( 'Birth', self::BIRTH ) )
		);

		$this->assertSame( self::PERSON, $this->hostSubjectIdsOn( $page )[self::BIRTH] );
	}

	public function testASchemaNameNamesASchemaOfDependentSubjects(): void {
		$this->assertTrue( $this->newRules()->localSchemaDeclaresDependentSubjects( 'Birth' ) );
	}

	/**
	 * @dataProvider namesOfNoSchemaOfDependentSubjectsProvider
	 */
	public function testASchemaNameNamesNoSchemaOfDependentSubjects( string $schemaName ): void {
		$this->assertFalse( $this->newRules()->localSchemaDeclaresDependentSubjects( $schemaName ) );
	}

	public static function namesOfNoSchemaOfDependentSubjectsProvider(): iterable {
		yield 'a Schema of standalone Subjects' => [ 'Place' ];
		yield 'no Schema' => [ 'Missing' ];
		yield 'a name no Schema can have' => [ '' ];
	}

	public function testADependentSubjectThatNoSubjectOnThePagePointsAtHasNoHostSubject(): void {
		$page = $this->page( $this->person( self::PERSON ), $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertArrayNotHasKey( self::BIRTH, $this->hostSubjectIdsOn( $page ) );
	}

	/**
	 * @dataProvider demoPageProvider
	 */
	public function testDemoDataPageBreaksNoRule( string $fileName ): void {
		$rules = new DependentSubjectRules(
			TestSources::newSchemaResolver( new InMemorySchemaLookup( ...self::demoSchemas() ) )
		);

		$this->assertSame(
			[],
			$rules->newViolations( PageSubjects::newEmpty(), self::demoPage( $fileName ), self::demoSubjects(), new SubjectIdList( [] ) )
		);
	}

	public static function demoPageProvider(): iterable {
		foreach ( self::demoFileNames( 'Subject' ) as $fileName ) {
			yield $fileName => [ $fileName ];
		}
	}

	/**
	 * @return string[]
	 */
	private static function demoFileNames( string $directory ): array {
		return array_map( 'basename', glob( __DIR__ . '/../../../../DemoData/' . $directory . '/*.json' ) );
	}

	/**
	 * @return Schema[]
	 */
	private static function demoSchemas(): array {
		$deserializer = new SchemaPersistenceDeserializer( TestSources::newPropertyTypeRegistry() );

		return array_map(
			fn ( string $fileName ): Schema => $deserializer->deserialize(
				new SchemaName( str_replace( '_', ' ', basename( $fileName, '.json' ) ) ),
				TestData::getFileContents( 'Schema/' . $fileName )
			),
			self::demoFileNames( 'Schema' )
		);
	}

	private static function demoPage( string $fileName ): PageSubjects {
		$deserializer = new SubjectContentDataDeserializer(
			new StatementDeserializer( TestSources::newPropertyTypeRegistry(), TestSubjectIds::newParser() ),
			new NullLogger(),
			TestSources::newSchemaReferenceParser()
		);

		return $deserializer->deserialize( TestData::getFileContents( 'Subject/' . $fileName ) );
	}

	private static function demoSubjects(): SubjectMap {
		$subjects = new SubjectMap();

		foreach ( self::demoFileNames( 'Subject' ) as $fileName ) {
			$subjects = $subjects->union( self::demoPage( $fileName )->getAllSubjects() );
		}

		return $subjects;
	}

}
