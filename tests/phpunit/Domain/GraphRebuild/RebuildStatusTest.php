<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphRebuild;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus
 */
class RebuildStatusTest extends TestCase {

	/**
	 * @dataProvider terminalProvider
	 */
	public function testARunIsOverOnceItIsNeitherQueuedNorRunning( RebuildStatus $status, bool $expected ): void {
		$this->assertSame( $expected, $status->isTerminal() );
	}

	public function terminalProvider(): iterable {
		yield 'queued' => [ RebuildStatus::Queued, false ];
		yield 'running' => [ RebuildStatus::Running, false ];
		yield 'succeeded' => [ RebuildStatus::Succeeded, true ];
		yield 'failed' => [ RebuildStatus::Failed, true ];
		yield 'cancelled' => [ RebuildStatus::Cancelled, true ];
	}

	/**
	 * A store may have only one rebuild ahead of it, whether or not anything has picked it up yet.
	 *
	 * @dataProvider activeProvider
	 */
	public function testARunIsActiveUntilItIsOver( RebuildStatus $status, bool $expected ): void {
		$this->assertSame( $expected, $status->isActive() );
	}

	public function activeProvider(): iterable {
		yield 'queued' => [ RebuildStatus::Queued, true ];
		yield 'running' => [ RebuildStatus::Running, true ];
		yield 'succeeded' => [ RebuildStatus::Succeeded, false ];
		yield 'failed' => [ RebuildStatus::Failed, false ];
		yield 'cancelled' => [ RebuildStatus::Cancelled, false ];
	}

	/**
	 * @dataProvider resumableProvider
	 */
	public function testOnlyARunThatStoppedShortCanBeResumed( RebuildStatus $status, bool $expected ): void {
		$this->assertSame( $expected, $status->isResumable() );
	}

	public function resumableProvider(): iterable {
		yield 'a run yet to start has not stopped' => [ RebuildStatus::Queued, false ];
		yield 'a run still going has not stopped' => [ RebuildStatus::Running, false ];
		yield 'a run that reconciled everything has nothing left' => [ RebuildStatus::Succeeded, false ];
		yield 'a failed run stopped short' => [ RebuildStatus::Failed, true ];
		yield 'a cancelled run stopped short' => [ RebuildStatus::Cancelled, true ];
	}

}
