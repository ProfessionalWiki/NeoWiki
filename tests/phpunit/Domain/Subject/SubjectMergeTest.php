<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMerge;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMerge
 */
class SubjectMergeTest extends TestCase {

	private const string HOST = 's11111111111111';
	private const string DEPENDENT = 's11111111111112';

	public function testMergesStatementsEachSideChangedAlone(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ), $this->text( 'Status', 'Active' ) ),
			latest: $this->host( 'Acme', $this->text( 'Founded', '2005' ), $this->text( 'Status', 'Defunct' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2006' ), $this->text( 'Status', 'Active' ) ),
		);

		$this->assertEquals(
			$this->host( 'Acme', $this->text( 'Founded', '2006' ), $this->text( 'Status', 'Defunct' ) ),
			$merged
		);
	}

	public function testRefusesAStatementBothSidesChangedDifferently(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ) ),
			latest: $this->host( 'Acme', $this->text( 'Founded', '2004' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2006' ) ),
		);

		$this->assertNull( $merged );
	}

	public function testAcceptsAStatementBothSidesChangedAlike(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ) ),
			latest: $this->host( 'Acme', $this->text( 'Founded', '2004' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2004' ) ),
		);

		$this->assertEquals( $this->host( 'Acme', $this->text( 'Founded', '2004' ) ), $merged );
	}

	public function testKeepsStatementsEachSideAdded(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme' ),
			latest: $this->host( 'Acme', $this->text( 'Motto', 'Make things' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2006' ) ),
		);

		$this->assertEquals(
			$this->host( 'Acme', $this->text( 'Founded', '2006' ), $this->text( 'Motto', 'Make things' ) ),
			$merged
		);
	}

	public function testRemovesAStatementOnlyTheProposedWriteRemoved(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ), $this->text( 'Status', 'Active' ) ),
			latest: $this->host( 'Acme', $this->text( 'Founded', '2004' ), $this->text( 'Status', 'Active' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2005' ) ),
		);

		$this->assertEquals( $this->host( 'Acme', $this->text( 'Founded', '2004' ) ), $merged );
	}

	public function testRefusesRemovingAStatementTheLatestRevisionChanged(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ) ),
			latest: $this->host( 'Acme', $this->text( 'Founded', '2004' ) ),
			proposed: $this->host( 'Acme' ),
		);

		$this->assertNull( $merged );
	}

	public function testTakesALabelOnlyTheLatestRevisionChanged(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme', $this->text( 'Founded', '2005' ) ),
			latest: $this->host( 'ACME Inc', $this->text( 'Founded', '2005' ) ),
			proposed: $this->host( 'Acme', $this->text( 'Founded', '2006' ) ),
		);

		$this->assertEquals( $this->host( 'ACME Inc', $this->text( 'Founded', '2006' ) ), $merged );
	}

	public function testRefusesALabelBothSidesChangedDifferently(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Acme' ),
			latest: $this->host( 'ACME Inc' ),
			proposed: $this->host( 'Acme Corporation' ),
		);

		$this->assertNull( $merged );
	}

	public function testKeepsADependentSubjectTheLatestRevisionAdded(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Rijksmuseum', $this->text( 'Founded', '1800' ) ),
			latest: $this->withDependent( [ $this->text( 'Year', '2025' ) ], $this->text( 'Founded', '1800' ) ),
			proposed: $this->host( 'Rijksmuseum', $this->text( 'Founded', '1798' ) ),
		);

		$this->assertEquals( $this->withDependent( [ $this->text( 'Year', '2025' ) ], $this->text( 'Founded', '1798' ) ), $merged );
	}

	public function testKeepsADependentSubjectTheWriteAdded(): void {
		$merged = SubjectMerge::merge(
			base: $this->host( 'Rijksmuseum', $this->text( 'Founded', '1800' ) ),
			latest: $this->host( 'Rijksmuseum', $this->text( 'Founded', '1798' ) ),
			proposed: $this->withDependent( [ $this->text( 'Year', '2025' ) ], $this->text( 'Founded', '1800' ) ),
		);

		$this->assertEquals( $this->withDependent( [ $this->text( 'Year', '2025' ) ], $this->text( 'Founded', '1798' ) ), $merged );
	}

	public function testMergesFieldsOfADependentSubjectEachSideChangedAlone(): void {
		$merged = SubjectMerge::merge(
			base: $this->withDependent( [ $this->text( 'Visitors', '1760000' ), $this->text( 'Source', 'a' ) ] ),
			latest: $this->withDependent( [ $this->text( 'Visitors', '1770000' ), $this->text( 'Source', 'a' ) ] ),
			proposed: $this->withDependent( [ $this->text( 'Visitors', '1760000' ), $this->text( 'Source', 'b' ) ] ),
		);

		$this->assertEquals(
			$this->withDependent( [ $this->text( 'Visitors', '1770000' ), $this->text( 'Source', 'b' ) ] ),
			$merged
		);
	}

	public function testDropsADependentSubjectTheLatestRevisionRemovedAndTheWriteLeftAsItWas(): void {
		$merged = SubjectMerge::merge(
			base: $this->withDependent( [ $this->text( 'Year', '2021' ) ], $this->text( 'Founded', '1800' ) ),
			latest: $this->host( 'Rijksmuseum', $this->text( 'Founded', '1800' ) ),
			proposed: $this->withDependent( [ $this->text( 'Year', '2021' ) ], $this->text( 'Founded', '1798' ) ),
		);

		$this->assertEquals( $this->host( 'Rijksmuseum', $this->text( 'Founded', '1798' ) ), $merged );
	}

	public function testRefusesKeepingADependentSubjectTheLatestRevisionRemovedAndTheWriteChanged(): void {
		$merged = SubjectMerge::merge(
			base: $this->withDependent( [ $this->text( 'Source', 'a' ) ] ),
			latest: $this->host( 'Rijksmuseum' ),
			proposed: $this->withDependent( [ $this->text( 'Source', 'b' ) ] ),
		);

		$this->assertNull( $merged );
	}

	public function testRefusesRemovingADependentSubjectTheLatestRevisionChanged(): void {
		$merged = SubjectMerge::merge(
			base: $this->withDependent( [ $this->text( 'Visitors', '2700000' ) ] ),
			latest: $this->withDependent( [ $this->text( 'Visitors', '2750000' ) ] ),
			proposed: $this->host( 'Rijksmuseum' ),
		);

		$this->assertNull( $merged );
	}

	private function host( string $label, Statement ...$statements ): SubjectMap {
		return new SubjectMap( TestSubject::build( id: self::HOST, label: $label, statements: new StatementList( $statements ) ) );
	}

	/**
	 * The Host Subject Rijksmuseum, pointing at one Dependent Subject.
	 *
	 * @param Statement[] $dependentStatements
	 */
	private function withDependent( array $dependentStatements, Statement ...$hostStatements ): SubjectMap {
		return new SubjectMap(
			TestSubject::build(
				id: self::HOST,
				label: 'Rijksmuseum',
				statements: new StatementList( [
					...$hostStatements,
					TestStatement::buildRelation( 'Attendance figures', [ TestRelation::build( 'r11111111111111', self::DEPENDENT ) ] ),
				] )
			),
			TestSubject::build( id: self::DEPENDENT, label: null, statements: new StatementList( $dependentStatements ) ),
		);
	}

	private function text( string $property, string $value ): Statement {
		return TestStatement::build( $property, $value );
	}

}
