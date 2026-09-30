<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the documentation references cited in production code.
 *
 * Two citation forms are guarded:
 *  - Repo-relative paths (docs/....md), used in developer-facing code comments, must exist in the
 *    local docs tree. A docs/....md sequence inside a URL is not such a citation and is ignored.
 *  - Public docs-site URLs (https://neowiki.ai/docs/...), which readers without the source tree
 *    follow, must come from DocumentationUrl, where DocumentationUrlTest checks each one.
 *
 * @coversNothing
 */
class DocsPathReferencesTest extends TestCase {

	private const DOCS_SITE_PREFIX = 'https://neowiki.ai/';
	private const DOCUMENTATION_URL_FILE = 'src/Presentation/DocumentationUrl.php';

	public function testEveryDocsPathCitedInSourceCodeExists(): void {
		$this->assertSame(
			[],
			$this->danglingDocsReferences(),
			'These src/ files cite a docs file that does not exist. Point them at the current path.'
		);
	}

	public function testOnlyDocumentationUrlCitesTheDocsSite(): void {
		$this->assertSame(
			[],
			$this->docsSiteUrlsOutsideDocumentationUrl(),
			'These src/ files cite a ' . self::DOCS_SITE_PREFIX . 'docs/... URL. Add it to DocumentationUrl '
				. 'and use that instead.'
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
						$dangling[] = $this->relativePath( $file ) . ":$line cites $path";
					}
				}
			}
		}

		return $dangling;
	}

	/**
	 * @return list<string> e.g. "src/.../Foo.php:66 cites https://neowiki.ai/docs/x"
	 */
	private function docsSiteUrlsOutsideDocumentationUrl(): array {
		$cited = [];

		foreach ( $this->sourceFiles() as $file ) {
			if ( $this->relativePath( $file ) === self::DOCUMENTATION_URL_FILE ) {
				continue;
			}

			foreach ( $this->citedDocsSiteUrls( $file ) as $line => $urls ) {
				foreach ( $urls as $url ) {
					$cited[] = $this->relativePath( $file ) . ":$line cites $url";
				}
			}
		}

		return $cited;
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
	 * Repo-relative docs/....md citations, keyed by line number. Paths that are part of a URL
	 * (e.g. https://docs.example.com/docs/x.md) are excluded: they are not repo citations.
	 *
	 * @return array<int, list<string>>
	 */
	private function citedDocsPaths( string $file ): array {
		$cited = [];

		foreach ( $this->lines( $file ) as $line => $text ) {
			$outsideUrls = preg_replace( '#https?://\S+#', '', $text );
			preg_match_all( '#\bdocs/[A-Za-z0-9._/-]+\.md#', $outsideUrls, $matches );

			if ( $matches[0] !== [] ) {
				$cited[$line] = $matches[0];
			}
		}

		return $cited;
	}

	/**
	 * Public docs-site URLs (https://neowiki.ai/docs/...), keyed by line number. The path stops
	 * at the first non-slug character, so a trailing sentence period is not captured.
	 *
	 * @return array<int, list<string>>
	 */
	private function citedDocsSiteUrls( string $file ): array {
		$cited = [];
		$pattern = '#' . preg_quote( self::DOCS_SITE_PREFIX, '#' ) . 'docs/[A-Za-z0-9_/-]+#';

		foreach ( $this->lines( $file ) as $line => $text ) {
			preg_match_all( $pattern, $text, $matches );

			if ( $matches[0] !== [] ) {
				$cited[$line] = $matches[0];
			}
		}

		return $cited;
	}

	/**
	 * @return array<int, string> file lines keyed by 1-based line number.
	 */
	private function lines( string $file ): array {
		$contents = file_get_contents( $file );
		$this->assertNotFalse( $contents, "Could not read $file" );

		$lines = [];
		foreach ( explode( "\n", $contents ) as $index => $text ) {
			$lines[$index + 1] = $text;
		}

		return $lines;
	}

	private function relativePath( string $file ): string {
		return substr( $file, strlen( $this->extensionRoot() ) + 1 );
	}

	private function extensionRoot(): string {
		return dirname( __DIR__, 2 );
	}

}
