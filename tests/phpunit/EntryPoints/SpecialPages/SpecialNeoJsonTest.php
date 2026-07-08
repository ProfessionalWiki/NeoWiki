<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use PermissionsError;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use SpecialPageTestBase;
use WikiPage;

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

	public function testPostByUserWhoCannotEditTargetPageWritesNothing(): void {
		$page = $this->getExistingTestPage( 'NeoJsonProtectedTarget' );
		$title = $page->getTitle();
		$this->protectAgainstNonSysopEdits( $page );

		$user = $this->getTestUser()->getUser();

		$request = new FauxRequest(
			[
				'wpjson' => '[]',
				'wpEditToken' => $user->getEditToken(),
			],
			wasPosted: true
		);

		try {
			$this->executeSpecialPage( $title->getPrefixedText(), $request, null, $user );
			$this->fail( 'Expected a PermissionsError for a user who cannot edit the protected page' );
		} catch ( PermissionsError ) {
		}

		$this->assertNull(
			NeoWikiExtension::getInstance()->newSubjectContentRepository()->getSubjectContentByPageTitle( $title ),
			'The rejected POST must not have written any Subject content'
		);
	}

	private function protectAgainstNonSysopEdits( WikiPage $page ): void {
		$cascade = false;
		$page->doUpdateRestrictions(
			[ 'edit' => 'sysop' ],
			[],
			$cascade,
			'Protect for permission test',
			$this->getTestSysop()->getUser()
		);
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
