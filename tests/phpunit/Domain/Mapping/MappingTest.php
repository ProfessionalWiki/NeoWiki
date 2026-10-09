<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Mapping;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping
 */
class MappingTest extends TestCase {

	public function testSchemaNameOfDigitsAloneIsAString(): void {
		$mapping = new Mapping(
			new MappingName( 'EDM' ),
			[],
			[ 'Museum' => new SchemaMapping( subject: null ), '1984' => new SchemaMapping( subject: null ) ]
		);

		$this->assertSame( [ 'Museum', '1984' ], $mapping->getSchemaNames() );
	}

}
