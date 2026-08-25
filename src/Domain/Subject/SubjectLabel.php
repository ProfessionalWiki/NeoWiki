<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

readonly class SubjectLabel {

	public function __construct(
		public string $text,
	) {
	}

	/**
	 * What a request, a slot or an import hands over: a label of nothing but whitespace is absence
	 * written the other way, and becomes no label so that no reader has to tell the two apart.
	 */
	public static function fromText( ?string $text ): ?self {
		if ( $text === null || trim( $text ) === '' ) {
			return null;
		}

		return new self( $text );
	}

}
