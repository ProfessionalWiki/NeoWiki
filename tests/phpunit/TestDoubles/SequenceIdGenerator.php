<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use LogicException;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

/**
 * Returns a scripted sequence of ids, one per generate() call. Lets a test force a collision by
 * scripting the same id twice. Throws once the script is exhausted so an over-generating bug fails
 * loudly instead of hanging.
 */
class SequenceIdGenerator implements IdGenerator {

	/** @var string[] */
	private array $ids;

	public function __construct( string ...$ids ) {
		$this->ids = $ids;
	}

	public function generate(): string {
		$id = array_shift( $this->ids );

		if ( $id === null ) {
			throw new LogicException( 'SequenceIdGenerator ran out of scripted ids' );
		}

		return $id;
	}

}
