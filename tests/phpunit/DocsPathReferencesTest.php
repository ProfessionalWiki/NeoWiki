<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the docs paths cited in production code against the docs tree.
 *
 * Several REST param descriptions point readers at a documentation file. Those strings are
 * user-facing: they are served in the generated OpenAPI spec. Nothing else checks them, so a
 * doc rename silently turns them into dead references.
 *
 * When this fails, either the doc moved (update the citing string) or the path was never right.
 *
 * @coversNothing
 */
class DocsPathReferencesTest extends TestCase {

	public function testEveryDocsPathCitedInSourceCodeExists(): void {
		$this->assertSame(
			[],
			$this->danglingDocsReferences(),
			'These src/ files cite a docs file that does not exist. Point them at the current path.'
		);
	}

	/**
	 * @return list<string> e.g. "src/EntryPoints/REST/ValidateSubjectApi.php:66 cites docs/Foo.md"
	 */
	private function danglingDocsReferences(): array {
		$dangling = [];

		foreach ( $this->sourceFiles() as $file ) {
			foreach ( $this->citedDocsPaths( $file ) as $line => $paths ) {
				foreach ( $paths as $path ) {
					if ( !file_exists( $this->extensionRoot() . '/' . $path ) ) {
						$relativeFile = substr( $file, strlen( $this->extensionRoot() ) + 1 );
						$dangling[] = "$relativeFile:$line cites $path";
					}
				}
			}
		}

		return $dangling;
	}

	/**
	 * @return list<string> absolute paths of every PHP file under src/.
	 */
	private function sourceFiles(): array {
		$files = [];

		$directory = new RecursiveDirectoryIterator( $this->extensionRoot() . '/src' );
		/** @var SplFileInfo $file */
		foreach ( new RecursiveIteratorIterator( $directory ) as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * @return array<int, list<string>> docs paths cited in the file, keyed by line number.
	 */
	private function citedDocsPaths( string $file ): array {
		$contents = file_get_contents( $file );
		$this->assertNotFalse( $contents, "Could not read $file" );

		$cited = [];

		foreach ( explode( "\n", $contents ) as $index => $line ) {
			preg_match_all( '#\bdocs/[A-Za-z0-9._/-]+\.md#', $line, $matches );

			if ( $matches[0] !== [] ) {
				$cited[$index + 1] = $matches[0];
			}
		}

		return $cited;
	}

	private function extensionRoot(): string {
		return dirname( __DIR__, 2 );
	}

}
