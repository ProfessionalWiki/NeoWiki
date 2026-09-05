<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName
 */
class SubjectDisplayNameTest extends TestCase {

	private function displayName( ?SubjectLabel $label, bool $isMainSubject, string $pageName = 'Page name' ): string {
		return SubjectDisplayName::forSubject(
			label: $label,
			isMainSubject: $isMainSubject,
			pageName: $pageName,
			schemaName: new SchemaName( 'Schema name' )
		);
	}

	public function testStoredLabelWinsForMainSubject(): void {
		$this->assertSame(
			'Stored',
			$this->displayName( new SubjectLabel( 'Stored' ), true )
		);
	}

	public function testStoredLabelWinsForChildSubject(): void {
		$this->assertSame(
			'Stored',
			$this->displayName( new SubjectLabel( 'Stored' ), false )
		);
	}

	public function testMainSubjectWithoutLabelFallsBackToPageName(): void {
		$this->assertSame(
			'Page name',
			$this->displayName( null, true )
		);
	}

	public function testChildSubjectWithoutLabelFallsBackToSchemaName(): void {
		$this->assertSame(
			'Schema name',
			$this->displayName( null, false )
		);
	}

	public function testMainSubjectWithoutPageNameFallsBackToSchemaName(): void {
		$this->assertSame(
			'Schema name',
			$this->displayName( null, true, '' )
		);
	}

	public function testSurroundingWhitespaceOfAStoredLabelIsPreserved(): void {
		$this->assertSame(
			'  Stored  ',
			$this->displayName( new SubjectLabel( '  Stored  ' ), false )
		);
	}

	private function chosenName( ?SubjectLabel $label, bool $isMainSubject, string $pageName = 'Page name' ): ?string {
		return SubjectDisplayName::labelOrPageName(
			label: $label,
			isMainSubject: $isMainSubject,
			pageName: $pageName
		);
	}

	public function testStoredLabelIsTheChosenNameForMainSubject(): void {
		$this->assertSame( 'Stored', $this->chosenName( new SubjectLabel( 'Stored' ), true ) );
	}

	public function testStoredLabelIsTheChosenNameForChildSubject(): void {
		$this->assertSame( 'Stored', $this->chosenName( new SubjectLabel( 'Stored' ), false ) );
	}

	public function testTheMainSubjectTakesThePageNameAsItsChosenName(): void {
		$this->assertSame( 'Page name', $this->chosenName( null, true ) );
	}

	/**
	 * Null is what makes the Schema tier the one nobody chose, and it is the whole answer a caller
	 * needs: the name it produces and the verdict on that name come from this one value.
	 */
	public function testAChildWithoutALabelHasNoChosenName(): void {
		$this->assertNull( $this->chosenName( null, false ) );
	}

	public function testAMainSubjectWithoutAPageNameHasNoChosenName(): void {
		$this->assertNull( $this->chosenName( null, true, '' ) );
	}

	/**
	 * A label someone typed is chosen even when they typed the Schema name. This and the case below
	 * are why the tier cannot be recovered by comparing the name against the Schema name.
	 */
	public function testAStoredLabelEqualToTheSchemaNameIsStillChosen(): void {
		$this->assertSame( 'Schema name', $this->chosenName( new SubjectLabel( 'Schema name' ), false ) );
	}

	/**
	 * The one case a client comparing the two strings gets wrong: the page title was chosen by
	 * whoever created the page, yet it equals the Schema name.
	 */
	public function testAPageTitledAfterItsSchemaIsStillAChosenName(): void {
		$this->assertSame( 'Schema name', $this->chosenName( null, true, 'Schema name' ) );
	}

}
