<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Mapping;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName
 */
class MappingNameTest extends TestCase {

	public function testNonReservedMappingNameIsValid(): void {
		$mappingName = new MappingName( 'EDM' );

		$this->assertSame( 'EDM', $mappingName->getText() );
	}

	public function testEmptyMappingNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new MappingName( '  ' );
	}

	/**
	 * "native" is the built-in projection, so a Mapping page may not claim it, in any casing (MediaWiki
	 * uppercases only the first title letter, so "Native" is the reachable form).
	 *
	 * @dataProvider reservedMappingNameProvider
	 */
	public function testReservedMappingNameIsInvalid( string $name ): void {
		$this->expectException( InvalidArgumentException::class );

		new MappingName( $name );
	}

	public static function reservedMappingNameProvider(): iterable {
		yield [ 'native' ];
		yield [ 'Native' ];
		yield [ 'NATIVE' ];
		yield [ 'nAtIvE' ];
	}

}
