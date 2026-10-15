<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\HostSubject;
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

	/**
	 * Names a Dependent Subject after its Schema and Host Subject, as in "Birth of Pablo Picasso".
	 *
	 * @param string|array{source: string, name: string} $schema The Dependent Subject's Schema reference.
	 */
	public static function ofDependentSubject(
		MessageLocalizer $messageLocalizer,
		string|array $schema,
		HostSubject $hostSubject
	): Message {
		return $messageLocalizer->msg( 'neowiki-dependent-subject-name' )->plaintextParams(
			is_array( $schema ) ? $schema['name'] : $schema,
			self::ofHostSubject( $messageLocalizer, $hostSubject )->text()
		);
	}

	private static function ofHostSubject( MessageLocalizer $messageLocalizer, HostSubject $hostSubject ): Message {
		if ( $hostSubject->hostSubject !== null ) {
			return self::ofDependentSubject( $messageLocalizer, $hostSubject->schema, $hostSubject->hostSubject );
		}

		return self::from(
			$messageLocalizer,
			new SubjectId( $hostSubject->id ),
			$hostSubject->displayNameIsGenerated ? null : $hostSubject->displayName
		);
	}

}
