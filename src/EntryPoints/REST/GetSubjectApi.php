<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectApi extends SimpleHandler {

	private const string EXPAND_PAGE = 'page';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( string $subjectId ): Response {
		$presenter = new RestGetSubjectPresenter();
		$revisionId = $this->getValidatedParams()['revisionId'] ?? null;

		$query = $this->newGetSubjectQuery( $presenter, $revisionId );

		if ( $query instanceof Response ) {
			return $query;
		}

		$expendOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );

		$query->execute(
			subjectId: $subjectId,
			includePageIdentifiers: in_array( self::EXPAND_PAGE, $expendOptions ),
			includeReferencedSubjects: in_array( self::EXPAND_RELATIONS, $expendOptions )
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	private function newGetSubjectQuery( RestGetSubjectPresenter $presenter, ?int $revisionId ): GetSubjectQuery|Response {
		if ( $revisionId === null ) {
			return NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter );
		}

		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $revisionId );

		if ( $revision === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Revision not found: ' . $revisionId,
			] );
		}

		return NeoWikiExtension::getInstance()->newGetSubjectQueryForRevision( $presenter, $revision );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'revisionId' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_PAGE,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

}
