<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\CurrencyProperty
 */
class CurrencyPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"format": "currency",
	"description": "",
	"required": false,
	"default": null,
	"currencyCode": "EUR",
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"format": "currency",
	"currencyCode": "EUR"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "currency",
	"description": "foo",
	"currencyCode": "EUR",
	"required": true,
	"default": 42,
	"precision": 2,
	"minimum": 0,
	"maximum": 100
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "currency",
	"description": "",
	"currencyCode": "EUR",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON
		);
	}

	public function testExceptionOnMissingCurrencyCode(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"format": "currency"
}
JSON
		);
	}

}
