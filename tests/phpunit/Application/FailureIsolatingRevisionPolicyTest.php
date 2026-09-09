<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\FailureIsolatingRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\FailureIsolatingRevisionPolicy
 */
class FailureIsolatingRevisionPolicyTest extends TestCase {

	private const int PAGE_ID = 7;
	private const int OTHER_PAGE_ID = 8;

	public function testPassesThePolicysAnswersThrough(): void {
		$revision = $this->newRevision( pageId: self::PAGE_ID, id: 10 );
		$published = $this->newRevision( pageId: self::PAGE_ID, id: 9 );
		$policy = $this->isolate( FixedRevisionPolicy::publishing( $published ) );

		$this->assertTrue( $policy->publishesRevision( $revision ) );
		$this->assertSame( $published, $policy->publishedRevision( $revision ) );
		$this->assertTrue( $policy->revisionIsReadableBy( $revision, $this->createStub( Authority::class ) ) );
	}

	public function testAThrowingPolicyPublishesNothing(): void {
		$policy = $this->isolate( $this->newThrowingPolicy() );
		$revision = $this->newRevision( pageId: self::PAGE_ID, id: 10 );

		$this->assertFalse( $policy->publishesRevision( $revision ) );
		$this->assertNull( $policy->publishedRevision( $revision ) );
	}

	public function testAThrowingPolicyHidesEveryRevision(): void {
		$policy = $this->isolate( $this->newThrowingPolicy() );

		$this->assertFalse( $policy->revisionIsReadableBy(
			$this->newRevision( pageId: self::PAGE_ID, id: 10 ),
			$this->createStub( Authority::class )
		) );
	}

	public function testAThrowingPolicyIsLogged(): void {
		$logger = new TestLogger();

		( new FailureIsolatingRevisionPolicy( $this->newThrowingPolicy(), $logger ) )
			->publishesRevision( $this->newRevision( pageId: self::PAGE_ID, id: 10 ) );

		$this->assertTrue( $logger->hasErrorRecords() );
	}

	public function testARevisionOfAnotherPageIsRefused(): void {
		$logger = new TestLogger();
		$policy = new FailureIsolatingRevisionPolicy(
			$this->newPolicyNaming( $this->newRevision( pageId: self::OTHER_PAGE_ID, id: 9 ) ),
			$logger
		);

		$published = $policy->publishedRevision( $this->newRevision( pageId: self::PAGE_ID, id: 10 ) );

		$this->assertNull( $published );
		$this->assertTrue( $logger->hasErrorRecords(), 'a contract violation is logged' );
	}

	public function testASuppressedRevisionIsNeverPublished(): void {
		$suppressed = $this->newRevision( pageId: self::PAGE_ID, id: 9, textSuppressed: true );
		$policy = $this->isolate( FixedRevisionPolicy::publishing( $suppressed ) );

		$this->assertNull( $policy->publishedRevision( $this->newRevision( pageId: self::PAGE_ID, id: 10 ) ) );
		$this->assertFalse( $policy->publishesRevision( $suppressed ) );
	}

	public function testANullAnswerStaysNull(): void {
		$policy = $this->isolate( FixedRevisionPolicy::publishingNothing() );

		$this->assertNull( $policy->publishedRevision( $this->newRevision( pageId: self::PAGE_ID, id: 10 ) ) );
	}

	private function isolate( RevisionPolicy $policy ): FailureIsolatingRevisionPolicy {
		return new FailureIsolatingRevisionPolicy( $policy, new NullLogger() );
	}

	/**
	 * A policy that breaks the contract by naming the same revision for every page it is asked about.
	 * FixedRevisionPolicy answers per page, as a real extension does, so it cannot stand in here.
	 */
	private function newPolicyNaming( RevisionRecord $published ): RevisionPolicy {
		return new class( $published ) extends NullRevisionPolicy {
			public function __construct( private readonly RevisionRecord $published ) {
			}

			public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
				return $this->published;
			}
		};
	}

	private function newThrowingPolicy(): RevisionPolicy {
		return new class extends NullRevisionPolicy {
			public function publishesRevision( RevisionRecord $revision ): bool {
				throw new RuntimeException( 'approval table missing' );
			}

			public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
				throw new RuntimeException( 'approval table missing' );
			}

			public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
				throw new RuntimeException( 'approval table missing' );
			}
		};
	}

	private function newRevision( int $pageId, int $id, bool $textSuppressed = false ): RevisionRecord {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( $pageId );
		$revision->method( 'getId' )->willReturn( $id );
		$revision->method( 'isDeleted' )->willReturnCallback(
			static fn ( int $field ): bool => $textSuppressed && ( $field & RevisionRecord::DELETED_TEXT ) !== 0
		);

		return $revision;
	}

}
