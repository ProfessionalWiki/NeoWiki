<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use CommentStoreComment;
use Laudis\Neo4j\Contracts\TransactionInterface;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use Status;
use Title;
use Laudis\Neo4j\ClientBuilder;

class MediaWikiHooks {

	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if ( str_ends_with( $title->getText(), '.node' ) ) {
			$model = SubjectContent::CONTENT_MODEL_ID;
		}
	}

	public static function onMultiContentSave(
		RenderedRevision $renderedRevision,
		UserIdentity $user,
		CommentStoreComment $summary,
		$flags,
		Status $hookStatus
	): void {
		foreach ( $renderedRevision->getRevision()->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {

				$client = ClientBuilder::create() // TODO: inject
					->withDriver('bolt', 'bolt://neo4j:' . $_ENV['NEO4J_PASSWORD'] . '@neo:7687')
					->withDefaultDriver('bolt')
					->build();

				$properties = (array)$content->getData()->getValue();
				$properties['name'] = $renderedRevision->getRevision()->getPage()->getDBkey(); // TODO: real title

				$client->writeTransaction( static function ( TransactionInterface $tsx ) use ( $properties ) {
					$tsx->run(
						<<<'CYPHER'
CREATE ($props)
CYPHER,

						[ 'props' => $properties ] // TODO: verify security
					);
				} );
			}
		}
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( $model === SubjectContent::CONTENT_MODEL_ID ) {
			$lang = 'json';
		}
	}

}
