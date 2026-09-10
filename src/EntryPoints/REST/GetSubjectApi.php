<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	private const string EXPAND_PAGE = 'page';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( string $subjectId ): Response {
		$presenter = new RestGetSubjectPresenter();
		$revisionId = $this->getValidatedParams()['revisionId'] ?? null;
		$latest = $this->getValidatedParams()['latest'] ?? false;

		if ( $latest && $revisionId !== null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'The latest and revisionId parameters are mutually exclusive.',
			] );
		}

		$query = $this->newGetSubjectQuery( $presenter, $subjectId, $revisionId, $latest );

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

	private function newGetSubjectQuery(
		RestGetSubjectPresenter $presenter,
		string $subjectId,
		?int $revisionId,
		bool $latest
	): GetSubjectQuery|Response {
		if ( $revisionId !== null ) {
			return $this->newQueryForRevisionId( $presenter, $revisionId );
		}

		if ( $latest ) {
			return $this->newQueryForCurrentRevision( $presenter, $subjectId );
		}

		return NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter, $this->getAuthority() );
	}

	private function newQueryForRevisionId( RestGetSubjectPresenter $presenter, int $revisionId ): GetSubjectQuery|Response {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $revisionId );

		// A revision the viewer may not see answers exactly like a nonexistent one: revision ids are
		// sequential, so any distinguishable answer is a sweepable existence oracle over restricted
		// pages (#1046), and over the unapproved revisions an approval extension hides.
		if ( $revision === null || !$this->revisionIsReadable( $revision ) ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Revision not found: ' . $revisionId,
			] );
		}

		return NeoWikiExtension::getInstance()->newGetSubjectQueryForRevision( $presenter, $revision, $this->getAuthority() );
	}

	private function revisionIsReadable( RevisionRecord $revision ): bool {
		return $this->revisionPageIsReadable( $revision->getPageId() )
			&& NeoWikiExtension::getInstance()->getRevisionPolicy()
				->revisionIsReadableBy( $revision, $this->getAuthority() );
	}

	private function revisionPageIsReadable( int $pageId ): bool {
		return NeoWikiExtension::getInstance()
			->newPageReadAuthorizer( $this->getAuthority() )
			->authorizeReadByPageId( new PageId( $pageId ) );
	}

	/**
	 * A viewer the policy does not let see the current revision is answered as an absent Subject is,
	 * so that asking for a draft cannot confirm a harvested Subject id exists. A Subject that
	 * resolves to no page falls through to the published read, which answers not-found for it.
	 */
	private function newQueryForCurrentRevision( RestGetSubjectPresenter $presenter, string $subjectId ): GetSubjectQuery|Response {
		$revision = $this->getCurrentRevisionOfSubjectPage( $subjectId );

		if ( $revision === null ) {
			return NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter, $this->getAuthority() );
		}

		if ( !NeoWikiExtension::getInstance()->getRevisionPolicy()->revisionIsReadableBy( $revision, $this->getAuthority() ) ) {
			$presenter->presentSubjectNotFound();

			return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		}

		return NeoWikiExtension::getInstance()->newGetLatestSubjectQuery( $presenter, $this->getAuthority() );
	}

	private function getCurrentRevisionOfSubjectPage( string $subjectId ): ?RevisionRecord {
		$pageIdentifiers = NeoWikiExtension::getInstance()
			->getPageIdentifiersLookup()
			->getPageIdOfSubject( new SubjectId( $subjectId ) );

		if ( $pageIdentifiers === null ) {
			return null;
		}

		return MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionByPageId( $pageIdentifiers->getId()->id );
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
				self::PARAM_DESCRIPTION => 'Revision ID to fetch the Subject at. Defaults to the revision the wiki publishes.',
			],
			'latest' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
				self::PARAM_DESCRIPTION => 'Return the hosting page\'s current revision, for editing, rather than the revision the wiki publishes. Requires the viewer to be allowed to see that revision. Cannot be combined with revisionId.',
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
