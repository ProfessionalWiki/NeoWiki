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
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyOnRevisionCreatedHandler;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder
 */
class SubjectPageRebuilderTest extends TestCase {

	private SpyOnRevisionCreatedHandler $handler;

	protected function setUp(): void {
		$this->handler = new SpyOnRevisionCreatedHandler();
	}

	public function testPassesRevisionAuthorToHandler(): void {
		$author = new UserIdentityValue( 42, 'RevisionAuthor' );

		$this->newRebuilder( $this->newRevisionByUser( $author ) )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( $author, $this->handler->calls[0]['user'] );
	}

	public function testSkipsPageWithoutCurrentRevision(): void {
		$rebuilt = $this->newRebuilder( null )->rebuild( Title::makeTitle( NS_MAIN, 'Missing' ) );

		$this->assertFalse( $rebuilt );
		$this->assertSame( [], $this->handler->calls );
	}

	private function newRebuilder( ?RevisionRecord $revision ): SubjectPageRebuilder {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new SubjectPageRebuilder( $this->handler, $factory );
	}

	private function newRevisionByUser( UserIdentity $user ): RevisionRecord {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getUser' )->willReturn( $user );
		return $revision;
	}

}
