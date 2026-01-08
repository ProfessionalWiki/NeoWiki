<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList
 */
class SubjectIdListTest extends TestCase {

	public function testAsStringArray(): void {
		$ids = new SubjectIdList( [
			new SubjectId( 's1111111111a123' ),
			new SubjectId( 's1111111111a456' ),
			new SubjectId( 's1111111111a123' ),
		] );

		$this->assertSame(
			[
				's1111111111a123',
				's1111111111a456',
			],
			$ids->asStringArray()
		);
	}

}
