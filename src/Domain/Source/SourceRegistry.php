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
	 *   first resolved. Registration happens under an early hook, on every request, whether or not a
	 *   Subject is read; a Source that costs anything to build — a client, a connection, a file read —
	 *   registers as a Closure so that cost is paid only by a request that reaches it.
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

	/**
	 * A copy of this registry whose local Source is $localSource, leaving every other registration as it is,
	 * including the ones not built yet.
	 *
	 * How this wiki reads its own Subjects depends on the caller — the published revision for a reader, only
	 * pages the actor may read for a validation pass — while a Source of somewhere else has no such variants:
	 * it vouches for what it returns. Replacing rather than registering, because the local key is closed to
	 * {@see self::registerSource()}: this is the wiki's own storage read differently, not another Source
	 * standing in front of it.
	 */
	public function withLocalSource( Source $localSource ): self {
		$copy = clone $this;
		$copy->sources[$this->localSourceKey] = $localSource;

		return $copy;
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

	/**
	 * Whether this wiki has the Source $id names. Answered from the registration alone, so asking does
	 * not build a Source registered as a Closure — this runs per relation target on a validation pass.
	 */
	public function canResolve( SubjectId $id ): bool {
		return isset( $this->sources[$id->source ?? $this->localSourceKey] );
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
