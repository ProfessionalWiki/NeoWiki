<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

enum DatePrecision: string {

	case Year = 'year';
	case Month = 'month';
	case Day = 'day';

	public function isAtLeast( self $other ): bool {
		return $this->rank() >= $other->rank();
	}

	private function rank(): int {
		return match ( $this ) {
			self::Year => 0,
			self::Month => 1,
			self::Day => 2,
		};
	}

}
