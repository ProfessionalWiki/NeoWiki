<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use Normalizer;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;

/**
 * Resolves each link into the documentation site against the docs tree the site is built from, so a moved page
 * or a renamed heading fails here rather than leaving readers on a 404 or at the top of the wrong page. The links
 * in extension.json are checked too, as JSON cannot use the enum.
 *
 * @covers \ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl
 */
class DocumentationUrlTest extends TestCase {

	private const string SITE_PREFIX = 'https://neowiki.ai/docs/';

	/**
	 * @dataProvider linkProvider
	 */
	public function testLeadsToADocsPage( string $url ): void {
		$this->assertFileExists( $this->docFileFor( $url ) );
	}

	/**
	 * @dataProvider sectionLinkProvider
	 */
	public function testTheSectionItNamesIsAHeadingOfThatPage( string $url ): void {
		$this->assertContains(
			parse_url( $url, PHP_URL_FRAGMENT ),
			$this->anchorsOf( $this->docFileFor( $url ) )
		);
	}

	public static function linkProvider(): iterable {
		foreach ( DocumentationUrl::cases() as $documentationUrl ) {
			yield $documentationUrl->name => [ $documentationUrl->value ];
		}

		foreach ( self::linksInExtensionJson() as $url ) {
			yield "extension.json: $url" => [ $url ];
		}
	}

	public static function sectionLinkProvider(): iterable {
		foreach ( self::linkProvider() as $name => [ $url ] ) {
			if ( str_contains( $url, '#' ) ) {
				yield $name => [ $url ];
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private static function linksInExtensionJson(): array {
		preg_match_all(
			'#' . preg_quote( self::SITE_PREFIX, '#' ) . '[A-Za-z0-9_/\#-]+#',
			(string)file_get_contents( self::extensionRoot() . '/extension.json' ),
			$matches
		);

		return array_values( array_unique( $matches[0] ) );
	}

	/**
	 * e.g. https://neowiki.ai/docs/api/subject-format#ids -> <root>/docs/api/subject-format.md
	 */
	private function docFileFor( string $url ): string {
		$path = (string)parse_url( $url, PHP_URL_PATH );

		return self::extensionRoot() . $path . '.md';
	}

	/**
	 * @return list<string> The anchor the site gives each heading of the doc, code blocks left out.
	 */
	private function anchorsOf( string $docFile ): array {
		$anchors = [];
		$inCodeBlock = false;

		foreach ( file( $docFile, FILE_IGNORE_NEW_LINES ) ?: [] as $line ) {
			if ( preg_match( '/^\s*(```|~~~)/', $line ) ) {
				$inCodeBlock = !$inCodeBlock;
			}
			elseif ( !$inCodeBlock && preg_match( '/^#{1,6}\s+(.+?)\s*$/', $line, $heading ) ) {
				$anchors[] = $this->slugify( $heading[1] );
			}
		}

		return $anchors;
	}

	/**
	 * VitePress's default slugify, which the site does not override, applied to the heading's text with its link
	 * targets dropped.
	 */
	private function slugify( string $heading ): string {
		$text = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $heading );
		$text = preg_replace( '/[\x{0300}-\x{036F}]/u', '', (string)Normalizer::normalize( $text, Normalizer::FORM_KD ) );
		$text = preg_replace( '/[\x{0000}-\x{001F}]/u', '', $text );
		$text = preg_replace( '/[\s~`!@#$%^&*()\-_+=[\]{}|\\\\;:"\'“”‘’<>,.?\/]+/u', '-', $text );
		$text = preg_replace( '/-{2,}/', '-', $text );
		$text = preg_replace( '/^(\d)/', '_$1', trim( $text, '-' ) );

		return mb_strtolower( $text );
	}

	private static function extensionRoot(): string {
		return dirname( __DIR__, 3 );
	}

}
