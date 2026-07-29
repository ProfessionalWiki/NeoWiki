<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Schema\Exception;

use RuntimeException;

/**
 * Thrown when the content of a Schema page that exists could not be read at all, as opposed to
 * being read and found not to be a valid Schema.
 *
 * The two produce the same null for callers, but only the second is a property of the revision.
 * An unreadable blob is a transient condition — an external store or replica hiccup — so a cache
 * keyed by revision id must not remember it: the key would not change until someone edited the
 * Schema, pinning it as missing for the life of the entry. Content that does not deserialize will
 * not deserialize on the next call either, and is safe to remember.
 *
 * Callers are expected to have established that the page exists; {@see CachingSchemaLookup} does so
 * before it reaches the inner lookup.
 */
class SchemaContentUnavailableException extends RuntimeException {

	public static function forName( string $schemaName ): self {
		return new self( 'Schema content could not be read: ' . $schemaName );
	}

}
