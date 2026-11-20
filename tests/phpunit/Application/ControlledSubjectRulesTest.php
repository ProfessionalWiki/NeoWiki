<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\ControlledSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\ControlledSubjectRules
 */
class ControlledSubjectRulesTest extends TestCase {

	private const string TERM = 'sCSRWoman11111a';
	private const string PERSON = 'sCSRPerson1111a';

	private function newRules(): ControlledSubjectRules {
		return new ControlledSubjectRules(
			TestSources::newSchemaResolver( new InMemorySchemaLookup(
				TestSchema::build( name: 'Gender', subjectsAreControlled: true ),
				TestSchema::build( name: 'Person' ),
			) ),
			new SubjectContentDataSerializer()
		);
	}

	private function term( ?string $label = 'Female', string $schema = 'Gender' ): Subject {
		return TestSubject::build( id: self::TERM, label: $label, schemaName: TestSchema::reference( $schema ) );
	}

	private function person(): Subject {
		return TestSubject::build( id: self::PERSON, label: 'Ada', schemaName: TestSchema::reference( 'Person' ) );
	}

	private function schemaChangedBy( SubjectMap $before, SubjectMap $after ): ?string {
		return $this->newRules()->controlledSchemaChangedBy( $before, $after )?->getText();
	}

	public function testAddingAControlledSubjectNamesItsSchema(): void {
		$this->assertSame( 'Gender', $this->schemaChangedBy( new SubjectMap(), new SubjectMap( $this->term() ) ) );
	}

	public function testRemovingAControlledSubjectNamesItsSchema(): void {
		$this->assertSame( 'Gender', $this->schemaChangedBy( new SubjectMap( $this->term() ), new SubjectMap() ) );
	}

	public function testChangingAControlledSubjectNamesItsSchema(): void {
		$this->assertSame(
			'Gender',
			$this->schemaChangedBy( new SubjectMap( $this->term( 'Female' ) ), new SubjectMap( $this->term( 'Woman' ) ) )
		);
	}

	public function testChangingALabelToAnotherSpellingOfTheSameNumberNamesItsSchema(): void {
		$this->assertSame(
			'Gender',
			$this->schemaChangedBy( new SubjectMap( $this->term( '0042' ) ), new SubjectMap( $this->term( '42.0' ) ) )
		);
	}

	public function testMovingASubjectOutOfAControlledSchemaNamesThatSchema(): void {
		$this->assertSame(
			'Gender',
			$this->schemaChangedBy( new SubjectMap( $this->term() ), new SubjectMap( $this->term( schema: 'Person' ) ) )
		);
	}

	public function testMovingASubjectIntoAControlledSchemaNamesThatSchema(): void {
		$this->assertSame(
			'Gender',
			$this->schemaChangedBy( new SubjectMap( $this->term( schema: 'Person' ) ), new SubjectMap( $this->term() ) )
		);
	}

	public function testLeavingAControlledSubjectAsItWasNamesNoSchema(): void {
		$this->assertNull(
			$this->schemaChangedBy( new SubjectMap( $this->term() ), new SubjectMap( $this->term(), $this->person() ) )
		);
	}

	public function testChangingSubjectsOfASchemaWhoseSubjectsAreNotControlledNamesNoSchema(): void {
		$this->assertNull( $this->schemaChangedBy( new SubjectMap( $this->person() ), new SubjectMap() ) );
	}

	public function testASubjectWhoseSchemaDoesNotResolveIsNotControlled(): void {
		$this->assertNull( $this->schemaChangedBy( new SubjectMap(), new SubjectMap( $this->term( schema: 'Missing' ) ) ) );
	}

	public function testAPageMoveCanRenameAControlledSubjectWithoutALabel(): void {
		$this->assertSame(
			'Gender',
			$this->newRules()->controlledSchemaAPageMoveCanRename( new SubjectMap( $this->person(), $this->term( label: null ) ) )
				?->getText()
		);
	}

	public function testAPageMoveCannotRenameAControlledSubjectWithALabel(): void {
		$this->assertNull( $this->newRules()->controlledSchemaAPageMoveCanRename( new SubjectMap( $this->term() ) ) );
	}

	public function testAPageMoveRenamingASubjectThatIsNotControlledNamesNoSchema(): void {
		$this->assertNull(
			$this->newRules()->controlledSchemaAPageMoveCanRename( new SubjectMap( $this->term( label: null, schema: 'Person' ) ) )
		);
	}

	public function testDeclaringTheSubjectsControlledChangesTheDeclaration(): void {
		$this->assertTrue( $this->newRules()->changesDeclaration(
			TestSchema::build( name: 'Gender' ),
			TestSchema::build( name: 'Gender', subjectsAreControlled: true )
		) );
	}

	public function testNoLongerDeclaringTheSubjectsControlledChangesTheDeclaration(): void {
		$this->assertTrue( $this->newRules()->changesDeclaration(
			TestSchema::build( name: 'Gender', subjectsAreControlled: true ),
			TestSchema::build( name: 'Gender' )
		) );
	}

	public function testCreatingASchemaWithControlledSubjectsChangesTheDeclaration(): void {
		$this->assertTrue( $this->newRules()->changesDeclaration(
			null,
			TestSchema::build( name: 'Gender', subjectsAreControlled: true )
		) );
	}

	public function testEditingASchemaWithoutChangingTheDeclarationLeavesItUnchanged(): void {
		$this->assertFalse( $this->newRules()->changesDeclaration(
			TestSchema::build( name: 'Gender', description: 'Before', subjectsAreControlled: true ),
			TestSchema::build( name: 'Gender', description: 'After', subjectsAreControlled: true )
		) );
	}

}
