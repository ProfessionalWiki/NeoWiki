<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;
use ProfessionalWiki\NeoWiki\Tests\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent
 */
class SubjectContentTest extends TestCase {

	public function testNewFromSubjects(): void {
		$subjects = TestSubject::newMap();

		$this->assertEquals(
			$subjects,
			SubjectContent::newFromSubjects( $subjects )->getSubjects()
		);
	}

}
