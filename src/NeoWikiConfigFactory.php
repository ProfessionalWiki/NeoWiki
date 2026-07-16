<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Config\Config;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NeoWikiConfigFactory {

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
	 * @return SparqlStoreConfig[]
	 */
	private function buildSparqlStores( Config $config ): array {
		$raw = $config->has( 'NeoWikiSparqlStores' ) ? $config->get( 'NeoWikiSparqlStores' ) : null;

		if ( !is_array( $raw ) ) {
			return [];
		}

		$stores = [];

		foreach ( $raw as $index => $entry ) {
			$store = $this->buildSparqlStore( $entry, $index );

			if ( $store !== null ) {
				$stores[] = $store;
			}
		}

		return $stores;
	}

	private function buildSparqlStore( mixed $entry, int|string $index ): ?SparqlStoreConfig {
		if ( !is_array( $entry ) ) {
			$this->logger->warning( 'Ignoring NeoWikiSparqlStores entry {index}: not an array.', [ 'index' => $index ] );
			return null;
		}

		$updateUrl = $entry['updateUrl'] ?? null;

		if ( !is_string( $updateUrl ) || trim( $updateUrl ) === '' ) {
			$this->logger->warning(
				'Ignoring NeoWikiSparqlStores entry {index}: missing or empty "updateUrl".',
				[ 'index' => $index ]
			);
			return null;
		}

		$accessToken = $entry['accessToken'] ?? null;
		$projection = $entry['projection'] ?? null;

		return new SparqlStoreConfig(
			updateUrl: $updateUrl,
			accessToken: is_string( $accessToken ) && $accessToken !== '' ? $accessToken : null,
			projection: is_string( $projection ) && $projection !== '' ? $projection : NeoWikiExtension::PROJECTION_NATIVE,
		);
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
