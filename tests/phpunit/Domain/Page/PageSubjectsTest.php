<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Page;

use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use RuntimeException;

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
		$this->assertEquals( TestSubject::newMap(), $data->getOtherSubjects() );
	}

	public function testRemoveOtherSubject(): void {
		$mainSubject = TestSubject::build( TestSubject::uniqueId() );
		$firstOther = TestSubject::build( TestSubject::uniqueId() );
		$secondOther = TestSubject::build( TestSubject::uniqueId() );
		$thirdOther = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects(
			$mainSubject,
			new SubjectMap( $firstOther, $secondOther, $thirdOther )
		);

		$data->removeSubject( $secondOther->id );

		$this->assertSame( $mainSubject, $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $firstOther, $thirdOther ),
			$data->getOtherSubjects()
		);
	}

	public function testWithoutOtherSubjectAnswersACopyLackingIt(): void {
		$mainSubject = TestSubject::build( TestSubject::uniqueId() );
		$firstOther = TestSubject::build( TestSubject::uniqueId() );
		$secondOther = TestSubject::build( TestSubject::uniqueId() );
		$thirdOther = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $mainSubject, new SubjectMap( $firstOther, $secondOther, $thirdOther ) );

		$remaining = $data->without( $secondOther->id );

		$this->assertSame( $mainSubject, $remaining->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $firstOther, $thirdOther ),
			$remaining->getOtherSubjects()
		);
	}

	public function testWithoutMainSubjectAnswersACopyWithoutOne(): void {
		$firstOther = TestSubject::build( TestSubject::uniqueId() );
		$secondOther = TestSubject::build( TestSubject::uniqueId() );
		$mainSubject = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $mainSubject, new SubjectMap( $firstOther, $secondOther ) );

		$remaining = $data->without( $mainSubject->id );

		$this->assertNull( $remaining->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $firstOther, $secondOther ),
			$remaining->getOtherSubjects()
		);
	}

	public function testWithoutLeavesTheSubjectsItWasCalledOnAlone(): void {
		// Moving a Subject keeps the page as it was read, so a failed move can write it back.
		$mainSubject = TestSubject::build( TestSubject::uniqueId() );
		$otherSubject = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $mainSubject, new SubjectMap( $otherSubject ) );

		$data->without( $mainSubject->id );

		$this->assertSame( $mainSubject, $data->getMainSubject() );
		$this->assertEquals( new SubjectMap( $otherSubject ), $data->getOtherSubjects() );
	}

	public function testWithoutAnIdThatIsNotOnThePageAnswersAnEqualCopy(): void {
		$data = new PageSubjects(
			TestSubject::build( TestSubject::uniqueId() ),
			new SubjectMap( TestSubject::build( TestSubject::uniqueId() ) )
		);

		$this->assertEquals( $data, $data->without( TestSubject::uniqueId() ) );
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

	public function testUpdateSubjectUpdatesOtherSubject(): void {
		$firstOther = TestSubject::build( TestSubject::uniqueId() );
		$secondOther = TestSubject::build( TestSubject::uniqueId(), new SubjectLabel( 'original' ) );
		$thirdOther = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects(
			TestSubject::build( TestSubject::uniqueId() ),
			new SubjectMap( $firstOther, $secondOther, $thirdOther )
		);

		$updatedSubject = TestSubject::build( $secondOther->id->text, new SubjectLabel( 'updated' ) );

		$data->updateSubject( $updatedSubject );

		$this->assertEquals(
			new SubjectMap( $firstOther, $updatedSubject, $thirdOther ),
			$data->getOtherSubjects()
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

	public function testSetOrderingReordersOtherSubjects(): void {
		$main = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );
		$third = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $main, new SubjectMap( $first, $second, $third ) );

		$data->setOrdering( $main->id, [ $third->id, $first->id, $second->id ] );

		$this->assertSame( $main, $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $third, $first, $second ),
			$data->getOtherSubjects()
		);
	}

	public function testSetOrderingPromotesAndSwapsMainIntoDroppedSlot(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );
		$third = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first, $second, $third ) );

		// Promote $second; previous main lands in $second's old slot.
		$data->setOrdering( $second->id, [ $first->id, $oldMain->id, $third->id ] );

		$this->assertSame( $second, $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $first, $oldMain, $third ),
			$data->getOtherSubjects()
		);
	}

	public function testSetOrderingDemotesMainAtChosenPosition(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first, $second ) );

		$data->setOrdering( null, [ $first->id, $oldMain->id, $second->id ] );

		$this->assertNull( $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $first, $oldMain, $second ),
			$data->getOtherSubjects()
		);
	}

	public function testSetOrderingThrowsWhenMainIdNotPresent(): void {
		$data = new PageSubjects(
			TestSubject::build( TestSubject::uniqueId() ),
			TestSubject::newMap()
		);

		$this->expectException( InvalidArgumentException::class );
		$data->setOrdering( TestSubject::uniqueId(), [] );
	}

	public function testSetOrderingThrowsWhenTheOtherSubjectOrderingMissesAnId(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first, $second ) );

		$this->expectException( InvalidArgumentException::class );
		// Forgot to include $second.
		$data->setOrdering( $oldMain->id, [ $first->id ] );
	}

	public function testSetOrderingThrowsWhenTheOtherSubjectOrderingIncludesMain(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first ) );

		$this->expectException( InvalidArgumentException::class );
		// Same id appearing as main AND in the other-subject ordering is illegal.
		$data->setOrdering( $oldMain->id, [ $first->id, $oldMain->id ] );
	}

	public function testCreateOtherSubjectAddsTheSubject(): void {
		$data = new PageSubjects( TestSubject::build( TestSubject::uniqueId() ), new SubjectMap() );

		$newOther = TestSubject::build( TestSubject::uniqueId() );
		$data->createOtherSubject( $newOther );

		$this->assertEquals( new SubjectMap( $newOther ), $data->getOtherSubjects() );
	}

	public function testCreateOtherSubjectThrowsWhenIdMatchesAnExistingOtherSubject(): void {
		$existingOther = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( null, new SubjectMap( $existingOther ) );

		$this->expectException( RuntimeException::class );
		$data->createOtherSubject( TestSubject::build( $existingOther->id ) );
	}

	public function testCreateOtherSubjectThrowsWhenIdMatchesTheMainSubject(): void {
		$main = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( $main, new SubjectMap() );

		$this->expectException( RuntimeException::class );
		// Regression: the guard previously checked only the page's other Subjects, missing the main Subject.
		$data->createOtherSubject( TestSubject::build( $main->id ) );
	}

	public function testCreateMainSubjectSetsTheMainSubject(): void {
		$data = new PageSubjects( null, new SubjectMap() );

		$newMain = TestSubject::build( TestSubject::uniqueId() );
		$data->createMainSubject( $newMain );

		$this->assertSame( $newMain, $data->getMainSubject() );
	}

	public function testCreateMainSubjectThrowsWhenMainAlreadyExists(): void {
		$data = new PageSubjects( TestSubject::build( TestSubject::uniqueId() ), new SubjectMap() );

		$this->expectException( RuntimeException::class );
		$data->createMainSubject( TestSubject::build( TestSubject::uniqueId() ) );
	}

	public function testCreateMainSubjectThrowsWhenIdMatchesAnExistingOtherSubject(): void {
		$existingOther = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( null, new SubjectMap( $existingOther ) );

		$this->expectException( RuntimeException::class );
		// Regression: a main Subject must not reuse an id already held by one of the page's other Subjects.
		$data->createMainSubject( TestSubject::build( $existingOther->id ) );
	}

}
