<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentData
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
			new SubjectContentData(
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

		$content->setContentData(
			new SubjectContentData(
				mainSubject: TestSubject::build( id: TestSubject::ZERO_GUID ),
				childSubjects: new SubjectMap()
			)
		);

		$this->assertSame(
			TestSubject::ZERO_GUID,
			$content->getContentData()->getMainSubject()->id->text
		);
	}

}
