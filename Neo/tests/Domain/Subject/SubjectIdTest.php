<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\Subject
 */
class SubjectIdTest extends TestCase {
	private const VALID_UUID = '00000000-0000-0000-0000-000000000001';
	private const INVALID_UUID = '0000-0000-000000000001';

	public function testInitialisationWithCorrectUuid(): void {
		$subjectId = new SubjectId( self::VALID_UUID );
		$this->assertEquals( self::VALID_UUID, $subjectId->text );
	}

	public function testInitialisationWithInvalidUuid(): void {
		$this->expectException( \InvalidArgumentException::class );
		$subjectId = new SubjectId( self::INVALID_UUID );
	}
}
