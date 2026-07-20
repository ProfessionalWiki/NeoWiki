<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\SubjectIdMinter;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Mints a batch of unused Subject IDs so an importer can wire relations between interlinked Subjects
 * before creating them. Stateless: no reservation, no graph dependency. See docs/api/subject-format.md.
 */
class MintSubjectIdsApi extends SimpleHandler {

	private const int MIN_COUNT = 1;
	private const int MAX_COUNT = 1000;

	public function __construct(
		private readonly SubjectIdMinter $subjectIdMinter,
	) {
	}

	public function run(): Response {
		$count = $this->getValidatedBody()['count'];

		if ( $count < self::MIN_COUNT || $count > self::MAX_COUNT ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => 'count must be between ' . self::MIN_COUNT . ' and ' . self::MAX_COUNT . '.',
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'subjectIds' => array_map(
				static fn ( SubjectId $id ): string => $id->text,
				$this->subjectIdMinter->mint( $count )
			),
		] );
	}

	public function getBodyParamSettings(): array {
		return [
			'count' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Number of Subject IDs to mint, between '
					. self::MIN_COUNT . ' and ' . self::MAX_COUNT . ' inclusive.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
