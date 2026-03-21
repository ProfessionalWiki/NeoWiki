<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Layout\DisplayRule;
use ProfessionalWiki\NeoWiki\Domain\Layout\DisplayRules;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;

class LayoutPersistenceDeserializer {

	/**
	 * @throws InvalidArgumentException
	 */
	public function deserialize( LayoutName $layoutName, string $json ): Layout {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		return new Layout(
			name: $layoutName,
			schema: new SchemaName( $data['schema'] ),
			type: $data['type'],
			description: $data['description'] ?? '',
			displayRules: $this->displayRulesFromJson( $data ),
			settings: $data['settings'] ?? [],
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function displayRulesFromJson( array $data ): DisplayRules {
		$rules = [];
		$displayRules = $data['displayRules'] ?? [];

		if ( !is_array( $displayRules ) ) {
			return new DisplayRules( [] );
		}

		foreach ( $displayRules as $rule ) {
			if ( is_array( $rule ) && isset( $rule['property'] ) && is_string( $rule['property'] ) ) {
				$rules[] = new DisplayRule(
					property: new PropertyName( $rule['property'] ),
					displayAttributes: $rule['displayAttributes'] ?? [],
				);
			}
		}

		return new DisplayRules( $rules );
	}

}
