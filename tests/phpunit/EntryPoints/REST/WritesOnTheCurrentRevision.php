<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\Rdbms\IDBAccessObject;

trait WritesOnTheCurrentRevision {

	/**
	 * $body as a client sends it that read the Subject right before writing it. A body naming its own base keeps it.
	 *
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	private function onTheCurrentRevision( string $subjectId, array $body ): array {
		return $body + [ 'baseRevisionId' => $this->currentRevisionOf( $subjectId ) ];
	}

	/**
	 * 0, which is no revision, for a Subject no page holds.
	 */
	private function currentRevisionOf( string $subjectId ): int {
		$extension = NeoWikiExtension::getInstance();
		$id = $extension->getSubjectIdParser()->parse( $subjectId );
		$pageId = $id === null ? null : $extension->getPageIdentifiersLookup()->getPageIdOfSubject( $id )?->getId();

		return $pageId === null ? 0 : Title::newFromID( $pageId->id )?->getLatestRevID( IDBAccessObject::READ_LATEST ) ?? 0;
	}

}
