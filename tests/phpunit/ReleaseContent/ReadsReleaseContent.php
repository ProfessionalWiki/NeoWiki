<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ReleaseContent;

/**
 * Content as releases stored it, in tests/release-content/<release>/<content model>/<page>.json, which the
 * TypeScript suite reads too. A test failing on it means master can no longer read what wikis of that release hold;
 * never edit the files to make it pass.
 */
trait ReadsReleaseContent {

	private static function releaseContent( string $release, string $contentModel, string $page ): string {
		return (string)file_get_contents( self::releaseContentPath( $release, $contentModel, $page ) );
	}

	private static function decodedReleaseContent( string $release, string $contentModel, string $page ): array {
		return json_decode( self::releaseContent( $release, $contentModel, $page ), true );
	}

	/**
	 * @return iterable<string, array{string, string}> Release and page of each file, keyed "<release> <page>"
	 */
	private static function releaseContentPages( string $contentModel ): iterable {
		foreach ( glob( self::releaseContentPath( '*', $contentModel, '*' ) ) as $path ) {
			$release = basename( dirname( $path, 2 ) );
			$page = str_replace( '_', ' ', basename( $path, '.json' ) );

			yield "$release $page" => [ $release, $page ];
		}
	}

	private static function releaseContentPath( string $release, string $contentModel, string $page ): string {
		return __DIR__ . "/../../release-content/$release/$contentModel/" . str_replace( ' ', '_', $page ) . '.json';
	}

}
