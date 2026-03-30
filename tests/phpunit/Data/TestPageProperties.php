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
		string $creationTime = '20230726163439',
		string $modificationTime = '20230726163439',
		array $categories = [],
		string $lastEditor = 'Chuck Norris',
		array $extraProperties = [],
	): PageProperties {
		return new PageProperties( array_merge(
			[
				'name' => $title,
				'creationTime' => new PageDateTime( $creationTime ),
				'modificationTime' => new PageDateTime( $modificationTime ),
				'categories' => $categories,
				'lastEditor' => $lastEditor,
			],
			$extraProperties,
		) );
	}

}
