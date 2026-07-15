<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Source\Source;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class StubSource implements Source {

	public function __construct(
		private readonly ?Subject $subject = null,
		private readonly ?Schema $schema = null,
	) {
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return $this->subject;
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		return $this->schema;
	}

	public function isEditable(): bool {
		return false;
	}

	public function isValidLocalId( string $localId ): bool {
		return true;
	}

	public function getBaseUri(): ?string {
		return null;
	}

}
