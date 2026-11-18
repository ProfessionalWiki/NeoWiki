<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

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

	public function testSpecialNeoWikiLinksToTheDataModelPage(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, 'qqx', $this->getTestUser()->getUser() );

		preg_match( '/data-mw-neowiki-extension-links="([^"]*)"/', $output, $matches );
		$links = json_decode( html_entity_decode( $matches[1] ?? '' ), true );

		$this->assertContains( 'Special:DataModel', array_column( $links, 'page' ) );
	}

}
