<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Holds each Schema parsed from PublishedPageJsonLookup's JSON for as long as the PHP process lasts,
 * keyed on the Schema page's latest revision id from the LinkCache, so a hit costs no I/O.
 * NeoWikiExtension pins its lookups in its singleton, so that is a single request under mod_php and an
 * entire run under a maintenance script. Such a long-running process does not see an approval change,
 * which leaves the latest revision id as it was, and a maintenance script does not see another
 * process's edit either; a job runner clears the LinkCache before every job.
 */
class CachingSchemaLookup implements SchemaLookup {

	/**
	 * @var array<int, ?Schema>
	 */
	private array $resolvedSchemas = [];

	public function __construct(
		private readonly PublishedPageJsonLookup $jsonLookup,
		private readonly SchemaPersistenceDeserializer $schemaDeserializer,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->makeTitleSafe( NeoWikiExtension::NS_SCHEMA, $schemaName->getText() );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The JSON lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Schema read path. It
		// must also run before the caches: the cached value is user-independent schema content,
		// and a cache hit must not serve a Schema whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$latestRevisionId = $title->getLatestRevID();

		if ( !array_key_exists( $latestRevisionId, $this->resolvedSchemas ) ) {
			$json = $this->jsonLookup->getJson( $title );

			if ( $json === null ) {
				return null;
			}

			// Named after the page, not after the asking: the process-local tier is keyed by the page's
			// revision, so the first spelling asked for would otherwise name the Schema for every later caller.
			$this->resolvedSchemas[$latestRevisionId] = $this->deserialize( $title->getText(), $json );
		}

		return $this->resolvedSchemas[$latestRevisionId];
	}

	/**
	 * A page titled as no Schema may be named, or holding JSON that is not a valid Schema, will not
	 * become one on the next call, so the process-local tier remembers the null.
	 */
	private function deserialize( string $pageName, string $json ): ?Schema {
		try {
			return $this->schemaDeserializer->deserialize( new SchemaName( $pageName ), $json );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

}
