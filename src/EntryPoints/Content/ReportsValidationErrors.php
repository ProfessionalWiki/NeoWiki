<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Message\Message;
use StatusValue;
use Wikimedia\Message\ListType;
use Wikimedia\Message\MessageValue;

trait ReportsValidationErrors {

	/**
	 * @param array<string, string> $errors Validation message by JSON pointer
	 */
	private function reportValidationErrors( StatusValue $status, string $messageKey, string $detailKey, array $errors ): void {
		$details = [];

		foreach ( $errors as $pointer => $message ) {
			$details[] = MessageValue::new( $detailKey )->params( $pointer, $message );
		}

		$this->reportErrors( $status, $messageKey, $details );
	}

	/**
	 * One message carrying every error rather than one message per error: MediaWiki's REST
	 * layer forwards only the first message a Status holds.
	 *
	 * @param MessageValue[] $errors
	 */
	private function reportErrors( StatusValue $status, string $messageKey, array $errors ): void {
		$status->fatal( $messageKey, count( $errors ), Message::listParam( $errors, ListType::SEMICOLON ) );
	}

}
