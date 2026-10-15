<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * The Host Subject a Dependent Subject is named after, as in "Birth of Pablo Picasso". Sent as parts rather than as a
 * name, so that the reader's language composes it. Fields mean what they mean on
 * {@see \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem}.
 */
readonly class HostSubject {

	public function __construct(
		public string $id,
		/**
		 * @var string|array{source: string, name: string}
		 */
		public string|array $schema,
		public string $displayName,
		public bool $displayNameIsGenerated,
		/**
		 * Null unless this Host Subject is a Dependent Subject too.
		 */
		public ?HostSubject $hostSubject,
	) {
	}

}
