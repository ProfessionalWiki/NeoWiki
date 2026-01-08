<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

class JsonSchemaErrorFormatter {

	/**
	 * @param array<string, string> $errors
	 */
	public static function format( array $errors ): string {
		$html = '<table class="wikitable">';

		foreach ( $errors as $key => $value ) {
			$html .= '<tr><td>' . htmlspecialchars( $key ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $value ) . '</td></tr>';
		}

		$html .= '</table>';

		return $html;
	}

}
