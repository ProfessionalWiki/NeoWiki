<?php

use ProfessionalWiki\NeoWiki\NeoWikiExtension;

$namespaceNames = [];

$namespaceNames['en'] = [
	NeoWikiExtension::NS_SCHEMA => 'Schema',
	NeoWikiExtension::NS_SCHEMA + 1 => 'Schema_talk',
	NeoWikiExtension::NS_VIEW => 'View',
	NeoWikiExtension::NS_VIEW + 1 => 'View_talk',
];
