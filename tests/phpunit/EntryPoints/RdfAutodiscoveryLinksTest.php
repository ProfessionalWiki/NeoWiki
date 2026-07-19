<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @group Database
 */
class RdfAutodiscoveryLinksTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 's1zz2222222azz1';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testAdvertisesTurtleAndTrigExportsForPageWithSubjects(): void {
		$revision = $this->createPageWithSubjects(
			'Autodiscovery with subjects',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$out = $this->renderContentPage( 'Autodiscovery with subjects', $revision->getId() );
		$pageId = $revision->getPageId();

		$turtle = $this->alternateLinkHref( $out, 'text/turtle' );
		$trig = $this->alternateLinkHref( $out, 'application/trig' );

		$this->assertNotNull( $turtle, 'A text/turtle alternate link is expected on a page with Subjects.' );
		$this->assertNotNull( $trig, 'An application/trig alternate link is expected on a page with Subjects.' );
		$this->assertStringContainsString( "/neowiki/v0/page/{$pageId}/rdf?projection=native&format=turtle", $turtle );
		$this->assertStringContainsString( "/neowiki/v0/page/{$pageId}/rdf?projection=native&format=trig", $trig );
		// Absolute (canonical) URLs, not relative paths, so Linked Data tooling can dereference them.
		$this->assertStringStartsWith( 'http', $turtle );
		$this->assertStringStartsWith( 'http', $trig );
	}

	public function testDoesNotAdvertiseExportsForPageWithoutSubjects(): void {
		$this->editPage( 'Autodiscovery without subjects', 'Plain page, no NeoWiki data.' );
		$revisionId = Title::newFromText( 'Autodiscovery without subjects' )->getLatestRevID();

		$out = $this->renderContentPage( 'Autodiscovery without subjects', $revisionId );

		$this->assertNull( $this->alternateLinkHref( $out, 'text/turtle' ) );
		$this->assertNull( $this->alternateLinkHref( $out, 'application/trig' ) );
	}

	private function renderContentPage( string $pageName, int $revisionId ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $pageName ) );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $revisionId );

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

	private function alternateLinkHref( OutputPage $out, string $type ): ?string {
		foreach ( $out->getLinkTags() as $tag ) {
			if ( ( $tag['rel'] ?? null ) === 'alternate' && ( $tag['type'] ?? null ) === $type ) {
				return $tag['href'] ?? null;
			}
		}

		return null;
	}

}
