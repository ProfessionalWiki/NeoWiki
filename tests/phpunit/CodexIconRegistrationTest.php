<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards against property-type icons being referenced by the frontend
 * (NeoWikiExtension.ts) without being registered in the ResourceLoader
 * CodexModule icon allowlist in extension.json.
 *
 * The frontend bundle externalises `@wikimedia/codex-icons` to a curated
 * `icons.json` produced by `CodexModule::getIcons` from this allowlist, so an
 * icon missing here resolves to `undefined` at runtime and silently renders no
 * icon (it still passes unit tests, which resolve the real npm package).
 *
 * @coversNothing
 */
class CodexIconRegistrationTest extends TestCase {

	/**
	 * @return list<string>
	 */
	private function getRegisteredCodexIcons(): array {
		$extension = json_decode(
			(string)file_get_contents( __DIR__ . '/../../extension.json' ),
			associative: true
		);

		foreach ( $extension['ResourceModules']['ext.neowiki']['packageFiles'] as $packageFile ) {
			if ( is_array( $packageFile ) && ( $packageFile['name'] ?? '' ) === 'icons.json' ) {
				return $packageFile['callbackParam'];
			}
		}

		$this->fail( 'No icons.json packageFile found in ext.neowiki module' );
	}

	/**
	 * @dataProvider propertyTypeIconProvider
	 */
	public function testPropertyTypeIconIsRegisteredForResourceLoader( string $icon ): void {
		$this->assertContains(
			$icon,
			$this->getRegisteredCodexIcons(),
			"Codex icon '{$icon}' is used by a property type but is not in the "
				. 'extension.json CodexModule icon allowlist, so it will not '
				. 'resolve at runtime.'
		);
	}

	/**
	 * @return iterable<string, array{string}>
	 *
	 * Mirrors the icon assignments in resources/ext.neowiki/src/NeoWikiExtension.ts.
	 * Add the corresponding entry here when registering an icon for a new property type.
	 */
	public static function propertyTypeIconProvider(): iterable {
		yield 'Text type (cdxIconSearchCaseSensitive)' => [ 'cdxIconSearchCaseSensitive' ];
		yield 'URL type (cdxIconLink)' => [ 'cdxIconLink' ];
		yield 'Number type (cdxIconMathematics)' => [ 'cdxIconMathematics' ];
		yield 'Select type (cdxIconListBullet)' => [ 'cdxIconListBullet' ];
		yield 'Relation type (cdxIconArticles)' => [ 'cdxIconArticles' ];
		yield 'Date & Time type (cdxIconClock)' => [ 'cdxIconClock' ];
		yield 'Date type (cdxIconCalendar)' => [ 'cdxIconCalendar' ];
	}

}
