<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

final class Labels {

	/**
	 * @return array<string, string> Trimmed, keyed by lowercase language tag, without empty ones
	 */
	public static function fromJson( mixed $json ): array {
		$labels = [];

		foreach ( is_array( $json ) ? $json : [] as $language => $label ) {
			if ( is_string( $label ) && trim( $label ) !== '' ) {
				$labels[strtolower( (string)$language )] = trim( $label );
			}
		}

		return $labels;
	}

}
