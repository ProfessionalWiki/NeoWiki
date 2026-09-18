<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MessageLocalizer;

/**
 * A Subject's name as a message: verbatim when chosen, marked as a stand-in when the wiki supplied
 * it. The name comes from {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName}.
 */
class SubjectNameMessage {

	public static function from( MessageLocalizer $messageLocalizer, string $name, bool $isGenerated ): Message {
		if ( $isGenerated ) {
			return $messageLocalizer->msg( 'neowiki-subject-generated-name' )->plaintextParams( $name );
		}

		return ( new RawMessage( '$1' ) )->plaintextParams( $name );
	}

}
