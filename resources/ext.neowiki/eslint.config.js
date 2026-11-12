import { defineConfig, globalIgnores } from 'eslint/config';
import tsParser from '@typescript-eslint/parser';
import vue from 'eslint-plugin-vue';
import wikimedia from 'eslint-config-wikimedia/flat.js';

export default defineConfig( [
	globalIgnores( [
		'coverage/',
		'dist/'
	] ),

	{
		files: [ '**/*.ts', '**/*.vue' ],
		extends: [
			wikimedia( 'common', 'node', 'language/es2022' ),
			vue.configs[ 'flat/vue2-strongly-recommended' ]
		]
	},
	// The .vue scripts are TypeScript, but the typescript config only applies to .ts files.
	// It must come before vue/es6, or its parser replaces vue-eslint-parser and .vue files fail to parse.
	{
		files: [ '**/*.vue' ],
		extends: [ wikimedia( 'typescript' ) ],
		languageOptions: {
			parserOptions: {
				parser: tsParser
			}
		}
	},
	{
		files: [ '**/*.ts', '**/*.vue' ],
		extends: [ wikimedia( 'vue/es6' ) ],
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: {
				mw: 'readonly',
				$: 'readonly'
			}
		},
		rules: {
			'es-x/no-rest-spread-properties': 'off',
			'n/no-missing-import': 'off',
			'max-len': 'off',
			camelcase: 'off',
			'vue/no-v-model-argument': 'off',
			'es-x/no-optional-chaining': 'off',
			'no-unused-vars': 'off',
			'@typescript-eslint/no-unused-vars': [
				'error',
				{
					args: 'all',
					argsIgnorePattern: '^_',
					caughtErrors: 'all',
					caughtErrorsIgnorePattern: '^_',
					destructuredArrayIgnorePattern: '^_',
					varsIgnorePattern: '^_',
					ignoreRestSiblings: true
				}
			],
			'es-x/no-array-prototype-includes': 'off',
			'no-use-before-define': 'off',
			'n/no-unsupported-features/node-builtins': 'off', // To avoid: "XYZ is not supported until Node.js x.y.z."
			'es-x/no-async-functions': 'off',
			'@typescript-eslint/no-empty-object-type': 'off',
			'no-shadow': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-returns': 'off',
			'no-new': 'off',
			'implicit-arrow-linebreak': 'off',
			'es-x/no-nullish-coalescing-operators': 'off',
			'es-x/no-optional-catch-binding': 'off',
			'space-before-function-paren': 'off',
			'prefer-arrow-callback': 'off',
			'spaced-comment': 'off',
			'no-tabs': 'off',
			'@typescript-eslint/explicit-module-boundary-types': 'off',
			'es-x/no-iterator': 'warn',
			// Messages can be overridden on-wiki, so v-html is an injection point, not a style preference.
			'vue/no-v-html': 'error'
		}
	},
	{
		files: [
			'src/codex/**/*.ts',
			'src/codex/**/*.vue'
		],
		rules: {
			'no-restricted-imports': [ 'error', {
				patterns: [ {
					group: [ '@/*', '!@/codex', '../*' ],
					message: 'src/codex/ holds Codex workarounds, each to go on its own once Codex changes, so it imports no NeoWiki code.'
				} ]
			} ]
		}
	},
	{
		files: [
			'src/infrastructure/**/*.ts',
			'src/persistence/*.ts'
		],
		rules: {
			'@typescript-eslint/no-explicit-any': 'off'
		}
	},
	{
		files: [
			'tests/**/*.ts'
		],
		rules: {
			'@typescript-eslint/no-explicit-any': 'off'
		}
	},
	{
		files: [
			'src/persistence/**/*.ts',
			'src/domain/propertyTypes/**/*.ts',
			'src/domain/PropertyDefinition.ts',
			'src/domain/StatementList.ts',
			'src/domain/Value.ts',
			'src/domain/PropertyType.ts'
		],
		rules: {
			'@typescript-eslint/no-explicit-any': 'off'
		}
	}
] );
