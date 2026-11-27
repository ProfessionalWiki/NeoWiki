<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;

class SpecialNeoWiki extends SpecialPage {

	public function __construct() {
		parent::__construct( 'NeoWiki' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::GettingStarted->value, true );

		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );

		$attributes = [ 'id' => 'ext-neowiki-overview' ];

		// Special:GraphStores is restricted, and Special:SpecialPages lists it only to those who may use
		// it. Offering a link here to anyone else would lead to a permissions error instead of a page.
		if ( $this->getAuthority()->isAllowed( NeoWikiExtension::ADMIN_RIGHT ) ) {
			$attributes['data-mw-neowiki-can-manage-graph-stores'] = 'true';
		}

		if ( $this->canEditConfiguration() ) {
			$attributes['data-mw-neowiki-can-edit-configuration'] = 'true';
		}

		if ( NeoWikiExtension::getInstance()->getPageIdentifiersLookup()->wikiHasSubjects() ) {
			$attributes['data-mw-neowiki-wiki-has-subjects'] = 'true';
		}

		$attributes['data-mw-neowiki-extension-links'] = json_encode( $this->extensionLinks(), JSON_THROW_ON_ERROR );

		$this->getOutput()->addHTML( Html::element( 'div', $attributes ) );
	}

	/**
	 * The pages other extensions add to the map, each listed in the NeoWikiOverviewLinks attribute of its
	 * extension.json with the keys of its label and description messages, and any rights a user needs to get it.
	 *
	 * @return list<array{page: string, label: string, description: string}>
	 */
	private function extensionLinks(): array {
		return array_values( array_map(
			fn ( array $link ) => [
				'page' => $link['page'],
				'label' => $this->msg( $link['label'] )->text(),
				'description' => $this->msg( $link['description'] )->text(),
			],
			array_filter(
				ExtensionRegistry::getInstance()->getAttribute( 'NeoWikiOverviewLinks' ),
				fn ( array $link ) => $this->mayOpen( $link['rights'] ?? [] )
			)
		) );
	}

	/**
	 * @param string[] $rights
	 */
	private function mayOpen( array $rights ): bool {
		return $rights === [] || $this->getAuthority()->isAllowedAll( ...$rights );
	}

	/**
	 * The map offers the configuration page only to those who may edit it, as with Graph stores.
	 */
	private function canEditConfiguration(): bool {
		if ( !NeoWikiExtension::getInstance()->isInWikiConfigEnabled() ) {
			return false;
		}

		$configTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE );

		return $this->getAuthority()->probablyCan( 'edit', $configTitle );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-neowiki' );
	}

}
