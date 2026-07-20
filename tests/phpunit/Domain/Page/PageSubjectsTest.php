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

	public function testSetOrderingReordersChildSubjects(): void {
		$main = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );
		$third = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $main, new SubjectMap( $first, $second, $third ) );

		$data->setOrdering( $main->id, [ $third->id, $first->id, $second->id ] );

		$this->assertSame( $main, $data->getMainSubject() );
		$this->assertEquals(
			new SubjectMap( $third, $first, $second ),
			$data->getChildSubjects()
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
			$data->getChildSubjects()
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
			$data->getChildSubjects()
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

	public function testSetOrderingThrowsWhenChildOrderingMissesAnId(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );
		$second = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first, $second ) );

		$this->expectException( InvalidArgumentException::class );
		// Forgot to include $second.
		$data->setOrdering( $oldMain->id, [ $first->id ] );
	}

	public function testSetOrderingThrowsWhenChildOrderingIncludesMain(): void {
		$oldMain = TestSubject::build( TestSubject::uniqueId() );
		$first = TestSubject::build( TestSubject::uniqueId() );

		$data = new PageSubjects( $oldMain, new SubjectMap( $first ) );

		$this->expectException( InvalidArgumentException::class );
		// Same id appearing as main AND in the child ordering is illegal.
		$data->setOrdering( $oldMain->id, [ $first->id, $oldMain->id ] );
	}

	public function testCreateChildSubjectAddsTheSubject(): void {
		$data = new PageSubjects( TestSubject::build( TestSubject::uniqueId() ), new SubjectMap() );

		$newChild = TestSubject::build( TestSubject::uniqueId() );
		$data->createChildSubject( $newChild );

		$this->assertEquals( new SubjectMap( $newChild ), $data->getChildSubjects() );
	}

	public function testCreateChildSubjectThrowsWhenIdMatchesAnExistingChild(): void {
		$existingChild = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( null, new SubjectMap( $existingChild ) );

		$this->expectException( RuntimeException::class );
		$data->createChildSubject( TestSubject::build( $existingChild->id ) );
	}

	public function testCreateChildSubjectThrowsWhenIdMatchesTheMainSubject(): void {
		$main = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( $main, new SubjectMap() );

		$this->expectException( RuntimeException::class );
		// Regression: the guard previously checked only sibling children, missing the main Subject.
		$data->createChildSubject( TestSubject::build( $main->id ) );
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

	public function testCreateMainSubjectThrowsWhenIdMatchesAnExistingChild(): void {
		$existingChild = TestSubject::build( TestSubject::uniqueId() );
		$data = new PageSubjects( null, new SubjectMap( $existingChild ) );

		$this->expectException( RuntimeException::class );
		// Regression: a main Subject must not reuse an id already held by a child.
		$data->createMainSubject( TestSubject::build( $existingChild->id ) );
	}

}
