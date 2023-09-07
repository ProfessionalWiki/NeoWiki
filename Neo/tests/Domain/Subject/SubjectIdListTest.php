<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

namespace Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList
 */
class SubjectIdListTest extends TestCase {

	public function testAsStringArray(): void {
		$ids = new SubjectIdList( [
			new SubjectId( '00000000-0000-0000-0000-000000000001' ),
			new SubjectId( '00000000-0000-0000-0000-000000000002' ),
			new SubjectId( '00000000-0000-0000-0000-000000000001' ),
		] );

		$this->assertSame(
			[
				'00000000-0000-0000-0000-000000000001',
				'00000000-0000-0000-0000-000000000002',
			],
			$ids->asStringArray()
		);
	}

}
