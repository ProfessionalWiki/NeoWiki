<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;

class TestPageProperties {

	public static function build(
		string $title = 'PageTitle',
		?string $creationTime = '20230726163439',
		?string $modificationTime = '20230726163439',
		array $categories = []
	): PageProperties {
		return new PageProperties(
			title: $title,
			creationTime: $creationTime,
			modificationTime: $modificationTime,
			categories: $categories,
			lastEditor: 'Chuck Norris'
		);
	}

}
