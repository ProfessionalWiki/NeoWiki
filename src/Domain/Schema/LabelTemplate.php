<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use Closure;

/**
 * How a Schema labels a Subject nobody typed a label for: text in which each `{Property name}` stands
 * for that property's value, as in `{Museum} attendance {Year}`.
 *
 * Mirrored, rendering included, by resources/ext.neowiki/src/domain/LabelTemplate.ts, which previews the
 * label in the Subject editor. Change one and change the other.
 */
readonly class LabelTemplate {

	private const string PLACEHOLDER = '/\{([^{}]*)\}/';

	public function __construct(
		public string $text,
	) {
	}

	/**
	 * The label for a Subject, or null when none of the placeholders has a value: a template that names
	 * nothing about the Subject labels every Subject of its Schema alike. Blank is no label either.
	 *
	 * @param Closure(PropertyName): string $valueOf The value of a property, or '' when it has none
	 */
	public function render( Closure $valueOf ): ?string {
		$anyValue = false;

		$label = preg_replace_callback(
			self::PLACEHOLDER,
			function ( array $match ) use ( $valueOf, &$anyValue ): string {
				$name = $this->propertyNameIn( $match[1] );

				if ( $name === null ) {
					return $match[0];
				}

				$value = $valueOf( $name );
				$anyValue = $anyValue || $value !== '';

				return $value;
			},
			$this->text
		);

		$label = $anyValue ? $this->collapseWhitespace( (string)$label ) : '';

		return $label === '' ? null : $label;
	}

	private function propertyNameIn( string $placeholder ): ?PropertyName {
		return trim( $placeholder ) === '' ? null : new PropertyName( $placeholder );
	}

	/**
	 * ASCII whitespace only: matching Unicode whitespace fails on a value that is not valid UTF-8.
	 */
	private function collapseWhitespace( string $label ): string {
		return trim( (string)preg_replace( '/\s+/', ' ', $label ) );
	}

	/**
	 * @return PropertyName[] Each property a placeholder names, once, in order of first appearance
	 */
	public function getPropertyNames(): array {
		preg_match_all( self::PLACEHOLDER, $this->text, $matches );

		$names = [];

		foreach ( $matches[1] as $placeholder ) {
			$name = $this->propertyNameIn( $placeholder );

			if ( $name !== null ) {
				$names[$name->text] = $name;
			}
		}

		return array_values( $names );
	}

}
