<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageDateTime implements PageValue {

	/**
	 * @param string $timestamp In the standard MediaWiki format, ie 20230726163439
	 */
	public function __construct(
		public string $timestamp,
	) {
	}

	public function getType(): PageValueType {
		return PageValueType::Datetime;
	}

	public function getValue(): string {
		return $this->timestamp;
	}

}
