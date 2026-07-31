<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * A Subject of a page being written to the graph, with the Schema its Statements are read against
 * and whether it is the main Subject of that page.
 */
readonly class Neo4jPageSubject {

	public function __construct(
		public Subject $subject,
		public Schema $schema,
		public bool $isMainSubject,
	) {
	}

	public function getId(): string {
		return $this->subject->id->text;
	}

}
