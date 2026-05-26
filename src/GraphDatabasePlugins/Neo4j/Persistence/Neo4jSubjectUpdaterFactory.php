<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Psr\Log\LoggerInterface;

class Neo4jSubjectUpdaterFactory {

	public function __construct(
		private readonly SchemaLookup $schemaLookup,
		private readonly Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private readonly LoggerInterface $logger
	) {
	}

	public function newSubjectUpdater( TransactionInterface $transaction, PageId $pageId ): Neo4jSubjectUpdater {
		return new Neo4jSubjectUpdater(
			$transaction,
			$pageId,
			$this->schemaLookup,
			$this->valueBuilderRegistry,
			$this->logger
		);
	}

}
