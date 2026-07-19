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
		int $pageId,
		bool $isContentNamespace,
		bool $hasSubjects,
		bool $canCreateMainSubject,
		bool $canEditSubject,
		bool $isLatestRevision,
		bool $devUiEnabled,
		string $currentAction
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

		if ( $title->canExist() && $currentAction !== SubjectsAction::ACTION_NAME ) {
			$items[] = [
				'text' => wfMessage(
					$canEditSubject ? 'neowiki-page-tools-manage-subjects' : 'neowiki-page-tools-view-subjects'
				)->text(),
				'href' => $title->getLocalURL( [ 'action' => SubjectsAction::ACTION_NAME ] ),
				'id' => 't-neowiki-manage-subjects',
			];
		}

		if ( $hasSubjects ) {
			$items[] = [
				'text' => wfMessage( 'neowiki-page-tools-rdf' )->text(),
				'href' => wfScript( 'rest' ) . '/neowiki/v0/page/'
					. $pageId . '/rdf?format=turtle',
				'id' => 't-neowiki-rdf',
			];
		}

		if ( $devUiEnabled ) {
			$items[] = [
				'text' => wfMessage(
					$canEditSubject ? 'neowiki-page-tools-edit-json' : 'neowiki-page-tools-view-json'
				)->text(),
				'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'NeoJson', $title->getFullText() ),
				'id' => 't-neowiki-edit-json',
			];
		}

		return $items;
	}

}
