<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
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

	public function testSubjectHeadersAreReadWithoutDeserializing(): void {
		$content = new SubjectContent( TestSubject::jsonThatDoesNotDeserialize( TestSubject::ZERO_GUID ) );

		$this->assertSame( [ TestSubject::ZERO_GUID ], $this->idsOf( $content ) );
	}

	public function testSubjectHeadersLeaveOutKeysThatAreNotSubjectIds(): void {
		$content = new SubjectContent(
			'{"subjects":{"not an id":{},"' . TestSubject::ZERO_GUID . '":{}}}'
		);

		$this->assertSame( [ TestSubject::ZERO_GUID ], $this->idsOf( $content ) );
	}

	public function testContentThatIsNotSubjectJsonHoldsNoSubjectHeaders(): void {
		$this->assertSame( [], ( new SubjectContent( 'not json at all' ) )->getSubjectHeaders() );
	}

	/**
	 * @return string[]
	 */
	private function idsOf( SubjectContent $content ): array {
		return array_map(
			static fn ( SubjectHeader $header ): string => $header->id,
			$content->getSubjectHeaders()
		);
	}

}
