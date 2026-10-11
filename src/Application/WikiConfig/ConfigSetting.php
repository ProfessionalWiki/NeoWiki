<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * One setting exposed on the on-wiki configuration page: the page key admins write, the
 * LocalSettings.php setting it overrides, the shape of its value, and the messages the configuration
 * form labels and explains it with. The single definition drives save-time validation, the generated
 * on-page reference, the form, and the combining read, so those cannot drift apart.
 */
readonly class ConfigSetting {

	/**
	 * @param ConfigChoice[] $choices The values the form offers by name, in place of its usual control.
	 */
	public function __construct(
		public string $pageKey,
		public string $settingName,
		public ConfigValueType $valueType,
		public string $labelMessage,
		public ?string $helpMessage,
		public array $choices = [],
	) {
	}

	public function isValidValue( mixed $value ): bool {
		return $this->valueType->isValidValue( $value );
	}

	/**
	 * The accepted-value description for the on-page reference.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function describe(): array {
		return $this->valueType->describe();
	}

	/**
	 * The save-time error for a value of the wrong shape.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function invalidValueError(): array {
		return $this->valueType->invalidValueError( $this->pageKey );
	}

}
