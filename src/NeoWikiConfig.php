<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

readonly class NeoWikiConfig {

	/**
	 * @param SparqlStoreConfig[] $sparqlStores The configured SPARQL graph stores (#586), possibly empty.
	 */
	public function __construct(
		public bool $enableDevelopmentUIs,
		public ?string $neo4jInternalWriteUrl,
		public ?string $neo4jInternalReadUrl,
		public string $wikiId,
		public string $rdfBaseUri,
		public array $sparqlStores,
	) {
	}

	public function hasNeo4jBackend(): bool {
		return self::neo4jConfigured( $this->neo4jInternalReadUrl, $this->neo4jInternalWriteUrl );
	}

	public static function neo4jConfigured( ?string $readUrl, ?string $writeUrl ): bool {
		return $readUrl !== null && $writeUrl !== null;
	}

}
