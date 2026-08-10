<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Context\RequestContext;
use ProfessionalWiki\NeoWiki\Infrastructure\MediaWikiEditNoticeMessageRenderer;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\MediaWikiEditNoticeMessageRenderer
 * @group Database
 */
class MediaWikiEditNoticeMessageRendererTest extends NeoWikiIntegrationTestCase {

	private const string MESSAGE_KEY = 'neowiki-editnotice-0';

	private const string PAGE_DB_KEY = 'Berlin';

	private function newRenderer(): MediaWikiEditNoticeMessageRenderer {
		return new MediaWikiEditNoticeMessageRenderer(
			RequestContext::getMain(),
			$this->getServiceContainer()->getTitleFactory()
		);
	}

	private function render(): ?string {
		return $this->newRenderer()->render( self::MESSAGE_KEY, NS_MAIN, self::PAGE_DB_KEY );
	}

	private function createMessagePage( string $content ): void {
		$this->insertPage( 'MediaWiki:' . ucfirst( self::MESSAGE_KEY ), $content );
		$this->getServiceContainer()->getMessageCache()->enable();
	}

	public function testRendersNothingWhenTheMessagePageDoesNotExist(): void {
		$this->assertNull( $this->render() );
	}

	public function testRendersTheMessageContent(): void {
		$this->createMessagePage( "'''Changes need approval'''" );

		$html = $this->render();

		$this->assertNotNull( $html );
		$this->assertStringContainsString( 'Changes need approval', $html );
		$this->assertStringContainsString( '<b>', $html );
	}

	public function testRendersNothingWhenAnAdminDisabledTheMessage(): void {
		$this->createMessagePage( '-' );

		$this->assertNull( $this->render() );
	}

	public function testRendersNothingWhenTheMessageRendersEmpty(): void {
		$this->createMessagePage( '<!-- deliberately renders nothing -->' );

		$this->assertNull( $this->render() );
	}

	public function testMagicWordsResolveAgainstThePageBeingEdited(): void {
		$this->createMessagePage( 'Editing {{PAGENAME}}' );

		$this->assertStringContainsString( 'Editing ' . self::PAGE_DB_KEY, (string)$this->render() );
	}

}
