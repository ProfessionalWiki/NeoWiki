<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * The valid example preloaded into the on-wiki configuration page when it is first created, so an
 * administrator starts from a working configuration set to the defaults rather than a blank page. A test
 * pins that it passes {@see ConfigValidator} and covers every {@see ConfigSchema} setting, so a schema
 * change can never leave the preload invalid or incomplete.
 */
class ConfigExample {

	public const string JSON = <<<'JSON'
		{
			"dereferenceSubjectsToDataTab": false,
			"autoRenderMainSubject": true
		}
		JSON;

}
