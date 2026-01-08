<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

interface IdGenerator {

	/**
	 * Generates an ID of length 14
	 */
	public function generate(): string;

}
