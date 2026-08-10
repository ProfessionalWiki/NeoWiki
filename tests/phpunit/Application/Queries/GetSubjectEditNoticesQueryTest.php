<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices\GetSubjectEditNoticesQuery;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\RecordingSchemaNoticeProvider;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSubjectEditNoticeContextFactory;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSubjectEditNoticeProvider;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SubjectEditNoticesPresenterSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices\GetSubjectEditNoticesQuery
 */
class GetSubjectEditNoticesQueryTest extends TestCase {

	private const int PAGE_ID = 42;

	private SubjectEditNoticesPresenterSpy $presenter;

	protected function setUp(): void {
		$this->presenter = new SubjectEditNoticesPresenterSpy();
	}

	/**
	 * @param SubjectEditNoticeProviderRegistry|null $registry
	 */
	private function newQuery(
		bool $readAllowed = true,
		bool $pageExists = true,
		?SubjectEditNoticeProviderRegistry $registry = null
	): GetSubjectEditNoticesQuery {
		return new GetSubjectEditNoticesQuery(
			$this->presenter,
			$registry ?? new SubjectEditNoticeProviderRegistry(),
			new StubPageReadAuthorizer( $readAllowed ),
			new StubSubjectEditNoticeContextFactory( $pageExists )
		);
	}

	private function newRegistryWith( ...$providers ): SubjectEditNoticeProviderRegistry {
		$registry = new SubjectEditNoticeProviderRegistry();

		foreach ( $providers as $provider ) {
			$registry->addProvider( $provider );
		}

		return $registry;
	}

	private function newNotice( string $key ): SubjectEditNotice {
		return new SubjectEditNotice( key: $key, html: "<p>$key</p>" );
	}

	public function testPresentsNoticesFromARegisteredProvider(): void {
		$query = $this->newQuery( registry: $this->newRegistryWith(
			new StubSubjectEditNoticeProvider( [ $this->newNotice( 'approval' ) ] )
		) );

		$query->execute( pageId: self::PAGE_ID, schemaName: null );

		$this->assertSame( [ 'approval' ], $this->presenter->presentedKeys() );
	}

	public function testProvidersAreConcatenatedInRegistrationOrder(): void {
		$query = $this->newQuery( registry: $this->newRegistryWith(
			new StubSubjectEditNoticeProvider( [ $this->newNotice( 'first' ), $this->newNotice( 'second' ) ] ),
			new StubSubjectEditNoticeProvider( [ $this->newNotice( 'third' ) ] )
		) );

		$query->execute( pageId: self::PAGE_ID, schemaName: null );

		$this->assertSame( [ 'first', 'second', 'third' ], $this->presenter->presentedKeys() );
	}

	public function testDeniedPageIsPresentedExactlyLikeAPageWithoutNotices(): void {
		$query = $this->newQuery(
			readAllowed: false,
			registry: $this->newRegistryWith(
				new StubSubjectEditNoticeProvider( [ $this->newNotice( 'secret' ) ] )
			)
		);

		$query->execute( pageId: self::PAGE_ID, schemaName: null );

		$this->assertSame( [], $this->presenter->presentedKeys() );
	}

	public function testMissingPageYieldsNoNotices(): void {
		$query = $this->newQuery(
			pageExists: false,
			registry: $this->newRegistryWith(
				new StubSubjectEditNoticeProvider( [ $this->newNotice( 'orphan' ) ] )
			)
		);

		$query->execute( pageId: self::PAGE_ID, schemaName: null );

		$this->assertSame( [], $this->presenter->presentedKeys() );
	}

	public function testSchemaNameReachesTheProviders(): void {
		$provider = new RecordingSchemaNoticeProvider();
		$query = $this->newQuery( registry: $this->newRegistryWith( $provider ) );

		$query->execute( pageId: self::PAGE_ID, schemaName: 'Person' );

		$this->assertSame( 'Person', $provider->receivedSchemaName );
	}

	public function testPageIdReachesTheProviders(): void {
		$provider = new RecordingSchemaNoticeProvider();
		$query = $this->newQuery( registry: $this->newRegistryWith( $provider ) );

		$query->execute( pageId: self::PAGE_ID, schemaName: null );

		$this->assertSame( self::PAGE_ID, $provider->receivedPageId );
	}

}
