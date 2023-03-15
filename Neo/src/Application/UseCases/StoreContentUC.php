<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\UseCases;

use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\Relation;
use ProfessionalWiki\NeoWiki\Domain\RelationId;
use ProfessionalWiki\NeoWiki\Domain\RelationList;
use ProfessionalWiki\NeoWiki\Domain\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Subject;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectProperties;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeId;
use ProfessionalWiki\NeoWiki\Domain\SubjectTypeIdList;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;
use stdClass;

class StoreContentUC {

	public function __construct(
		private readonly QueryStore $queryStore,
	) {
	}

	public function storeContent( RenderedRevision $renderedRevision, UserIdentity $user ): void {
		foreach ( $renderedRevision->getRevision()->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {
				$this->queryStore->saveSubject( $this->contentToSubject( $content, $renderedRevision ) );
			}
		}
	}

	private function contentToSubject( SubjectContent $content, RenderedRevision $renderedRevision ): Subject {
		$jsonArray = (array)$content->getData()->getValue();

		return new Subject(
			id: new SubjectId( $renderedRevision->getRevision()->getPage()->getDBkey() ), // TODO: decouple from page to support multiple subjects per page
			types: $this->newSubjectTypeIdList( $jsonArray ),
			relations: $this->newRelationList( $jsonArray ),
			properties: $this->newSubjectProperties( $jsonArray ),
		);
	}

	private function newSubjectTypeIdList( array $jsonArray ): SubjectTypeIdList {
		return new SubjectTypeIdList(
			array_map(
				fn( string $id ) => new SubjectTypeId( $id ),
				(array)( $jsonArray['types'] ?? [] )
			)
		);
	}

	private function newRelationList( array $jsonArray ): RelationList {
		$relations = (array)( $jsonArray['relations'] ?? [] );

		return new RelationList(
			array_map(
				fn( string $id, stdClass $relation ) => new Relation(
					id: new RelationId( $id ),
					type: new RelationTypeId( $relation->type ),
					target: new SubjectId( $relation->target ),
					properties: new RelationProperties( (array)( ( (array)$relation )['properties'] ?? [] ) ),
				),
				array_keys( $relations ),
				$relations
			)
		);
	}

	private function newSubjectProperties( array $jsonArray ): SubjectProperties {
		return new SubjectProperties( (array)( $jsonArray['properties'] ?? [] ) );
	}

}
