<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MessageLocalizer;

/**
 * How a Subject's name is shown wherever one stands alone: as it is when somebody chose it, and
 * marked as the stand-in it is when the wiki supplied it.
 *
 * The name itself comes from {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName}.
 */
class SubjectNameMessage {

	public static function from( MessageLocalizer $messageLocalizer, string $name, bool $isGenerated ): Message {
		if ( $isGenerated ) {
			return $messageLocalizer->msg( 'neowiki-subject-generated-name' )->plaintextParams( $name );
		}

		return ( new RawMessage( '$1' ) )->plaintextParams( $name );
	}

}
