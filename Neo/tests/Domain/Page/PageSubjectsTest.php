<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Page;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects
 */
class PageSubjectsTest extends TestCase {

	public function testGetAllSubjectsReturnsMainSubjectFirst(): void {
		$data = new PageSubjects(
			TestSubject::build( id: TestSubject::ZERO_GUID ),
			TestSubject::newMap()
		);

		$this->assertSame(
			TestSubject::ZERO_GUID,
			$data->getAllSubjects()->asArray()[0]->id->text
		);
	}

	public function testRemoveMainSubject(): void {
		$data = new PageSubjects(
			TestSubject::build( id: TestSubject::ZERO_GUID ),
			TestSubject::newMap()
		);

		$data->removeSubject( new SubjectId( TestSubject::ZERO_GUID ) );

		$this->assertNull( $data->getMainSubject() );
		$this->assertEquals( TestSubject::newMap(), $data->getChildSubjects() );
	}

	public function testRemoveChildSubject(): void {
		$mainSubject = TestSubject::build( TestSubject::uniqueId() );
		$firstChild = TestSubject::build( TestSubject::uniqueId() );
		$secondChild = TestSubject::build( TestSubject::uniqueId() );
		$thirdChild = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects(
			$mainSubject,
			new SubjectMap( $firstChild, $secondChild, $thirdChild )
		);

		$data->removeSubject( $secondChild->id );

		$this->assertSame( $mainSubject, $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $firstChild, $thirdChild ),
			$data->getChildSubjects()
		);
	}

	public function testUpdateSubjectUpdatesTheMainSubject(): void {
		$mainSubject = TestSubject::build( TestSubject::uniqueId(), new SubjectLabel( 'original' ) );
		$updatedSubject = TestSubject::build( $mainSubject->id->text, new SubjectLabel( 'updated' ) );

		$data = new PageSubjects(
			$mainSubject,
			TestSubject::newMap()
		);

		$data->updateSubject( $updatedSubject );

		$this->assertSame( $updatedSubject, $data->getMainSubject() );
	}

	public function testUpdateSubjectUpdatesChildSubject(): void {
		$firstChild = TestSubject::build( TestSubject::uniqueId() );
		$secondChild = TestSubject::build( TestSubject::uniqueId(), new SubjectLabel( 'original' ) );
		$thirdChild = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects(
			TestSubject::build( TestSubject::uniqueId() ),
			new SubjectMap( $firstChild, $secondChild, $thirdChild )
		);

		$updatedSubject = TestSubject::build( $secondChild->id->text, new SubjectLabel( 'updated' ) );

		$data->updateSubject( $updatedSubject );

		$this->assertEquals(
			new SubjectMap( $firstChild, $updatedSubject, $thirdChild ),
			$data->getChildSubjects()
		);
	}

	public function testUpdateSubjectThrowsExceptionWhenSubjectIsNotFound(): void {
		$data = new PageSubjects(
			TestSubject::build( TestSubject::uniqueId() ),
			TestSubject::newMap()
		);

		$this->expectException( OutOfBoundsException::class );
		$data->updateSubject( TestSubject::build( TestSubject::uniqueId() ) );
	}

}
