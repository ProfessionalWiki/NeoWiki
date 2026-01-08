<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use Random\Randomizer;
use WMDE\Clock\Clock;
use WMDE\Clock\SystemClock;

class ProductionIdGenerator implements IdGenerator {

	private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	private Randomizer $randomizer;
	private Clock $clock;

	public function __construct( Randomizer $randomizer = null, Clock $clock = null ) {
		$this->randomizer = $randomizer ?? new Randomizer();
		$this->clock = $clock ?? new SystemClock();
	}

	/**
	 * Generates a sortable nanoid-style ID with length 14 (9 for microsecond timestamp, 5 for random bytes).
	 * Example: EVNrDCjgVpv9oC
	 */
	public function generate(): string {
		return $this->encodeTimestamp( $this->getTimestampInMicroseconds() )
			. $this->encodeRandom( $this->getRandomBytes( 10 ) );
	}

	private function getTimestampInMicroseconds(): int {
		return (int)( $this->clock->now()->format( 'U.u' ) * 1000000 );
	}

	private function getRandomBytes( int $length ): string {
		return $this->randomizer->getBytes( $length );
	}

	/**
	 * Encodes the timestamp into a 9-character string.
	 * Wraps after 2200.
	 */
	private function encodeTimestamp( int $timestamp ): string {
		$encoded = '';
		for ( $i = 0; $i < 9; $i++ ) {
			$encoded = self::BASE58_ALPHABET[$timestamp % 58] . $encoded;
			$timestamp = (int)( $timestamp / 58 );
		}
		return $encoded;
	}

	/**
	 * Encodes random bytes into a 5-character string.
	 * Results in 0.05% chance of collision when generating 1000 IDs.
	 * This is good enough since the prefix changes every microsecond.
	 */
	private function encodeRandom( string $random ): string {
		$encoded = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$index = ord( $random[$i] ) % 58;
			$encoded .= self::BASE58_ALPHABET[$index];
		}
		return $encoded;
	}

}
