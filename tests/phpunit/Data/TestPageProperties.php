<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Page\PageDateTime;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;

class TestPageProperties {

	/**
	 * @param array<string, mixed> $extraProperties
	 */
	public static function build(
		string $title = 'PageTitle',
		int $namespaceId = 0,
		string $creationTime = '20230726163439',
		string $modificationTime = '20230726163439',
		array $categories = [],
		string $lastEditor = 'Chuck Norris',
		array $extraProperties = [],
	): PageProperties {
		return new PageProperties( array_merge(
			[
				'name' => $title,
				'namespaceId' => $namespaceId,
				'creationTime' => new PageDateTime( $creationTime ),
				'lastUpdated' => new PageDateTime( $modificationTime ),
				'categories' => $categories,
				'lastEditor' => $lastEditor,
			],
			$extraProperties,
		) );
	}

}
