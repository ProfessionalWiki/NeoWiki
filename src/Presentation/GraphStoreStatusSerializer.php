<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Utils\MWTimestamp;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Renders what the graph-store surfaces report as JSON.
 *
 * A store is described by what it holds and how far that is from the wiki, never by how it is reached:
 * the endpoint URL and access token a store is configured with stay out of every response, because the
 * right to rebuild a store is not the right to read the credentials for it.
 */
class GraphStoreStatusSerializer {

	/**
	 * @return array{
	 *     name: string,
	 *     projection: ?string,
	 *     state: string,
	 *     projectionChanged: ?string,
	 *     activeRun: ?array<string, mixed>,
	 *     lastSuccessfulRun: ?array<string, mixed>
	 * }
	 */
	public function storeToArray( GraphStoreStatus $status ): array {
		return [
			'name' => $status->name,
			'projection' => $status->projection,
			'state' => $status->state->value,
			'projectionChanged' => self::asIso8601( $status->projectionChanged ),
			'activeRun' => $status->activeRun === null ? null : $this->runToArray( $status->activeRun ),
			'lastSuccessfulRun' => $status->lastSuccessfulRun === null
				? null
				: self::finishedRunToArray( $status->lastSuccessfulRun ),
		];
	}

	/**
	 * The reason a run ended is deliberately absent. A backend reports an unreachable server by quoting
	 * the endpoint it tried, and the run keeps that message for an operator reading the records — which
	 * is not the same audience as whoever may call this.
	 *
	 * @return array{
	 *     id: int,
	 *     store: string,
	 *     status: string,
	 *     phase: string,
	 *     processed: int,
	 *     failed: int,
	 *     trigger: string,
	 *     started: ?string
	 * }
	 */
	public function runToArray( RebuildRun $run ): array {
		return [
			'id' => $run->id,
			'store' => $run->store,
			'status' => $run->status->value,
			'phase' => $run->phase->value,
			'processed' => $run->processed,
			'failed' => $run->failed,
			'trigger' => $run->trigger->value,
			'started' => self::asIso8601( $run->started ),
		];
	}

	/**
	 * @return array{finished: ?string, processed: int, failed: int}
	 */
	private static function finishedRunToArray( RebuildRun $run ): array {
		return [
			'finished' => self::asIso8601( $run->finished ),
			'processed' => $run->processed,
			'failed' => $run->failed,
		];
	}

	private static function asIso8601( ?string $timestamp ): ?string {
		if ( $timestamp === null ) {
			return null;
		}

		$converted = MWTimestamp::convert( TS_ISO_8601, $timestamp );

		return $converted === false ? null : $converted;
	}

}
