<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class CompositeGraphDatabasePlugin implements GraphDatabasePlugin {

	/**
	 * @var GraphDatabasePlugin[]
	 */
	private array $plugins;

	public function __construct( GraphDatabasePlugin ...$plugins ) {
		$this->plugins = $plugins;
	}

	public function savePage( Page $page ): void {
		foreach ( $this->plugins as $plugin ) {
			$plugin->savePage( $page );
		}
	}

	public function deletePage( PageId $pageId ): void {
		foreach ( $this->plugins as $plugin ) {
			$plugin->deletePage( $pageId );
		}
	}

}
