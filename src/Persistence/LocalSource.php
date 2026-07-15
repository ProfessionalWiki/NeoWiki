<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Source\Source;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The default Source: local Subjects and Schemas stored in the wiki's revision slots, resolved
 * through the existing local persistence. Editable and versioned, with bare-nanoid localIds (ADR 23).
 */
readonly class LocalSource implements Source {

	public function __construct(
		private SubjectLookup $subjectLookup,
		private SchemaLookup $schemaLookup,
		private string $baseUri,
	) {
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return $this->subjectLookup->getSubject( $id );
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		return $this->schemaLookup->getSchema( $schemaName );
	}

	public function isEditable(): bool {
		return true;
	}

	public function isValidLocalId( string $localId ): bool {
		return SubjectId::isValid( $localId )
			&& ( new SubjectId( $localId ) )->getSource() === null;
	}

	public function getBaseUri(): ?string {
		return $this->baseUri;
	}

}
