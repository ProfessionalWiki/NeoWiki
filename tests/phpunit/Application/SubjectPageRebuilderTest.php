<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder
 */
class SubjectPageRebuilderTest extends TestCase {

	public function testPassesRevisionAuthorToHandler(): void {
		$revisionAuthor = new UserIdentityValue( 42, 'RevisionAuthor' );
		$revision = $this->newRevisionWithUser( $revisionAuthor );

		$capturedUser = null;
		$handler = $this->newHandlerCapturingUser( $capturedUser );

		$rebuilder = new SubjectPageRebuilder(
			$handler,
			$this->newWikiPageFactoryWithRevision( $revision )
		);

		$rebuilder->rebuild( Title::makeTitle( NS_MAIN, 'TestPage' ) );

		$this->assertSame( $revisionAuthor, $capturedUser );
	}

	public function testPassesGivenRevisionToHandler(): void {
		$revisionAuthor = new UserIdentityValue( 42, 'RevisionAuthor' );
		$revision = $this->newRevisionWithUser( $revisionAuthor );

		$capturedRevision = null;
		$handler = $this->createMock( OnRevisionCreatedHandler::class );
		$handler->expects( $this->once() )
			->method( 'onRevisionCreated' )
			->willReturnCallback(
				function ( RevisionRecord $r ) use ( &$capturedRevision ): void {
					$capturedRevision = $r;
				}
			);

		$rebuilder = new SubjectPageRebuilder(
			$handler,
			$this->newWikiPageFactoryWithRevision( $revision )
		);

		$rebuilder->rebuild( Title::makeTitle( NS_MAIN, 'TestPage' ) );

		$this->assertSame( $revision, $capturedRevision );
	}

	public function testReturnsTrueWhenRebuilt(): void {
		$revision = $this->newRevisionWithUser( new UserIdentityValue( 1, 'Someone' ) );

		$rebuilder = new SubjectPageRebuilder(
			$this->createMock( OnRevisionCreatedHandler::class ),
			$this->newWikiPageFactoryWithRevision( $revision )
		);

		$this->assertTrue( $rebuilder->rebuild( Title::makeTitle( NS_MAIN, 'TestPage' ) ) );
	}

	public function testSkipsPageWithoutCurrentRevision(): void {
		$handler = $this->createMock( OnRevisionCreatedHandler::class );
		$handler->expects( $this->never() )->method( 'onRevisionCreated' );

		$rebuilder = new SubjectPageRebuilder(
			$handler,
			$this->newWikiPageFactoryWithRevision( null )
		);

		$this->assertFalse( $rebuilder->rebuild( Title::makeTitle( NS_MAIN, 'Missing' ) ) );
	}

	private function newRevisionWithUser( UserIdentity $user ): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )->willReturn( $user );
		return $revision;
	}

	private function newWikiPageFactoryWithRevision( ?RevisionRecord $revision ): WikiPageFactory {
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->method( 'getRevisionRecord' )->willReturn( $revision );

		$factory = $this->createMock( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $wikiPage );

		return $factory;
	}

	private function newHandlerCapturingUser( ?UserIdentity &$capturedUser ): OnRevisionCreatedHandler {
		$handler = $this->createMock( OnRevisionCreatedHandler::class );
		$handler->expects( $this->once() )
			->method( 'onRevisionCreated' )
			->willReturnCallback(
				function ( RevisionRecord $r, UserIdentity $user ) use ( &$capturedUser ): void {
					$capturedUser = $user;
				}
			);
		return $handler;
	}

}
