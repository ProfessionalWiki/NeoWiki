<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSetting;

/**
 * Renders the on-page reference for the on-wiki configuration page from the config schema, so it can
 * never drift from the settings actually exposed. It lists every page key, the value it accepts, and the
 * LocalSettings.php setting it overrides. The value description comes from each setting's own describe();
 * the per-setting semantics live in the LocalSettings.php documentation, reached via the setting name, so
 * they are not duplicated here.
 */
class ConfigDocumentationBuilder {

	public const string ANCHOR = 'neowiki-config-reference';

	private const string DOCUMENTATION_URL = 'https://neowiki.ai/docs/operations/installation';

	public function __construct(
		private ConfigSchema $schema,
		private MessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * A one-line pointer to the on-page reference and the external documentation. Rendered to HTML so it
	 * can be placed directly into the edit form and the view output, neither of which parses wikitext.
	 */
	public function buildPointer(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'neowiki-config-docs-pointer' ],
			$this->messageLocalizer->msg( 'neowiki-config-docs-pointer', self::ANCHOR, self::DOCUMENTATION_URL )->parse()
		);
	}

	public function buildReference(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'neowiki-config-docs' ],
			Html::element(
				'h2',
				[ 'id' => self::ANCHOR ],
				$this->messageLocalizer->msg( 'neowiki-config-docs-heading' )->text()
			) . $this->renderTable()
		);
	}

	private function renderTable(): string {
		$rows = $this->renderHeaderRow();

		foreach ( $this->schema->getSettings() as $setting ) {
			$rows .= $this->renderRow( $setting );
		}

		return Html::rawElement( 'table', [ 'class' => 'wikitable' ], $rows );
	}

	private function renderHeaderRow(): string {
		return Html::rawElement(
			'tr',
			[],
			Html::element( 'th', [], $this->messageLocalizer->msg( 'neowiki-config-docs-column-key' )->text() )
			. Html::element( 'th', [], $this->messageLocalizer->msg( 'neowiki-config-docs-column-value' )->text() )
			. Html::element( 'th', [], $this->messageLocalizer->msg( 'neowiki-config-docs-column-setting' )->text() )
		);
	}

	private function renderRow( ConfigSetting $setting ): string {
		// The key and the LocalSettings.php setting fill their whole cell, so they are plain text; only the
		// accepted value carries inline <code> spans on the literal JSON values, emitted raw below.
		return Html::rawElement(
			'tr',
			[],
			Html::element( 'td', [], $setting->pageKey )
			. Html::rawElement( 'td', [], $this->describeValue( $setting ) )
			. Html::element( 'td', [], '$wg' . $setting->settingName )
		);
	}

	/**
	 * The accepted-value description as HTML. The boolean type message wraps the literal JSON values in
	 * <code> spans, so it is emitted raw — it is a trusted static message with no parameters.
	 */
	private function describeValue( ConfigSetting $setting ): string {
		return $this->messageLocalizer->msg( ...$setting->describe() )->text();
	}

}
