<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatLookup;

abstract class PropertyDefinition {

	public function __construct(
		private readonly PropertyCore $core,
	) {
	}

	abstract public function getFormat(): string;

	public function getDescription(): string {
		return $this->core->description;
	}

	public function isRequired(): bool {
		return $this->core->required;
	}

	public function getDefault(): mixed {
		return $this->core->default;
	}

	public function hasDefault(): bool {
		return $this->core->default !== null;
	}

	public function allowsMultipleValues(): bool {
		return false;
	}

	public function toJson(): array {
		return array_merge(
			[
				'format' => $this->getFormat(),
				'description' => $this->getDescription(),
				'required' => $this->isRequired(),
				'default' => $this->getDefault(),
			],
			$this->nonCoreToJson()
		);
	}

	abstract protected function nonCoreToJson(): array;

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromJson( array $json, ValueFormatLookup $formatLookup ): self {
		$format = $formatLookup->getFormat( $json['format'] );

		if ( $format === null ) {
			throw new InvalidArgumentException( 'Unknown format: ' . $json['format'] );
		}

		$propertyCore = new PropertyCore(
			description: $json['description'] ?? '',
			required: $json['required'] ?? false,
			default: $json['default'] ?? null
		);

		try{
			return $format->buildPropertyDefinitionFromJson( $propertyCore, $json );
		}
		catch ( \Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

}
