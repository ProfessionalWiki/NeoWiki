<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use Psr\Log\LoggerInterface;

class SubjectUpdaterFactory {

	public function __construct(
		private readonly SchemaLookup $schemaLookup,
		private readonly LoggerInterface $logger
	) {
	}

	public function newSubjectUpdater( TransactionInterface $transaction, PageId $pageId ): SubjectUpdater {
		return new SubjectUpdater(
			$this->schemaLookup,
			$transaction,
			$pageId,
			$this->logger
		);
	}

}
