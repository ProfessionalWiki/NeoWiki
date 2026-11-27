<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki;
use SpecialPageTestBase;

/**
 * Special:NeoWiki reads the database to tell whether the wiki has Subjects.
 *
 * @coversNothing The link is data in extension-model-bundles.json.
 * @group Database
 */
class DataModelOverviewLinkTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialNeoWiki {
		return new SpecialNeoWiki();
	}

	public function testSpecialNeoWikiLinksAUserWhoMayInstallBundlesToTheDataModelPage(): void {
		$this->assertContains( 'Special:DataModel', $this->linkedPagesFor( $this->getTestSysop()->getUser() ) );
	}

	public function testSpecialNeoWikiLinksNoOtherUserToTheDataModelPage(): void {
		$this->assertNotContains( 'Special:DataModel', $this->linkedPagesFor( $this->getTestUser()->getUser() ) );
	}

	/**
	 * @return string[]
	 */
	private function linkedPagesFor( User $user ): array {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, 'qqx', $user );

		preg_match( '/data-mw-neowiki-extension-links="([^"]*)"/', $output, $matches );

		return array_column( json_decode( html_entity_decode( $matches[1] ?? '' ), true ), 'page' );
	}

}
