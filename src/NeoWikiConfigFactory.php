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
	 * Bounds how long a store that never answers can hold up a page's projection, with headroom over
	 * the largest pages NeoWiki aims to support.
	 */
	private const int DEFAULT_SPARQL_UPDATE_TIMEOUT_SECONDS = 10;

	/**
	 * @param Feature[] $features
	 */
	public function __construct(
		private readonly array $features,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function buildFromMediaWikiConfig( Config $config ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: $config->get( 'NeoWikiEnableDevelopmentUI' ) === true,
			neo4jInternalWriteUrl: self::resolveWriteUrl( $this->configString( $config, 'NeoWikiNeo4jInternalWriteUrl' ) ),
			neo4jInternalReadUrl: self::resolveReadUrl( $this->configString( $config, 'NeoWikiNeo4jInternalReadUrl' ) ),
			// Also the local Source key (ADR 23). A Wiki ID that is not a well-formed Source key — a
			// digit-leading database name, say — degrades rather than failing: bare Subject ids still
			// resolve to the local Source, while the explicit `<wikiId>:<localId>` spelling of a local
			// id is unusable, because the id grammar rejects that key before anything can canonicalize
			// it away.
			wikiId: WikiMap::getCurrentWikiId(),
			rdfBaseUri: $this->buildRdfBaseUri( $config ),
			sparqlStores: $this->buildSparqlStores( $config ),
			enabledFeatures: $this->buildEnabledFeatures( $config ),
		);
	}

	/**
	 * @return string[]
	 */
	private function buildEnabledFeatures( Config $config ): array {
		$setting = $config->get( 'NeoWikiFeatures' );

		if ( !is_array( $setting ) ) {
			$this->logger->warning(
				'Ignoring NeoWikiFeatures, which is not an array of feature names to true or false, as in '
				. '[ \'featureName\' => true ].'
			);
			$setting = [];
		}

		$this->warnAboutUnusableEntries( $setting );

		return $this->namesOfEnabledFeatures( $this->withReplacedSettings( $config, $setting ) );
	}

	/**
	 * A replaced setting a wiki still sets away from its feature's default keeps deciding the feature, so a wiki
	 * that turned off the Lua library to protect restricted content does not get it back by upgrading.
	 */
	private function withReplacedSettings( Config $config, array $setting ): array {
		foreach ( $this->features as $feature ) {
			if ( $feature->replacedSetting === null ) {
				continue;
			}

			$enabled = $config->get( $feature->replacedSetting ) === true;

			if ( $enabled === $feature->enabledByDefault ) {
				continue;
			}

			wfDeprecatedMsg(
				"\$wg{$feature->replacedSetting} is deprecated: remove it, and set "
					. "\$wgNeoWikiFeatures['{$feature->name}'] = " . var_export( $enabled, true ) . ' instead.',
				callerOffset: false
			);

			$setting = [ $feature->name => $enabled ] + $setting;
		}

		return $setting;
	}

	/**
	 * @return string[]
	 */
	private function namesOfEnabledFeatures( array $setting ): array {
		$names = [];

		foreach ( $this->features as $feature ) {
			if ( self::isEnabled( $feature, $setting ) ) {
				$names[] = $feature->name;
			}
		}

		return $names;
	}

	/**
	 * Only true turns a feature on, as with NeoWiki's other switches, so that a wiki writing 0 to turn one off
	 * gets it off.
	 */
	private static function isEnabled( Feature $feature, array $setting ): bool {
		if ( array_key_exists( $feature->name, $setting ) ) {
			return $setting[$feature->name] === true;
		}

		return $feature->enabledByDefault;
	}

	/**
	 * An entry NeoWiki cannot use is logged rather than thrown: it may be a typo, or name a feature a later
	 * release removed, and neither must take down the wiki.
	 */
	private function warnAboutUnusableEntries( array $setting ): void {
		$featureNames = array_column( $this->features, 'name' );

		foreach ( $setting as $key => $value ) {
			$problem = self::entryProblem( $key, $value, $featureNames );

			if ( $problem !== null ) {
				$this->logger->warning( $problem, [ 'name' => is_int( $key ) ? $value : $key ] );
			}
		}
	}

	/**
	 * What is wrong with an entry of the setting, as a message to log about the feature it names, or null when
	 * nothing is.
	 *
	 * @param string[] $featureNames
	 */
	private static function entryProblem( int|string $key, mixed $value, array $featureNames ): ?string {
		if ( is_int( $key ) ) {
			return 'Ignoring NeoWikiFeatures entry "{name}", which lacks true or false. Write it as \'{name}\' => true.';
		}

		if ( !in_array( $key, $featureNames, true ) ) {
			return 'Ignoring NeoWikiFeatures entry "{name}": NeoWiki has no feature of that name to turn on or off. '
				. 'Check its spelling, and the release notes of the version you run.';
		}

		if ( !is_bool( $value ) ) {
			return 'NeoWikiFeatures entry "{name}" is neither true nor false, so the feature is off.';
		}

		return null;
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
			updateTimeoutSeconds: $this->updateTimeoutSeconds( $entry, $index ),
		);
	}

	/**
	 * @param array<mixed> $entry
	 */
	private function updateTimeoutSeconds( array $entry, int|string $index ): int {
		$value = $entry['updateTimeoutSeconds'] ?? null;

		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}

		if ( $value !== null ) {
			$this->logger->warning(
				'NeoWikiSparqlStores entry {index} has an "updateTimeoutSeconds" that is not a positive whole number of '
				. 'seconds, so its store uses the default of ' . self::DEFAULT_SPARQL_UPDATE_TIMEOUT_SECONDS . '.',
				[ 'index' => $index ]
			);
		}

		return self::DEFAULT_SPARQL_UPDATE_TIMEOUT_SECONDS;
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
