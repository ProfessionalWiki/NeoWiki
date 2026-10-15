<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;

final class SubjectSummarySerializer {

	/**
	 * @return array<string, mixed>
	 */
	public static function serialize( SubjectSummary $summary ): array {
		$entry = [
			'id' => $summary->subjectId,
			'displayName' => $summary->displayName,
			'displayNameIsGenerated' => $summary->displayNameIsGenerated,
			'schema' => $summary->schemaName,
			'pageId' => $summary->pageId,
			'pageTitle' => $summary->pageTitle,
			'lastEdited' => $summary->lastEdited,
		];

		if ( $summary->hostSubject !== null ) {
			$entry['hostSubject'] = SubjectPresentationSerializer::serializeHostSubject( $summary->hostSubject );
		}

		return $entry;
	}

}
