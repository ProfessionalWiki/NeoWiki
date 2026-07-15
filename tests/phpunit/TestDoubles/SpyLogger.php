<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Psr\Log\AbstractLogger;

class SpyLogger extends AbstractLogger {

	/**
	 * @var list<array{level: mixed, message: string, context: array<mixed>}>
	 */
	private array $logCalls = [];

	/**
	 * @param mixed $level
	 * @param string|\Stringable $message
	 * @param array<mixed> $context
	 */
	public function log( $level, $message, array $context = [] ): void {
		$this->logCalls[] = [ 'level' => $level, 'message' => (string)$message, 'context' => $context ];
	}

	/**
	 * @return list<array{level: mixed, message: string, context: array<mixed>}>
	 */
	public function getLogCalls(): array {
		return $this->logCalls;
	}

}
