<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use PermissionsError;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson
 * @group Database
 */
class SpecialNeoJsonTest extends SpecialPageTestBase {

	use MockAuthorityTrait;

	protected function newSpecialPage(): SpecialNeoJson {
		return new SpecialNeoJson();
	}

	public function testPageExists(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'(neojson-summary)',
			$output
		);
	}

	public function testAccessDeniedWhenUserCannotEditTargetPage(): void {
		$title = $this->getExistingTestPage( 'NeoJsonTarget' )->getTitle();

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage(
			$title->getPrefixedText(),
			null,
			null,
			$this->authorityWithGlobalEditButNoPageEdit()
		);
	}

	public function testAccessAllowedWhenUserCanEditTargetPage(): void {
		$title = $this->getExistingTestPage( 'NeoJsonTarget' )->getTitle();

		/** @var string $output */
		[ $output ] = $this->executeSpecialPage(
			$title->getPrefixedText(),
			null,
			null,
			$this->authorityThatCanEdit()
		);

		$this->assertStringContainsString( 'wpjson', $output );
	}

	private function authorityWithGlobalEditButNoPageEdit(): Authority {
		// Holds the wiki-global 'edit' right, but cannot edit this specific (e.g. protected) page.
		// This fails the test if the page ever gated on the global right instead of per-title edit.
		$callback = static function ( string $permission, ?PageIdentity $page = null ): bool {
			return !( $permission === 'edit' && $page !== null );
		};
		return $this->mockRegisteredAuthority( $callback );
	}

	private function authorityThatCanEdit(): Authority {
		$allowAll = static fn ( string $permission, ?PageIdentity $page = null ): bool => true;
		return $this->mockRegisteredAuthority( $allowAll );
	}

}
