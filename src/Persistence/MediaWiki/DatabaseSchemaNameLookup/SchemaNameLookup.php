<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

interface SchemaNameLookup {
	public function getFirstSchemasName(): array;

	public function getSchemasNameMatching( string $search ): array;
}
