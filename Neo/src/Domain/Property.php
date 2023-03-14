<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class Property {

	public function __construct(
		private PropertyId $id,
		private PropertyType $type,
	) {
	}

}
