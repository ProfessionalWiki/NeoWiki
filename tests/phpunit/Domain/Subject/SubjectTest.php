<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\Subject
 */
class SubjectTest extends TestCase {

	public function testHasSameIdentity(): void {
		$firstSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );
		$secondSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$this->assertTrue( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

	public function testHasSameIdentityWithDifferentId(): void {
		$secondSubject = TestSubject::build( new SubjectId( 's11111111111111' ) );
		$firstSubject = TestSubject::build( new SubjectId( 's11111111111112' ) );

		$this->assertFalse( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

	public function testSetStatementsReplacesTheStatementList(): void {
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$newStatement = new Statement(
			new PropertyName( 'NewProp' ),
			'text',
			new StringValue( 'hello' )
		);
		$newStatementList = new StatementList( [ $newStatement ] );

		$subject->setStatements( $newStatementList );

		$this->assertSame( $newStatementList, $subject->getStatements() );
	}

	public function testWithStatementsKeepsIdLabelAndSchema(): void {
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$copy = $subject->withStatements( new StatementList( [] ) );

		$this->assertSame( $subject->getId(), $copy->getId() );
		$this->assertSame( $subject->getLabel(), $copy->getLabel() );
		$this->assertSame( $subject->getSchemaName(), $copy->getSchemaName() );
	}

	public function testWithStatementsLeavesTheOriginalStatementsInPlace(): void {
		$originalStatements = new StatementList( [
			new Statement( new PropertyName( 'Original' ), 'text', new StringValue( 'hello' ) ),
		] );
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ), statements: $originalStatements );

		$copy = $subject->withStatements( new StatementList( [] ) );

		$this->assertSame( $originalStatements, $subject->getStatements() );
		$this->assertSame( [], $copy->getStatements()->asArray() );
	}

	public function testStatementOrderIsNotContent(): void {
		$this->assertTrue(
			$this->newCompany( 'ACME', [ 'Motto' => 'Make things', 'Website' => 'acme.example' ] )->hasSameContentAs(
				$this->newCompany( 'ACME', [ 'Website' => 'acme.example', 'Motto' => 'Make things' ] )
			)
		);
	}

	public function testAnotherLabelIsOtherContent(): void {
		$this->assertFalse(
			$this->newCompany( 'ACME', [] )->hasSameContentAs( $this->newCompany( 'ACME Inc', [] ) )
		);
	}

	public function testNoLabelIsOtherContentThanALabel(): void {
		$this->assertFalse(
			$this->newCompany( null, [] )->hasSameContentAs( $this->newCompany( 'ACME', [] ) )
		);
	}

	public function testAnotherStatementValueIsOtherContent(): void {
		$this->assertFalse(
			$this->newCompany( 'ACME', [ 'Motto' => 'Make things' ] )->hasSameContentAs(
				$this->newCompany( 'ACME', [ 'Motto' => 'Break things' ] )
			)
		);
	}

	public function testAnAddedStatementIsOtherContent(): void {
		$this->assertFalse(
			$this->newCompany( 'ACME', [ 'Motto' => 'Make things' ] )->hasSameContentAs(
				$this->newCompany( 'ACME', [ 'Motto' => 'Make things', 'Website' => 'acme.example' ] )
			)
		);
	}

	public function testAnotherSchemaIsOtherContent(): void {
		$this->assertFalse(
			TestSubject::build( schemaName: new SchemaName( 'Company' ) )->hasSameContentAs(
				TestSubject::build( schemaName: new SchemaName( 'Person' ) )
			)
		);
	}

	/**
	 * @param array<string, string> $statements
	 */
	private function newCompany( ?string $label, array $statements ): Subject {
		return TestSubject::build(
			label: $label,
			statements: new StatementList( array_map(
				static fn ( string $property, string $value ): Statement => TestStatement::build( $property, $value ),
				array_keys( $statements ),
				$statements
			) )
		);
	}

}
