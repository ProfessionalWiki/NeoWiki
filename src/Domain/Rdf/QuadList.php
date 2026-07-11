<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use Countable;
use InvalidArgumentException;

readonly class QuadList implements Countable {

	/**
	 * @var Quad[]
	 */
	private array $quads;

	public function __construct( Quad ...$quads ) {
		$this->quads = $quads;
	}

	/**
	 * @param Quad[] $quads
	 */
	public static function fromArray( array $quads ): self {
		foreach ( $quads as $quad ) {
			if ( !( $quad instanceof Quad ) ) {
				throw new InvalidArgumentException( 'QuadList can only contain Quad objects' );
			}
		}

		return new self( ...$quads );
	}

	public function merge( self $other ): self {
		return new self( ...$this->quads, ...$other->quads );
	}

	public function contains( Quad $needle ): bool {
		foreach ( $this->quads as $quad ) {
			if ( $quad->equals( $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return Quad[]
	 */
	public function asArray(): array {
		return $this->quads;
	}

	public function isEmpty(): bool {
		return $this->quads === [];
	}

	public function count(): int {
		return count( $this->quads );
	}

}
