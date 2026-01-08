<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\Subject
 */
class SubjectTest extends TestCase {

	public function testHasSameIdentity(): void {
		$firstSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );
		$secondSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$this->assertTrue( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

	public function testHasSameIdentityWithDifferentId(): void {
		$secondSubject = TestSubject::build( new SubjectId( 's11111111111111' ) );
		$firstSubject = TestSubject::build( new SubjectId( 's11111111111112' ) );

		$this->assertFalse( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

}
