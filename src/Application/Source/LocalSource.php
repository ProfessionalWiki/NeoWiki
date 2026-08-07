<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Source;

use Closure;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Source\Source;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * This wiki's own Subjects and Schemas: the revision slot (ADR 4) and the Schema namespace, reached as a
 * {@see Source}. It registers under the MediaWiki Wiki ID, which is what a bare Subject id resolves to.
 */
readonly class LocalSource implements Source {

	/**
	 * @param Closure(): SubjectLookup $subjectLookup Built per call rather than up front: reaching a
	 *   Subject by id goes through the subject-to-page index, which lives in the graph projection, so a
	 *   wiki with no graph backend cannot build the lookup at all — while its Schemas resolve fine.
	 */
	public function __construct(
		private Closure $subjectLookup,
		private SchemaLookup $schemaLookup,
		private string $baseUri,
	) {
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return ( $this->subjectLookup )()->getSubject( $id );
	}

	public function getSubjects( SubjectIdList $ids ): SubjectMap {
		return ( $this->subjectLookup )()->getSubjects( $ids );
	}

	public function getSchema( SchemaName $name ): ?Schema {
		return $this->schemaLookup->getSchema( $name );
	}

	public function isEditable(): bool {
		return true;
	}

	public function isValidLocalId( string $localId ): bool {
		return SubjectId::isValidLocalId( $localId );
	}

	public function getBaseUri(): string {
		return $this->baseUri;
	}

}
