<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * A Subject's name as a message: verbatim when someone chose it, and otherwise the Subject's id,
 * marked as the stand-in it is. The chosen name comes from
 * {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName}.
 */
class SubjectNameMessage {

	public static function from( MessageLocalizer $messageLocalizer, SubjectId $subjectId, ?string $chosenName ): Message {
		if ( $chosenName === null ) {
			return $messageLocalizer->msg( 'neowiki-subject-generated-name' )->plaintextParams( $subjectId->text );
		}

		return ( new RawMessage( '$1' ) )->plaintextParams( $chosenName );
	}

}
