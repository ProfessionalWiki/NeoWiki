<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\View;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, DisplayRule>
 */
class DisplayRules implements IteratorAggregate {

	/**
	 * @var DisplayRule[]
	 */
	private readonly array $rules;

	/**
	 * @param DisplayRule[] $rules
	 */
	public function __construct( array $rules ) {
		$this->rules = array_values( $rules );
	}

	public function isEmpty(): bool {
		return $this->rules === [];
	}

	public function getIterator(): Traversable {
		return new ArrayIterator( $this->rules );
	}

}
