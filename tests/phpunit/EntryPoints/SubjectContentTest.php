<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent
 * @covers \ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects
 */
class SubjectContentTest extends TestCase {

	public function testNewContentDoesNotHaveSubjects(): void {
		$this->assertFalse( SubjectContent::newEmpty()->hasSubjects() );
	}

	public function testContentHasSubjects(): void {
		$this->assertTrue( $this->newContentWithMainSubject()->hasSubjects() );
	}

	private function newContentWithMainSubject(): SubjectContent {
		return SubjectContent::newFromData(
			new PageSubjects(
				mainSubject: TestSubject::build(),
				childSubjects: new SubjectMap()
			)
		);
	}

	public function testContentWithSubjectIsNotEmpty(): void {
		$this->assertFalse( $this->newContentWithMainSubject()->isEmpty() );
	}

	public function testNewContentIsEmpty(): void {
		$this->assertTrue( SubjectContent::newEmpty()->isEmpty() );
	}

	public function testCanModifyData(): void {
		$content = SubjectContent::newEmpty();

		$content->setPageSubjects(
			new PageSubjects(
				mainSubject: TestSubject::build( id: TestSubject::ZERO_GUID ),
				childSubjects: new SubjectMap()
			)
		);

		$this->assertSame(
			TestSubject::ZERO_GUID,
			$content->getPageSubjects()->getMainSubject()->id->text
		);
	}

	public function testMutatePageSubjects(): void {
		$content = SubjectContent::newFromData(
			new PageSubjects(
				mainSubject: TestSubject::build( id: TestSubject::ZERO_GUID ),
				childSubjects: new SubjectMap()
			)
		);

		$content->mutatePageSubjects( function( PageSubjects $subjects ): void {
			$subjects->removeSubject( new SubjectId( TestSubject::ZERO_GUID ) );
		} );

		$this->assertNull( $content->getPageSubjects()->getMainSubject() );
	}

	public function testSubjectIdsAreReadWithoutDeserializing(): void {
		$content = new SubjectContent( TestSubject::jsonThatDoesNotDeserialize( TestSubject::ZERO_GUID ) );

		$this->assertSame( [ TestSubject::ZERO_GUID ], $content->getSubjectIds() );
	}

	public function testSubjectIdsLeaveOutKeysThatAreNotSubjectIds(): void {
		$content = new SubjectContent(
			'{"subjects":{"not an id":{},"' . TestSubject::ZERO_GUID . '":{}}}'
		);

		$this->assertSame( [ TestSubject::ZERO_GUID ], $content->getSubjectIds() );
	}

	/**
	 * The subject-to-page index keys ids bare, as stored (ADR 32), and a Subject from another Source
	 * is not stored in a local slot: no local page holds it. A qualified key is also longer than the
	 * index column, so admitting one would truncate rather than record it.
	 */
	public function testSubjectIdsLeaveOutAKeyNamingAnotherSource(): void {
		$content = new SubjectContent(
			'{"subjects":{"otherwiki:Q42":{},"' . TestSubject::ZERO_GUID . '":{}}}'
		);

		$this->assertSame( [ TestSubject::ZERO_GUID ], $content->getSubjectIds() );
	}

	public function testSubjectIdsLeaveOutAKeyLongerThanTheIndexColumn(): void {
		$content = new SubjectContent(
			'{"subjects":{"' . str_repeat( 'a', 64 ) . ':' . str_repeat( 'b', 256 ) . '":{}}}'
		);

		$this->assertSame( [], $content->getSubjectIds() );
	}

	public function testContentThatIsNotSubjectJsonHoldsNoSubjectIds(): void {
		$this->assertSame( [], ( new SubjectContent( 'not json at all' ) )->getSubjectIds() );
	}

}
