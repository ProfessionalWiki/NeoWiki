<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\ValueFormat\Formats;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\UrlFormat;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\UrlFormat
 */
class UrlFormatTest extends TestCase {

	public function testBuildNeo4jValueForOneString(): void {
		$this->assertEquals(
			[ 'foo' ],
			$this->newFormat()->buildNeo4jValue( new StringValue( 'foo' ) )
		);
	}

	public function testBuildNeo4jValueForMultipleStrings(): void {
		$this->assertEquals(
			[ 'foo', 'bar' ],
			$this->newFormat()->buildNeo4jValue( new StringValue( 'foo', 'bar' ) )
		);
	}

	private function newFormat(): UrlFormat {
		return new UrlFormat();
	}

}
