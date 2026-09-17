<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\JsonSchemaAssertions;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty
 */
class UrlPropertyTest extends TestCase {

	use JsonSchemaAssertions;

	public function testValueIsAnArrayOfStringsMatchingTheUrlPattern(): void {
		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string', 'pattern' => UrlType::URL_PATTERN ] ],
			TestProperty::buildUrl( multiple: true )->toJsonSchema()
		);
	}

	public function testValueDoesNotClaimTheUriFormat(): void {
		$this->assertArrayNotHasKey(
			'format',
			TestProperty::buildUrl()->toJsonSchema()['items'],
			'format: uri rejects the scheme-less values NeoWiki accepts and accepts the schemes it rejects.'
		);
	}

	public function testUniqueItemsConstraintForbidsDuplicateParts(): void {
		$this->assertSame(
			[
				'type' => 'array',
				'items' => [ 'type' => 'string', 'pattern' => UrlType::URL_PATTERN ],
				'uniqueItems' => true,
			],
			TestProperty::buildUrl( multiple: true, uniqueItems: true )->toJsonSchema()
		);
	}

	/**
	 * @dataProvider urlPatternProvider
	 */
	public function testPatternJudgesAUrl( string $url, bool $valid ): void {
		$items = TestProperty::buildUrl()->toJsonSchema()['items'];

		$this->assertSame( $valid, $this->jsonSchemaAccepts( $items, $url ) );
	}

	public static function urlPatternProvider(): iterable {
		yield 'a host alone' => [ 'example.com', true ];
		yield 'an uppercase scheme and host' => [ 'HTTPS://EXAMPLE.COM', true ];
		yield 'a path, query and fragment in mixed case' => [ 'http://example.com/A/B?Q=1#Frag', true ];
		yield 'a colon with no port' => [ 'http://example.com:/a', false ];
		yield 'a host label ending in a hyphen' => [ 'http://exa-.com', false ];
		yield 'a host label starting with a hyphen' => [ 'http://-example.com', false ];
		yield 'a single-letter top-level domain' => [ 'http://example.c', false ];
		yield 'a scheme other than http or https' => [ 'ftp://example.com', false ];
		yield 'a space in the path' => [ 'https://example.com/a b', false ];
	}

}
