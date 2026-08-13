<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Infrastructure\RequestContextSubjectEditNoticeEnvironment;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\RequestContextSubjectEditNoticeEnvironment
 * @group Database
 */
class RequestContextSubjectEditNoticeEnvironmentTest extends NeoWikiIntegrationTestCase {

	private function newEnvironment(): RequestContextSubjectEditNoticeEnvironment {
		return new RequestContextSubjectEditNoticeEnvironment(
			$this->getServiceContainer()->getTitleFactory()
		);
	}

	private function newContext(): SubjectEditNoticeContext {
		return new SubjectEditNoticeContext(
			pageId: new PageId( 42 ),
			pageDbKey: 'Berlin',
			namespaceId: NS_HELP,
			namespaceHasSubpages: false
		);
	}

	public function testProvidersSeeThePageWhoseNoticesAreBeingCollected(): void {
		$this->newEnvironment()->prepareFor( $this->newContext() );

		$this->assertSame( 'Help:Berlin', RequestContext::getMain()->getTitle()?->getPrefixedText() );
	}

	public function testRestoringPutsBackTheTitleTheRequestArrivedWith(): void {
		RequestContext::getMain()->setTitle( Title::newFromText( 'Original page' ) );
		$environment = $this->newEnvironment();

		$environment->prepareFor( $this->newContext() );
		$environment->restore();

		$this->assertSame( 'Original page', RequestContext::getMain()->getTitle()?->getPrefixedText() );
	}

}
