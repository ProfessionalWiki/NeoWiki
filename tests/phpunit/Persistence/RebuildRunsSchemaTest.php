<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence;

use GenerateSchemaChangeSql;
use GenerateSchemaSql;
use MediaWikiIntegrationTestCase;

/**
 * The per-DBMS SQL that update.php applies is generated from the abstract schema and committed
 * alongside it, so the two can drift: an edit to either one alone leaves installs creating a table that
 * no longer matches what the code expects. Regenerating and comparing catches that.
 *
 * @coversNothing
 */
class RebuildRunsSchemaTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider databaseTypeProvider
	 */
	public function testGeneratedSqlMatchesTheAbstractSchema( string $databaseType ): void {
		$extensionPath = dirname( __DIR__, 3 );
		$generatedPath = $this->getNewTempFile();

		$script = new GenerateSchemaSql();
		$script->loadWithArgv( [
			'--json=' . $extensionPath . '/sql/neowiki_rebuild_runs.json',
			'--sql=' . $generatedPath,
			'--type=' . $databaseType,
			'--quiet',
		] );
		$script->execute();

		$this->assertSame(
			self::withoutSourcePath( (string)file_get_contents(
				$extensionPath . '/sql/' . $databaseType . '/neowiki_rebuild_runs.sql'
			) ),
			self::withoutSourcePath( (string)file_get_contents( $generatedPath ) ),
			'run `make dbschema` to regenerate the ' . $databaseType . ' schema'
		);
	}

	/**
	 * The patch that adds a column to an already-installed table is generated the same way and drifts the
	 * same way, and an install that has the table but not the column is exactly what it exists for.
	 *
	 * @dataProvider databaseTypeProvider
	 */
	public function testGeneratedPhasePatchMatchesTheAbstractSchemaChange( string $databaseType ): void {
		$extensionPath = dirname( __DIR__, 3 );
		$generatedPath = $this->getNewTempFile();

		$script = new GenerateSchemaChangeSql();
		$script->loadWithArgv( [
			'--json=' . $extensionPath . '/sql/abstractSchemaChanges/patch-neowiki_rebuild_runs-nwrr_phase.json',
			'--sql=' . $generatedPath,
			'--type=' . $databaseType,
			'--quiet',
		] );
		$script->execute();

		$this->assertSame(
			self::withoutSourcePath( (string)file_get_contents(
				$extensionPath . '/sql/' . $databaseType . '/patch-neowiki_rebuild_runs-nwrr_phase.sql'
			) ),
			self::withoutSourcePath( (string)file_get_contents( $generatedPath ) ),
			'run `make dbschema` to regenerate the ' . $databaseType . ' schema change'
		);
	}

	public function databaseTypeProvider(): iterable {
		yield 'mysql' => [ 'mysql' ];
		yield 'sqlite' => [ 'sqlite' ];
		yield 'postgres' => [ 'postgres' ];
	}

	/**
	 * The generator writes the JSON's path into a header comment, and that path depends on the working
	 * directory it ran from. Only the schema itself is being compared.
	 */
	private static function withoutSourcePath( string $sql ): string {
		return (string)preg_replace( '/^-- Source: .*$/m', '-- Source:', $sql );
	}

}
