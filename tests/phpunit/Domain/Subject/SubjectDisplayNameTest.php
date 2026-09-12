<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName
 */
class SubjectDisplayNameTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111aaa';
	private const string OTHER_SUBJECT_ID = 's11111111111bbb';
	private const string SCHEMA_NAME = 'Schema name';

	private function newSubject( ?SubjectLabel $label, string $id = self::SUBJECT_ID ): Subject {
		return TestSubject::build( id: $id, label: $label, schemaName: new SchemaName( self::SCHEMA_NAME ) );
	}

	private function displayName( ?SubjectLabel $label, bool $isMainSubject, string $pageName = 'Page name' ): string {
		return SubjectDisplayName::forSubject(
			subject: $this->newSubject( $label ),
			isMainSubject: $isMainSubject,
			pageName: $pageName
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
			self::SCHEMA_NAME,
			$this->displayName( null, false )
		);
	}

	public function testMainSubjectWithoutPageNameFallsBackToSchemaName(): void {
		$this->assertSame(
			self::SCHEMA_NAME,
			$this->displayName( null, true, '' )
		);
	}

	public function testMainSubjectOnAPageTitledAfterItFallsBackToSchemaName(): void {
		$this->assertSame(
			self::SCHEMA_NAME,
			$this->displayName( null, true, ucfirst( self::SUBJECT_ID ) )
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
			subject: $this->newSubject( $label ),
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
		$this->assertSame( self::SCHEMA_NAME, $this->chosenName( new SubjectLabel( self::SCHEMA_NAME ), false ) );
	}

	/**
	 * The one case a client comparing the two strings gets wrong: the page title was chosen by
	 * whoever created the page, yet it equals the Schema name.
	 */
	public function testAPageTitledAfterItsSchemaIsStillAChosenName(): void {
		$this->assertSame( self::SCHEMA_NAME, $this->chosenName( null, true, self::SCHEMA_NAME ) );
	}

	/**
	 * Entity-first creation titles a page after a Subject when no label names it, so that title was
	 * chosen by nobody.
	 */
	public function testAPageTitledAfterTheSubjectItselfHasNoChosenName(): void {
		$this->assertNull( $this->chosenName( null, true, self::SUBJECT_ID ) );
	}

	public function testALabelEqualToASubjectIdIsStillChosen(): void {
		$this->assertSame(
			self::SUBJECT_ID,
			$this->chosenName( new SubjectLabel( self::SUBJECT_ID ), true, self::SUBJECT_ID )
		);
	}

	/**
	 * A wiki that capitalizes page titles - the default - stores the page an id titles under an
	 * upper-case S, so that is the title this rule most often meets.
	 */
	public function testAPageTitledAfterTheSubjectWithTheFirstLetterCapitalizedHasNoChosenName(): void {
		$this->assertNull( $this->chosenName( null, true, ucfirst( self::SUBJECT_ID ) ) );
	}

	/**
	 * Only a page titled after a Subject it holds counts. An ordinary word can read like an id -
	 * fifteen letters starting with an s - and naming a page after one must not unname its Subject.
	 */
	public function testAPageTitledLikeAnythingElseIsStillAChosenName(): void {
		$this->assertSame( 'Standardization', $this->chosenName( null, true, 'Standardization' ) );
	}

	private function chosenNameIn( Subject $subject, PageSubjects $pageSubjects, string $pageName ): ?string {
		return SubjectDisplayName::labelOrPageNameIn( $subject, $pageSubjects, $pageName );
	}

	public function testTheMainSubjectOfAPageTakesThePageNameAsItsChosenName(): void {
		$subject = $this->newSubject( null );

		$this->assertSame(
			'Standardization',
			$this->chosenNameIn( $subject, new PageSubjects( $subject, new SubjectMap() ), 'Standardization' )
		);
	}

	public function testTheMainSubjectOfAPageTitledAfterItHasNoChosenName(): void {
		$subject = $this->newSubject( null );

		$this->assertNull(
			$this->chosenNameIn(
				$subject,
				new PageSubjects( $subject, new SubjectMap() ),
				ucfirst( self::SUBJECT_ID )
			)
		);
	}

	/**
	 * Which Subject an id-titled page holds can change - a move, or another Subject pinned as the
	 * Main one - and the title is no more chosen than it was.
	 */
	public function testTheMainSubjectOfAPageTitledAfterAnotherSubjectOnItHasNoChosenName(): void {
		$mainSubject = $this->newSubject( null );

		$this->assertNull(
			$this->chosenNameIn(
				$mainSubject,
				new PageSubjects(
					$mainSubject,
					new SubjectMap( $this->newSubject( null, self::OTHER_SUBJECT_ID ) )
				),
				ucfirst( self::OTHER_SUBJECT_ID )
			)
		);
	}

	/**
	 * A Subject of another page has nothing to do with the title of this one.
	 */
	public function testAPageTitledAfterASubjectItDoesNotHoldIsStillAChosenName(): void {
		$subject = $this->newSubject( null );

		$this->assertSame(
			ucfirst( self::OTHER_SUBJECT_ID ),
			$this->chosenNameIn(
				$subject,
				new PageSubjects( $subject, new SubjectMap() ),
				ucfirst( self::OTHER_SUBJECT_ID )
			)
		);
	}

	public function testASubjectThatIsNotThePagesMainSubjectHasNoChosenName(): void {
		$subject = $this->newSubject( null );

		$this->assertNull(
			$this->chosenNameIn(
				$subject,
				new PageSubjects( $this->newSubject( null, self::OTHER_SUBJECT_ID ), new SubjectMap( $subject ) ),
				'Standardization'
			)
		);
	}

}
