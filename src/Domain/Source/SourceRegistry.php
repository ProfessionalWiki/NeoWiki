<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Source;

use LogicException;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SourceRegistry {

	/**
	 * @var array<string, Source>
	 */
	private array $sources = [];

	public function __construct(
		private readonly string $localSourceKey
	) {
	}

	/**
	 * @throws LogicException when the key is already taken (a config error: fail at registration)
	 */
	public function register( string $sourceKey, Source $source ): void {
		if ( array_key_exists( $sourceKey, $this->sources ) ) {
			throw new LogicException( "A Source is already registered for the key '$sourceKey'." );
		}

		$this->sources[$sourceKey] = $source;
	}

	public function getSource( string $sourceKey ): ?Source {
		return $this->sources[$sourceKey] ?? null;
	}

	/**
	 * Maps a bare id (source null) to the local Source; null for unknown keys.
	 */
	public function getSourceForId( SubjectId $id ): ?Source {
		return $this->getSource( $id->getSource() ?? $this->localSourceKey );
	}

	/**
	 * Maps a local reference (source null) to the local Source; null for unknown keys. A schema's
	 * source is independent of any subject's source (ADR 23).
	 */
	public function getSourceForSchemaReference( SchemaReference $reference ): ?Source {
		return $this->getSource( $reference->getSource() ?? $this->localSourceKey );
	}

}
