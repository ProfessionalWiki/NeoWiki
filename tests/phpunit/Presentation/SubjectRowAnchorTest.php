<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Presentation\SubjectRowAnchor;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectRowAnchor
 */
class SubjectRowAnchorTest extends TestCase {

	/**
	 * The same literal example is asserted by the TypeScript counterpart
	 * (resources/ext.neowiki/tests/presentation/subjectRowDomId.spec.ts). Keep them identical so a
	 * prefix change on either side breaks a test.
	 */
	public function testDomIdPrefixesTheSubjectId(): void {
		$this->assertSame(
			'ext-neowiki-subject-row-s12345abcdefghj',
			SubjectRowAnchor::domId( 's12345abcdefghj' )
		);
	}

}
