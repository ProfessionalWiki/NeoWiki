<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

/**
 * The outcome of resolving a projection name: either the {@see RdfProjection}, or — when the name is
 * neither "native" nor a target any Mapping declares — the list of known projection names for a
 * helpful error. It lets a caller resolve the projection and list the valid names from a single
 * enumeration of the Mapping pages, instead of enumerating twice (once to resolve, once to list).
 */
readonly class RdfProjectionResolution {

	/**
	 * @param string[] $knownProjectionNames The valid projection names, populated only when the target
	 *   is unknown (so the caller can report them); empty when a projection was resolved.
	 */
	private function __construct(
		public ?RdfProjection $projection,
		public array $knownProjectionNames,
	) {
	}

	public static function projection( RdfProjection $projection ): self {
		return new self( $projection, [] );
	}

	/**
	 * @param string[] $knownProjectionNames
	 */
	public static function unknownTarget( array $knownProjectionNames ): self {
		return new self( null, $knownProjectionNames );
	}

}
