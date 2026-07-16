<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
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
			return NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter, $this->getAuthority() );
		}

		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $revisionId );

		// A revision on an unreadable page answers exactly like a nonexistent revision:
		// revision ids are sequential, so any distinguishable answer is a sweepable
		// existence oracle over restricted pages (#1046).
		if ( $revision === null || !$this->revisionPageIsReadable( $revision->getPageId() ) ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Revision not found: ' . $revisionId,
			] );
		}

		return NeoWikiExtension::getInstance()->newGetSubjectQueryForRevision( $presenter, $revision, $this->getAuthority() );
	}

	private function revisionPageIsReadable( int $pageId ): bool {
		return NeoWikiExtension::getInstance()
			->newSubjectReadAuthorizer( $this->getAuthority() )
			->authorizeRead( new PageId( $pageId ) );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject. 15 characters, starting with "s".',
			],
			'revisionId' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Revision ID to fetch the Subject at. Defaults to the latest revision.',
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_PAGE,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Embed related data in the response. Accepted values: "page", "relations".',
			],
		];
	}

}
