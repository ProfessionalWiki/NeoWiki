<?php

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

class JsonFilter {
	public function filterJson( string $json ): string {
		$data = json_decode( $json, true );
		$filteredData = $this->arrayFilterRecursive( $data );
		return json_encode( $filteredData );
	}

	public function arrayFilterRecursive( array $input ): array {
		foreach ( $input as &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->arrayFilterRecursive( $value );
			}
		}

		return array_filter( $input, function( $value ) {
			return !empty( $value );
		} );
	}
}
