<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageIdentifiers {

	public function __construct(
		private readonly PageId $id,
		private readonly string $title,
	) {
	}

	public function getId(): PageId {
		return $this->id;
	}

	public function getTitle(): string {
		return $this->title;
	}

}
