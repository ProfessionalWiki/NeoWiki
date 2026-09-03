<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Source;

use Closure;
use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The Sources this wiki knows, keyed by Source key (ADR 23). A wiki farm is simply more registered
 * Sources; the identity format does not change.
 *
 * A Source key that was never registered resolves to null rather than throwing, so an id referring to a
 * Source this wiki does not have degrades where it is read instead of breaking the page.
 */
class SourceRegistry {

	/**
	 * @var array<string, Source|Closure(): Source> Keys are Source keys. A Closure is a Source not
	 *   built yet; it is replaced by what it returns the first time that Source is asked for.
	 */
	private array $sources = [];

	/**
	 * @param string $localSourceKey The key the local Source registers under: the MediaWiki Wiki ID
	 *   (ADR 22). A bare Subject id resolves to it.
	 */
	public function __construct(
		private readonly string $localSourceKey
	) {
	}

	/**
	 * Registering a key already taken replaces its Source, except for the local one, which is this
	 * wiki's own storage and not something an extension may stand in front of.
	 *
	 * @param Source|Closure(): Source $source A Closure is called at most once, when this Source is
	 *   first resolved. Registering a Source must not cost building it: the local one reaches the
	 *   subject-to-page index, which lives in the graph projection, so a wiki without a configured
	 *   graph backend cannot build it at registration time.
	 *
	 * @throws InvalidArgumentException When $key is not a well-formed Source key, or names the local
	 *   Source while one is registered.
	 */
	public function registerSource( string $key, Source|Closure $source ): void {
		// The local key is exempt from the grammar: it is the wiki's own id rather than anyone's
		// choice here, and a wiki whose id is not a well-formed Source key must still resolve the bare
		// ids of its own Subjects. See NeoWikiConfigFactory for what such a wiki loses.
		if ( $key !== $this->localSourceKey && !SubjectId::isValidSourceKey( $key ) ) {
			throw new InvalidArgumentException( "Source key has the wrong format: '$key'" );
		}

		if ( $key === $this->localSourceKey && isset( $this->sources[$key] ) ) {
			throw new InvalidArgumentException( "The local Source cannot be replaced: '$key'" );
		}

		$this->sources[$key] = $source;
	}

	public function getSource( string $key ): ?Source {
		$source = $this->sources[$key] ?? null;

		if ( $source instanceof Closure ) {
			$source = $source();
			$this->sources[$key] = $source;
		}

		return $source;
	}

	/**
	 * The Source that produced the Subject $id names, or null when this wiki has no such Source.
	 */
	public function getSourceOf( SubjectId $id ): ?Source {
		return $this->getSource( $id->source ?? $this->localSourceKey );
	}

	public function canResolve( SubjectId $id ): bool {
		return $this->getSourceOf( $id ) !== null;
	}

	public function getLocalSourceKey(): string {
		return $this->localSourceKey;
	}

	/**
	 * @return string[] The registered Source keys.
	 */
	public function getSourceKeys(): array {
		return array_keys( $this->sources );
	}

}
