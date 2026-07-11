<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Config\Config;
use MediaWiki\WikiMap\WikiMap;

class NeoWikiConfigFactory {

	public function buildFromMediaWikiConfig( Config $config ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: $config->get( 'NeoWikiEnableDevelopmentUI' ) === true,
			neo4jInternalWriteUrl: self::resolveWriteUrl( $this->configString( $config, 'NeoWikiNeo4jInternalWriteUrl' ) ),
			neo4jInternalReadUrl: self::resolveReadUrl( $this->configString( $config, 'NeoWikiNeo4jInternalReadUrl' ) ),
			wikiId: WikiMap::getCurrentWikiId(),
			rdfBaseUri: $this->buildRdfBaseUri( $config ),
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
