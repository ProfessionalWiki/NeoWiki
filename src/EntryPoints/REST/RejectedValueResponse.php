<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use ProfessionalWiki\NeoWiki\Application\RejectedValueException;
use ProfessionalWiki\NeoWiki\Presentation\ViolationMessage;
use ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer;

/**
 * The 400 a write endpoint answers when a Property Type could not canonicalize a value.
 *
 * Body: `status` and `message`, as every 400 from these endpoints; `violation`, serialized as the
 * validate endpoints serialize theirs; and `messageTranslations`, the message in the wiki's content
 * language with English alongside. `message` is the first translation, or the exception's own text
 * when no formatter is configured.
 */
trait RejectedValueResponse {

	private function newRejectedValueResponse( RejectedValueException $exception ): Response {
		$messageValue = ViolationMessage::valueOf( $exception->violation );
		$translations = $this->getResponseFactory()->formatMessage( $messageValue )['messageTranslations'] ?? [];

		return $this->getResponseFactory()->createHttpError( 400, [
			'status' => 'error',
			'message' => reset( $translations ) ?: $exception->getMessage(),
			'violation' => ViolationSerializer::serialize( $exception->violation ),
			'messageTranslations' => $translations,
		] );
	}

}
