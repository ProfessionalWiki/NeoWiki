<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\View;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

readonly class View {

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct(
		private ViewName $name,
		private SchemaName $schema,
		private string $type,
		private string $description,
		private DisplayRules $displayRules,
		private array $settings,
	) {
	}

	public function getName(): ViewName {
		return $this->name;
	}

	public function getSchema(): SchemaName {
		return $this->schema;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getDisplayRules(): DisplayRules {
		return $this->displayRules;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getSettings(): array {
		return $this->settings;
	}

}
