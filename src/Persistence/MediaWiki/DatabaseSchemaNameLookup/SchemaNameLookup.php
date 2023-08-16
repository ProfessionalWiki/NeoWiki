<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

interface SchemaNameLookup {
	public function getFirstTenSchemaNamesMatching( string $search ): array;
}
