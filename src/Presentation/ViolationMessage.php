<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use Wikimedia\Message\MessageValue;

/**
 * The message a Violation is answered with: the `neowiki-field-<code>` message the frontend also
 * resolves, under the property's name when the violation carries one.
 *
 * The args are the caller's own input, so they are plaintext: the REST text formatter formats for
 * FORMAT_TEXT, which preprocesses the message as wikitext, and a text param is substituted before
 * that pass - a value of `{{:Some page}}` would come back as that page's content, past every read
 * check. A plaintext param is substituted after it, so it reaches the caller as they sent it.
 */
final class ViolationMessage {

	public static function valueOf( Violation $violation ): MessageValue {
		$fieldMessage = MessageValue::new( 'neowiki-field-' . $violation->code )->plaintextParams( ...$violation->args );

		if ( $violation->propertyName === null ) {
			return $fieldMessage;
		}

		// The property name is plaintext (it is caller data); the field message is a nested
		// MessageValue, whose own caller data is already plaintext.
		return MessageValue::new( 'neowiki-rejected-value' )
			->plaintextParams( $violation->propertyName->text )
			->params( $fieldMessage );
	}

}
