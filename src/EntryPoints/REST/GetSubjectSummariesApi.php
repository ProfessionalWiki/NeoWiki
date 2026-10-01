<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseException;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\InvalidSubjectSummaryCursorException;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaries;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryQuery;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySort;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetSubjectSummariesApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function __construct(
		private readonly SubjectSummaryLookup $lookup,
	) {
	}

	public function run(): Response {
		$params = $this->getValidatedParams();
		$sort = SubjectSummarySort::from( $params['sort'] );
		$direction = $sort === SubjectSummarySort::Newest ? SortDirection::Descending : SortDirection::from( $params['direction'] );

		$after = $this->cursorFrom( $params['cursor'], $sort, $direction );

		try {
			$schemaName = $params['schema'] === null ? null : new SchemaName( $params['schema'] );
		} catch ( InvalidArgumentException ) {
			// No Subject has a Schema that cannot exist, such as one named "Subject".
			return $this->listingResponse( new SubjectSummaries( [], null ) );
		}

		return $this->listingResponse( $this->lookup->getSubjectSummaries( new SubjectSummaryQuery(
			schemaName: $schemaName,
			search: trim( $params['search'] ),
			sort: $sort,
			direction: $direction,
			after: $after,
			limit: $params['limit'],
		) ) );
	}

	private function listingResponse( SubjectSummaries $summaries ): Response {
		return $this->getResponseFactory()->createJson( [
			'subjects' => array_map( $this->serialize( ... ), $summaries->summaries ),
			'nextCursor' => $summaries->nextCursor?->encode(),
		] );
	}

	private function cursorFrom( ?string $cursor, SubjectSummarySort $sort, SortDirection $direction ): ?SubjectSummaryCursor {
		if ( $cursor === null || $cursor === '' ) {
			return null;
		}

		try {
			return SubjectSummaryCursor::decode( $cursor, $sort, $direction );
		} catch ( InvalidSubjectSummaryCursorException ) {
			// The structured body matches the other handlers' createHttpError responses.
			throw new ResponseException(
				$this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid cursor' ] )
			);
		}
	}

	/**
	 * @return array<string, string|int|bool>
	 */
	private function serialize( SubjectSummary $summary ): array {
		return [
			'id' => $summary->subjectId,
			'displayName' => $summary->displayName,
			'displayNameIsGenerated' => $summary->displayNameIsGenerated,
			'schema' => $summary->schemaName,
			'pageId' => $summary->pageId,
			'pageTitle' => $summary->pageTitle,
			'lastEdited' => $summary->lastEdited,
		];
	}

	public function getParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => null,
				self::PARAM_DESCRIPTION => 'Only Subjects of this Schema (case-sensitive). Omit it for every Schema.',
			],
			'search' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
				self::PARAM_DESCRIPTION => 'Keeps Subjects whose name or page title contains it, in any case, or whose ID starts with it.',
			],
			'sort' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => array_column( SubjectSummarySort::cases(), 'value' ),
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => SubjectSummarySort::Newest->value,
				self::PARAM_DESCRIPTION => 'Order of the list. "newest" is by creation, newest first, and ignores direction.',
			],
			'direction' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => array_column( SortDirection::cases(), 'value' ),
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => SortDirection::Ascending->value,
				self::PARAM_DESCRIPTION => 'Direction of the sort.',
			],
			'cursor' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => null,
				self::PARAM_DESCRIPTION => 'Opaque pagination cursor: the nextCursor of the previous response. Omit for the first page.',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of Subjects to return.',
			],
		];
	}

}
