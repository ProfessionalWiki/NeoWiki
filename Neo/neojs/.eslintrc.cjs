module.exports = {
	root: true,
	plugins: [
		'@stylistic'
	],
	extends: [
		'wikimedia',
		'wikimedia/node',
		'wikimedia/language/es2022',
		'@wmde/wikimedia-typescript'
	],
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module'
	},
	rules: {
		// These @typescript-eslint rules are disabled because they are replaced by the @stylistic rules.
		'@typescript-eslint/indent': 'off',
		'@typescript-eslint/member-delimiter-style': 'off',
		'@typescript-eslint/type-annotation-spacing': 'off',
		'@typescript-eslint/semi': 'off',
		// These @stylistic rules are the same as the above disabled rules defined in @wmde/wikimedia-typescript.
		'@stylistic/indent': [ 'error', 'tab', { SwitchCase: 1 } ],
		'@stylistic/member-delimiter-style': 'error',
		'@stylistic/type-annotation-spacing': [ 'error', {
			before: false,
			after: true,
			overrides: {
				arrow: {
					before: true,
					after: true
				},
				colon: {
					before: false,
					after: true
				}
			}
		} ],
		'@stylistic/semi': [ 'error', 'always' ],
		// Overrides.
		'n/no-missing-import': 'off',
		'max-len': 'off',
		'no-use-before-define': 'off',
		'@typescript-eslint/explicit-module-boundary-types': 'off',
		'no-shadow': 'off',
		'no-bitwise': [ 'off' ],
		'jsdoc/require-param': 'off',
		'jsdoc/require-returns': 'off',
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
		]
	},
	overrides: [
		{
			files: [
				'src/domain/valueFormats/**/*.ts',
				'src/domain/PropertyDefinition.ts',
				'src/domain/StatementList.ts',
				'src/domain/Value.ts',
				'src/domain/ValueFormat.ts'
			],
			rules: {
				'@typescript-eslint/no-explicit-any': 'off'
			}
		}
	]
};
