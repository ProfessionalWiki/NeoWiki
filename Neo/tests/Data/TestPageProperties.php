<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;

class TestPageProperties {

	public static function build( string $title = null ): PageProperties {
		return new PageProperties(
			title: $title ?? 'PageTitle'
		);
	}

}
