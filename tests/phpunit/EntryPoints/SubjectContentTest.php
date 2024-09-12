<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SubjectContent
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

}
