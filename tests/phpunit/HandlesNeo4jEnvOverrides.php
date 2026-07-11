<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

/**
 * Saves, clears, and restores the NEO4J_URL_OVERRIDE / NEO4J_URL_READ_OVERRIDE environment variables.
 * These CI-only overrides win over config, so clearing them lets a test exercise the config-value path
 * deterministically. Restore what was snapshotted afterwards.
 */
trait HandlesNeo4jEnvOverrides {

	private string|false $neo4jWriteEnvOverride;
	private string|false $neo4jReadEnvOverride;

	private function snapshotAndClearNeo4jEnvOverrides(): void {
		$this->neo4jWriteEnvOverride = getenv( 'NEO4J_URL_OVERRIDE' );
		$this->neo4jReadEnvOverride = getenv( 'NEO4J_URL_READ_OVERRIDE' );
		putenv( 'NEO4J_URL_OVERRIDE' );
		putenv( 'NEO4J_URL_READ_OVERRIDE' );
	}

	private function restoreNeo4jEnvOverrides(): void {
		putenv( $this->neo4jWriteEnvOverride === false ? 'NEO4J_URL_OVERRIDE' : "NEO4J_URL_OVERRIDE=$this->neo4jWriteEnvOverride" );
		putenv( $this->neo4jReadEnvOverride === false ? 'NEO4J_URL_READ_OVERRIDE' : "NEO4J_URL_READ_OVERRIDE=$this->neo4jReadEnvOverride" );
	}

}
