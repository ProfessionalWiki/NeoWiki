<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType
 */
class UrlTypeTest extends TestCase {

	public function testBuildNeo4jValueForOneString(): void {
		$this->assertEquals(
			[ 'foo' ],
			$this->newType()->buildNeo4jValue( new StringValue( 'foo' ) )
		);
	}

	public function testBuildNeo4jValueForMultipleStrings(): void {
		$this->assertEquals(
			[ 'foo', 'bar' ],
			$this->newType()->buildNeo4jValue( new StringValue( 'foo', 'bar' ) )
		);
	}

	private function newType(): UrlType {
		return new UrlType();
	}

}
