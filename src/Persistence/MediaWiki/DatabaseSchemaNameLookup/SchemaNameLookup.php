<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

interface SchemaNameLookup {
	public function getFirstTenSchemaNames(): array;
}
