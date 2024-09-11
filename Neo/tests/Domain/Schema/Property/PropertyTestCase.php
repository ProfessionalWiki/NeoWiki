<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\FormatTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;

abstract class PropertyTestCase extends TestCase {

	protected function deserializeAndReserialize( string $serialization ): string {
		return json_encode(
			$this->fromJson( $serialization )->toJson(),
			JSON_PRETTY_PRINT
		);
	}

	protected function fromJson( string $json ): PropertyDefinition {
		return PropertyDefinition::fromJson(
			json_decode( $json, true ),
			ValueFormatRegistry::withCoreFormats()
		);
	}

	protected function assertSerializationDoesNotChange( string $serialization ): void {
		$this->assertJsonStringEqualsJsonString(
			$serialization,
			$this->deserializeAndReserialize( $serialization )
		);
	}

}
