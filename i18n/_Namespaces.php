<?php

use ProfessionalWiki\NeoWiki\NeoWikiExtension;

$namespaceNames = [];

$namespaceNames['en'] = [
	NeoWikiExtension::NS_SCHEMA => 'Schema',
	NeoWikiExtension::NS_SCHEMA + 1 => 'Schema_talk',
	NeoWikiExtension::NS_LAYOUT => 'Layout',
	NeoWikiExtension::NS_LAYOUT + 1 => 'Layout_talk',
	NeoWikiExtension::NS_MAPPING => 'Mapping',
	NeoWikiExtension::NS_MAPPING + 1 => 'Mapping_talk',
	NeoWikiExtension::NS_SUBJECT => 'Subject',
	NeoWikiExtension::NS_SUBJECT + 1 => 'Subject_talk',
];
