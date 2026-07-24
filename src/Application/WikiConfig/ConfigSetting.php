<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * One boolean setting exposed on the on-wiki configuration page: the page key admins write and the
 * LocalSettings.php setting it overrides. The single definition drives save-time validation, the
 * generated on-page reference, and the combining read, so those cannot drift apart.
 *
 * Both exposed settings are boolean today, so the value shape is fixed here rather than abstracted. The
 * first genuinely differently-shaped setting reintroduces a value-type abstraction.
 */
readonly class ConfigSetting {

	public function __construct(
		public string $pageKey,
		public string $settingName,
	) {
	}

	public function isValidValue( mixed $value ): bool {
		return is_bool( $value );
	}

	/**
	 * The accepted-value description for the on-page reference.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function describe(): array {
		return [ 'neowiki-config-type-boolean' ];
	}

	/**
	 * The save-time error for a value of the wrong shape.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function invalidValueError(): array {
		return [ 'neowiki-config-error-invalid-boolean', $this->pageKey ];
	}

}
