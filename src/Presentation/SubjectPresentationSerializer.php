<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

/**
 * The one Subject shape the web API serves, so a Subject returned by a write endpoint is
 * interchangeable with the same Subject read back.
 */
class SubjectPresentationSerializer {

	/**
	 * @return array<string, mixed>
	 */
	public function serialize( GetSubjectResponseItem $item ): array {
		$entry = [
			'id' => $item->id,
			'label' => $item->label,
			'displayName' => $item->displayName,
			'schema' => $item->schema,
		];

		if ( $item->pageId !== null ) {
			$entry['pageId'] = $item->pageId;
			$entry['pageTitle'] = $item->pageTitle;
			$entry['pageNamespaceId'] = $item->pageNamespaceId;
		}

		$entry['statements'] = $item->statements;

		return $entry;
	}

}
