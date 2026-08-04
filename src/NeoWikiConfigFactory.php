<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Config\Config;
use MediaWiki\WikiMap\WikiMap;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphStoreName;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NeoWikiConfigFactory {

	/**
	 * What a graph rebuild's run records can file a store under. A longer name would be truncated there
	 * without a word, so two stores could end up sharing one row — and with it the check that stops two
	 * rebuilds of one store running at once.
	 */
	public function __construct(
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function buildFromMediaWikiConfig( Config $config ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: $config->get( 'NeoWikiEnableDevelopmentUI' ) === true,
			neo4jInternalWriteUrl: self::resolveWriteUrl( $this->configString( $config, 'NeoWikiNeo4jInternalWriteUrl' ) ),
			neo4jInternalReadUrl: self::resolveReadUrl( $this->configString( $config, 'NeoWikiNeo4jInternalReadUrl' ) ),
			wikiId: WikiMap::getCurrentWikiId(),
			rdfBaseUri: $this->buildRdfBaseUri( $config ),
			sparqlStores: $this->buildSparqlStores( $config ),
		);
	}

	/**
	 * Parses the `NeoWikiSparqlStores` config into value objects. A malformed entry (not an array, or
	 * without a usable `updateUrl`) is skipped with a warning rather than throwing: a config typo must
	 * not take down the wiki, but it must not be silent either.
	 *
	 * A store name must identify exactly one store, since that is how a scoped rebuild addresses it, so
	 * an entry whose name cannot do that is skipped the same way. The first entry claiming a name keeps
	 * it: dropping the later duplicate leaves the stores before it configured as they were.
	 *
	 * @return SparqlStoreConfig[]
	 */
	private function buildSparqlStores( Config $config ): array {
		$raw = $config->has( 'NeoWikiSparqlStores' ) ? $config->get( 'NeoWikiSparqlStores' ) : null;

		if ( !is_array( $raw ) ) {
			return [];
		}

		$stores = [];
		$takenNames = [];

		foreach ( $raw as $index => $entry ) {
			$store = $this->buildSparqlStore( $entry, $index );

			if ( $store === null ) {
				continue;
			}

			$rejection = self::rejectionReason( $store->name, $takenNames );

			if ( $rejection !== null ) {
				$this->logger->warning(
					'Ignoring NeoWikiSparqlStores entry {index}: ' . $rejection,
					[ 'index' => $index, 'name' => $store->name ]
				);
				continue;
			}

			$takenNames[$store->name] = true;
			$stores[] = $store;
		}

		return $stores;
	}

	/**
	 * Why this name cannot identify a store, as a message to log, or null when it can. Each reason has
	 * its own way out, so each says which one.
	 *
	 * @param array<string, true> $takenNames
	 */
	private static function rejectionReason( string $name, array $takenNames ): ?string {
		// Whatever its casing: a store called "Neo4j" reads as the bundled backend wherever a name is
		// written or reported, whether or not the two collide as array keys.
		if ( strcasecmp( $name, Neo4jPlugin::STORE_NAME ) === 0 ) {
			return 'the name "{name}" is reserved for the bundled Neo4j backend, in any casing. '
				. 'Give this store another "name".';
		}

		if ( GraphStoreName::isTooLong( $name ) ) {
			return 'the name "{name}" is longer than ' . GraphStoreName::MAX_LENGTH . ' bytes, which is all a '
				. 'rebuild can file its run records under. Give this store a shorter "name".';
		}

		if ( isset( $takenNames[$name] ) ) {
			return 'the name "{name}" is already taken by an earlier entry. '
				. 'Give one of them an explicit "name".';
		}

		return null;
	}

	private function buildSparqlStore( mixed $entry, int|string $index ): ?SparqlStoreConfig {
		if ( !is_array( $entry ) ) {
			$this->logger->warning( 'Ignoring NeoWikiSparqlStores entry {index}: not an array.', [ 'index' => $index ] );
			return null;
		}

		$updateUrl = self::stringValue( $entry, 'updateUrl' );

		if ( $updateUrl === null ) {
			$this->logger->warning(
				'Ignoring NeoWikiSparqlStores entry {index}: missing or empty "updateUrl".',
				[ 'index' => $index ]
			);
			return null;
		}

		$projection = self::stringValue( $entry, 'projection' ) ?? RdfPageProjector::PROJECTION;

		return new SparqlStoreConfig(
			updateUrl: $updateUrl,
			// The read surfaces query `queryUrl`; it defaults to `updateUrl` because for QLever the query
			// and update endpoints are the same value. A store that separates them (e.g. a read replica or
			// a read-protected endpoint) sets `queryUrl` explicitly.
			queryUrl: self::stringValue( $entry, 'queryUrl' ) ?? $updateUrl,
			accessToken: self::stringValue( $entry, 'accessToken' ),
			projection: $projection,
			// The projection is unique for as long as each store holds one, which makes it the name that
			// needs no configuring. Entries that would collide name themselves.
			name: self::stringValue( $entry, 'name' ) ?? $projection,
		);
	}

	/**
	 * A configured string, or null when the key is absent or holds nothing but whitespace. Surrounding
	 * whitespace is a typo in every setting here, and a padded store name or URL would silently fail to
	 * match, so it is trimmed off rather than carried.
	 *
	 * @param array<mixed> $entry
	 */
	private static function stringValue( array $entry, string $key ): ?string {
		$value = $entry[$key] ?? null;

		if ( !is_string( $value ) || trim( $value ) === '' ) {
			return null;
		}

		return trim( $value );
	}

	/**
	 * The base URI for all minted RDF IRIs. Defaults to the wiki's canonical URL so IRIs are stable
	 * and resolvable; admins can override it (e.g. to align with an institutional URI policy).
	 */
	private function buildRdfBaseUri( Config $config ): string {
		$configured = $config->get( 'NeoWikiRdfBaseUri' );

		if ( is_string( $configured ) && $configured !== '' ) {
			return $configured;
		}

		$canonicalServer = $config->get( 'CanonicalServer' );

		return is_string( $canonicalServer ) ? $canonicalServer : '';
	}

	private function configString( Config $config, string $key ): ?string {
		$value = $config->get( $key );
		return is_string( $value ) ? $value : null;
	}

	public static function resolveWriteUrl( ?string $configValue ): ?string {
		$override = getenv( 'NEO4J_URL_OVERRIDE' ); // Used by the CI to change its test config
		return is_string( $override ) ? $override : $configValue;
	}

	public static function resolveReadUrl( ?string $configValue ): ?string {
		$override = getenv( 'NEO4J_URL_READ_OVERRIDE' ); // Used by the CI to change its test config
		return is_string( $override ) ? $override : $configValue;
	}

}
