<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

interface SchemaNameLookup {
	public function getFirstSchemaNames(): array;

	public function getSchemaNamesMatching( string $search ): array;
}
