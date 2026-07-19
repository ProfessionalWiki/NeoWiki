<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb\Specials;

use Exception;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class SpecialRedHerbContentPageCount extends SpecialPage {

	public function __construct() {
		parent::__construct( 'RedHerbContentPageCount' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$out = $this->getOutput();

		try {
			$result = NeoWikiExtension::getInstance()->newCypherQueryService()->execute( new Neo4jQueryRequest(
				cypher: 'MATCH (page:Page) WHERE page.namespaceId = $namespaceId RETURN count(page) AS pageCount',
				parameters: [ 'namespaceId' => NS_MAIN ],
				limits: Neo4jQueryLimits::forUser( $this->getUser() ),
			) );
		} catch ( Exception $e ) {
			$out->addWikiMsg( 'redherb-content-page-count-error', $e->getMessage() );
			return;
		}

		$out->addWikiMsg( 'redherb-content-page-count', $result->rows[0]['pageCount'] );
	}

	public function getDescription(): Message {
		return $this->msg( 'redherb-special-content-page-count' );
	}

}
