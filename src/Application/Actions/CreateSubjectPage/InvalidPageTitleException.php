<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage;

use InvalidArgumentException;

/**
 * The title asked for titles no page that could be created here. Nothing is titled in its place:
 * only the caller knows what they meant by it.
 */
class InvalidPageTitleException extends InvalidArgumentException {

	public function __construct( public readonly string $pageTitle ) {
		parent::__construct( "\"$pageTitle\" is not a main namespace page title that can be created here" );
	}

}
