<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use LogicException;
use MediaWiki\Page\PageIdentity;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;

class InMemorySubjectContentRepository implements SubjectContentRepository {

	private ?SubjectContent $defaultContent = null;

	/** @var array<string, ?SubjectContent> */
	private array $contentByPageDbKey = [];

	/** @var array<int, ?SubjectContent> */
	private array $contentByRevisionId = [];

	public function __construct( ?PageSubjects $defaultPageSubjects = null ) {
		if ( $defaultPageSubjects !== null ) {
			$this->defaultContent = new InMemorySubjectContent( $defaultPageSubjects );
		}
	}

	public function setContentForPage( string $pageDbKey, ?PageSubjects $pageSubjects ): void {
		$this->contentByPageDbKey[$pageDbKey] = $pageSubjects === null
			? null
			: new InMemorySubjectContent( $pageSubjects );
	}

	public function setContentForRevision( int $revisionId, ?PageSubjects $pageSubjects ): void {
		$this->contentByRevisionId[$revisionId] = $pageSubjects === null
			? null
			: new InMemorySubjectContent( $pageSubjects );
	}

	public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent {
		throw new LogicException( 'Not implemented. Wire up per-PageId storage when a test needs it.' );
	}

	public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
		return $this->contentByPageDbKey[$pageIdentity->getDBkey()] ?? $this->defaultContent;
	}

	public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent {
		return $this->contentByRevisionId[$revisionId] ?? $this->defaultContent;
	}

	public function editSubjectContent(
		SubjectContent $subjectContent,
		PageId $pageId,
		string $editSummary
	): void {
		throw new LogicException( 'Not implemented. Wire up edit storage when a test needs it.' );
	}

}
