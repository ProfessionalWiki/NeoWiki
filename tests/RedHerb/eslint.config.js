'use strict';

const { defineConfig } = require( 'eslint/config' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

module.exports = defineConfig( [
	{
		extends: [ wikimedia( 'client', 'mediawiki' ) ],
		rules: {
			'max-len': 'off',
			'compat/compat': 'off'
		}
	},
	{
		files: [ '**/*.vue' ],
		rules: {
			'vue/no-v-html': 'error'
		}
	}
] );
