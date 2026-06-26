<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PageIdentifiers {

	public function __construct(
		private PageId $id,
		private string $title,
		private int $namespaceId,
	) {
	}

	public function getId(): PageId {
		return $this->id;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getNamespaceId(): int {
		return $this->namespaceId;
	}

}
