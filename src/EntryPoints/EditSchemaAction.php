<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Action;
use MediaWiki\Html\Html;

class EditSchemaAction extends Action {

	public function getName(): string {
		return 'edit-schema';
	}

	public function getRestriction(): string {
		return 'edit';
	}

	public function show(): void {
		$this->checkCanExecute( $this->getUser() );
		// TODO: should only execute on Schema namespace

		$this->getOutput()->setPageTitle(
			$this->msg( 'neowiki-editing-schema', $this->getTitle()->getText() )->text()
		);
		$this->getOutput()->addModules( 'ext.neowiki' );
		$this->getOutput()->addModuleStyles( 'ext.neowiki.styles' );

		$this->getOutput()->addHTML(
			Html::element(
				'div',
				[
					'id' => 'ext-neowiki-edit-schema',
					'data-mw-schema-name' => $this->getTitle()->getText(),
				]
			)
		);
	}

}
