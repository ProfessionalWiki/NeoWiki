<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * Validates the JSON of the on-wiki configuration page at save time, producing precise per-field errors
 * so an administrator catches a typo the moment they can fix it. Strict on save (unlike the tolerant
 * read path): non-object JSON, unknown keys, and values of the wrong shape are all rejected.
 *
 * Errors are returned as message specs — an array whose first element is a message key and whose rest are
 * the parameters. An empty result means the configuration is valid.
 */
class ConfigValidator {

	public const string ERROR_NOT_OBJECT = 'neowiki-config-error-not-object';
	public const string ERROR_UNKNOWN_KEY = 'neowiki-config-error-unknown-key';

	public function __construct(
		private ConfigSchema $schema
	) {
	}

	/**
	 * @return array[] A list of message specs, each [ messageKey, ...params ]. Empty when valid.
	 */
	public function validate( string $json ): array {
		$data = json_decode( $json, true );

		if ( $data === null ) {
			// A JSON syntax error is rejected by the JSON content model itself, so it is left to core.
			// Only a literal `null`, which is syntactically valid, is flagged here.
			return trim( $json ) === 'null' ? [ [ self::ERROR_NOT_OBJECT ] ] : [];
		}

		// A JSON object decodes to an associative array (or the empty array); a scalar or a JSON list
		// is not an object.
		if ( !is_array( $data ) || ( $data !== [] && array_is_list( $data ) ) ) {
			return [ [ self::ERROR_NOT_OBJECT ] ];
		}

		$errors = [];

		foreach ( $data as $key => $value ) {
			$setting = $this->schema->getSetting( (string)$key );

			if ( $setting === null ) {
				$errors[] = [ self::ERROR_UNKNOWN_KEY, (string)$key ];
			} elseif ( !$setting->isValidValue( $value ) ) {
				$errors[] = $setting->invalidValueError();
			}
		}

		return $errors;
	}

}
