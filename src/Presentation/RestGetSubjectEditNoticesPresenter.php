<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices\GetSubjectEditNoticesPresenter;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;

class RestGetSubjectEditNoticesPresenter implements GetSubjectEditNoticesPresenter {

	private array $apiResponse = [ 'notices' => [] ];

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentNotices( array $notices ): void {
		$this->apiResponse = [
			'notices' => array_map(
				static fn ( SubjectEditNotice $notice ): array => [
					'key' => $notice->key,
					'html' => $notice->html,
				],
				array_values( $notices )
			),
		];
	}

}
