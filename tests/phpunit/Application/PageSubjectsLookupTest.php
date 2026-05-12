<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup
 */
class PageSubjectsLookupTest extends TestCase {

	private const PAGE_ID = 42;
	private const OTHER_PAGE_ID = 43;

	public function testPageHasSubjectsIsTrueWhenMainSubjectExists(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertTrue( $lookup->pageHasSubjects( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasSubjectsIsTrueWhenOnlyChildSubjectsExist(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build() ) )
		);

		$this->assertTrue( $lookup->pageHasSubjects( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasSubjectsIsFalseForOtherPage(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertFalse( $lookup->pageHasSubjects( new PageId( self::OTHER_PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsTrueWhenMainSubjectExists(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertTrue( $lookup->pageHasMainSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsFalseWhenOnlyChildSubjectsExist(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build() ) )
		);

		$this->assertFalse( $lookup->pageHasMainSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsFalseForOtherPage(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertFalse( $lookup->pageHasMainSubject( new PageId( self::OTHER_PAGE_ID ) ) );
	}

	private function newLookupWithSubjects( PageSubjects $subjects ): PageSubjectsLookup {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects( $subjects, new PageId( self::PAGE_ID ) );

		return new PageSubjectsLookup( $repository );
	}

}
