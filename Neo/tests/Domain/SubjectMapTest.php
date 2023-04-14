<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap
 */
class SubjectMapTest extends TestCase {

	public function testGetSubject(): void {
		$subjectId = new SubjectId( '123' );
		$subject = TestSubject::build( $subjectId->text );
		$subjectMap = new SubjectMap( $subject );

		$this->assertEquals(
			$subject,
			$subjectMap->getSubject( $subjectId )
		);
	}

	public function testGetSubjectReturnsNullIfNotFound(): void {
		$subjectMap = new SubjectMap();
		$this->assertNull( $subjectMap->getSubject( new SubjectId( '123' ) ) );
	}

	public function testHasSubject(): void {
		$subjectId = new SubjectId( '123' );
		$subject = TestSubject::build( $subjectId->text );
		$subjectMap = new SubjectMap( $subject );

		$this->assertTrue(
			$subjectMap->hasSubject( $subjectId )
		);
	}

	public function testDoesNotHaveSubject(): void {
		$subjectMap = new SubjectMap( TestSubject::build( '123' ) );

		$this->assertFalse(
			$subjectMap->hasSubject( new SubjectId( '456' ) )
		);
	}

	public function testAsArray(): void {
		$subject1 = TestSubject::build( '123' );
		$subject2 = TestSubject::build( '456' );
		$subjectMap = new SubjectMap( $subject1, $subject2 );

		$this->assertEquals(
			[ $subject1, $subject2 ],
			$subjectMap->asArray()
		);
	}

	public function testAppend(): void {
		$subject1 = TestSubject::build( '123' );
		$subject2 = TestSubject::build( '456', label: new SubjectLabel( 'v1' ) );
		$subject2v2 = TestSubject::build( '456', label: new SubjectLabel( 'v2' ) );
		$subject3 = TestSubject::build( '789' );

		$subjectMap1 = new SubjectMap( $subject1, $subject2 );
		$subjectMap2 = new SubjectMap( $subject2v2, $subject3 );

		$subjectMap1->append( $subjectMap2 );

		$this->assertEquals(
			[ $subject1, $subject2v2, $subject3 ],
			$subjectMap1->asArray()
		);
	}

	public function testUpdateSubject(): void {
		$subjectId = new SubjectId( '123' );
		$subject1 = TestSubject::build( $subjectId->text );
		$subject2 = TestSubject::build( $subjectId->text, label: new SubjectLabel( 'Test subject 2' ) );

		$subjectMap = new SubjectMap(
			TestSubject::build( '456' ),
			$subject1,
			TestSubject::build( '789' ),
		);

		$subjectMap->addOrUpdateSubject( $subject2 );

		$this->assertEquals(
			$subject2,
			$subjectMap->getSubject( $subjectId )
		);
	}

	public function testGetIdsAsTextArray(): void {
		$subject1 = TestSubject::build( '123' );
		$subject2 = TestSubject::build( '456' );
		$subjectMap = new SubjectMap( $subject1, $subject2 );

		$this->assertEquals(
			[ '123', '456' ],
			$subjectMap->getIdsAsTextArray()
		);
	}

	public function testWithoutSubjectReturnsNewInstanceWithoutTheSubject(): void {
		$subject1 = TestSubject::build( '123' );
		$subject2 = TestSubject::build( '456' );
		$subject3 = TestSubject::build( '789' );

		$subjectMap = new SubjectMap( $subject1, $subject2, $subject3 );
		$newMap = $subjectMap->without( $subject2->id );

		$this->assertEquals(
			$newMap,
			new SubjectMap( $subject1, $subject3 )
		);

		$this->assertNotSame( $newMap, $subjectMap );
	}

	public function testWithoutNonExistentSubject(): void {
		$subject1 = TestSubject::build( '123' );
		$subject2 = TestSubject::build( '456' );
		$subject3 = TestSubject::build( '789' );

		$subjectMap = new SubjectMap( $subject1, $subject3 );
		$newMap = $subjectMap->without( $subject2->id );

		$this->assertEquals(
			$newMap,
			new SubjectMap( $subject1, $subject3 )
		);
	}

	public function withoutDoesNotMutate(): void {
		$subject1 = TestSubject::build( '123' );

		$subjectMap = new SubjectMap( $subject1 );
		$subjectMap->without( $subject1->id );

		$this->assertEquals(
			$subjectMap,
			new SubjectMap( $subject1 )
		);
	}

}
