<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Config\Config;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;

class NeoWikiConfigFactory {

	public function buildFromMediaWikiConfig( Config $config ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: $config->get( 'NeoWikiEnableDevelopmentUI' ) === true,
			neo4jInternalWriteUrl: $this->buildInternalWriteUrl( $config ),
			neo4jInternalReadUrl: $this->builtInternalReadUrl( $config ),
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

	private function buildInternalWriteUrl( Config $config ): string {
		if ( is_string( getenv( 'NEO4J_URL_OVERRIDE' ) ) ) { // Used by the CI to change its test config
			return getenv( 'NEO4J_URL_OVERRIDE' );
		}

		if ( is_string( $config->get( 'NeoWikiNeo4jInternalWriteUrl' ) ) ) {
			return $config->get( 'NeoWikiNeo4jInternalWriteUrl' );
		}

		throw new RuntimeException( 'Missing required config: NeoWikiNeo4jInternalWriteUrl' );
	}

	private function builtInternalReadUrl( Config $config ): string {
		if ( is_string( getenv( 'NEO4J_URL_READ_OVERRIDE' ) ) ) { // Used by the CI to change its test config
			return getenv( 'NEO4J_URL_READ_OVERRIDE' );
		}

		if ( is_string( $config->get( 'NeoWikiNeo4jInternalReadUrl' ) ) ) {
			return $config->get( 'NeoWikiNeo4jInternalReadUrl' );
		}

		throw new RuntimeException( 'Missing required config: NeoWikiNeo4jInternalReadUrl' );
	}

}
