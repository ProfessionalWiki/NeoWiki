<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::shouldAutoRenderMainSubject
 * @group Database
 */
class AutoRenderMainSubjectTest extends NeoWikiIntegrationTestCase {

	private const SUBJECT_ID = 's1zz1111111azz1';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testDoesNotAutoRenderMainSubjectWhenConfigDisabled(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render disabled',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderContentPage( 'Auto render disabled', $revision->getId() );

		$this->assertStringNotContainsString( 'ext-neowiki-view', $html );
	}

	public function testAutoRendersMainSubjectByDefault(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render default',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$html = $this->renderContentPage( 'Auto render default', $revision->getId() );

		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="' . self::SUBJECT_ID . '"', $html );
	}

	public function testRendersAppContainerWhenAutoRenderDisabled(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render container',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderContentPage( 'Auto render container', $revision->getId() );

		$this->assertStringContainsString( 'id="ext-neowiki-app"', $html );
	}

	private function renderContentPage( string $pageName, int $revisionId ): string {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $pageName ) );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $revisionId );

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out->getHTML();
	}

}
