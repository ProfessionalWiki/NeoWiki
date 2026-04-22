<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;

class PageToolsBuilder {

	/**
	 * @return list<array<string, mixed>>
	 */
	public function build(
		Title $title,
		bool $isContentNamespace,
		bool $canCreateMainSubject,
		bool $isLatestRevision,
		bool $devUiEnabled
	): array {
		if ( !$isContentNamespace ) {
			return [];
		}

		$items = [];

		if ( $canCreateMainSubject && $isLatestRevision ) {
			$items[] = [
				'text' => wfMessage( 'neowiki-page-tools-create-subject' )->text(),
				'href' => '#',
				'id' => 't-neowiki-create-subject',
				'data' => [
					'mw-neowiki-action' => 'open-subject-creator',
				],
			];
		}

		if ( $title->canExist() ) {
			$items[] = [
				'text' => wfMessage( 'neowiki-page-tools-manage-subjects' )->text(),
				'href' => $title->getLocalURL( [ 'action' => SubjectsAction::ACTION_NAME ] ),
				'id' => 't-neowiki-manage-subjects',
			];
		}

		if ( $devUiEnabled ) {
			$items[] = [
				'text' => wfMessage( 'neowiki-page-tools-edit-json' )->text(),
				'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'NeoJson', $title->getFullText() ),
				'id' => 't-neowiki-edit-json',
			];
		}

		return $items;
	}

}
