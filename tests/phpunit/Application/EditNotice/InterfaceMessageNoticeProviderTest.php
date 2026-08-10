<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\EditNotice;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\EditNotice\InterfaceMessageNoticeProvider;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubEditNoticeMessageRenderer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\EditNotice\InterfaceMessageNoticeProvider
 */
class InterfaceMessageNoticeProviderTest extends TestCase {

	private function newContext( int $namespaceId = 0, string $dbKey = 'Berlin', ?string $schemaName = null ): SubjectEditNoticeContext {
		return new SubjectEditNoticeContext(
			pageId: new PageId( 42 ),
			pageDbKey: $dbKey,
			namespaceId: $namespaceId,
			schemaName: $schemaName
		);
	}

	private function newProvider( array $renderedMessages ): InterfaceMessageNoticeProvider {
		return new InterfaceMessageNoticeProvider( new StubEditNoticeMessageRenderer( $renderedMessages ) );
	}

	public function testNoNoticesWhenNoMessagesExist(): void {
		$provider = $this->newProvider( [] );

		$this->assertSame( [], $provider->getNotices( $this->newContext() ) );
	}

	public function testNamespaceMessageBecomesNotice(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-0' => '<p>Namespace notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext() );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'neowiki-editnotice-0', $notices[0]->key );
		$this->assertSame( '<p>Namespace notice</p>', $notices[0]->html );
	}

	public function testPageMessageUsesNamespaceAndDbKey(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-4-Berlin' => '<p>Page notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( namespaceId: 4 ) );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'neowiki-editnotice-4-Berlin', $notices[0]->key );
	}

	public function testSlashesInDbKeyBecomeDashes(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-0-Europe-Berlin' => '<p>Subpage notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( dbKey: 'Europe/Berlin' ) );

		$this->assertCount( 1, $notices );
	}

	public function testSchemaMessageBecomesNoticeWhenSchemaIsKnown(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-schema-Person' => '<p>Schema notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person' ) );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'neowiki-editnotice-schema-Person', $notices[0]->key );
	}

	public function testSchemaMessageIsNotConsultedWithoutASchema(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-schema-Person' => '<p>Schema notice</p>' ] );

		$this->assertSame( [], $provider->getNotices( $this->newContext( schemaName: null ) ) );
	}

	public function testSlashesInSchemaNameBecomeDashes(): void {
		$provider = $this->newProvider( [ 'neowiki-editnotice-schema-Person-Author' => '<p>Schema notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person/Author' ) );

		$this->assertCount( 1, $notices );
	}

	public function testNoticesAreOrderedFromBroadestToNarrowest(): void {
		$provider = $this->newProvider( [
			'neowiki-editnotice-schema-Person' => '<p>Schema</p>',
			'neowiki-editnotice-0-Berlin' => '<p>Page</p>',
			'neowiki-editnotice-0' => '<p>Namespace</p>',
		] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person' ) );

		$this->assertSame(
			[ 'neowiki-editnotice-0', 'neowiki-editnotice-0-Berlin', 'neowiki-editnotice-schema-Person' ],
			array_map( static fn ( $notice ) => $notice->key, $notices )
		);
	}

}
