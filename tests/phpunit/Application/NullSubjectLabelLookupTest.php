<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup
 */
class NullSubjectLabelLookupTest extends TestCase {

	public function testReturnsNoMatches(): void {
		$lookup = new NullSubjectLabelLookup();

		$this->assertSame( [], $lookup->getSubjectLabelsMatching( 'Al', 10, 'Person' ) );
	}

}
