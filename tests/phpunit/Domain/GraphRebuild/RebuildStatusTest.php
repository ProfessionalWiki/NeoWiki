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
	public function testARunIsOverOnceItLeavesRunning( RebuildStatus $status, bool $expected ): void {
		$this->assertSame( $expected, $status->isTerminal() );
	}

	public function terminalProvider(): iterable {
		yield 'running' => [ RebuildStatus::Running, false ];
		yield 'succeeded' => [ RebuildStatus::Succeeded, true ];
		yield 'failed' => [ RebuildStatus::Failed, true ];
		yield 'cancelled' => [ RebuildStatus::Cancelled, true ];
	}

	/**
	 * @dataProvider resumableProvider
	 */
	public function testOnlyARunThatStoppedShortCanBeResumed( RebuildStatus $status, bool $expected ): void {
		$this->assertSame( $expected, $status->isResumable() );
	}

	public function resumableProvider(): iterable {
		yield 'a run still going has not stopped' => [ RebuildStatus::Running, false ];
		yield 'a run that reconciled everything has nothing left' => [ RebuildStatus::Succeeded, false ];
		yield 'a failed run stopped short' => [ RebuildStatus::Failed, true ];
		yield 'a cancelled run stopped short' => [ RebuildStatus::Cancelled, true ];
	}

}
