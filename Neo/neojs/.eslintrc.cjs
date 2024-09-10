module.exports = {
	root: true,
	plugins: [
		'@stylistic'
	],
	extends: [
		'wikimedia',
		'wikimedia/node',
		'wikimedia/language/rules-es2017',
		'@wmde/wikimedia-typescript'
	],
	env: {
		node: true,
		es6: true
	},
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
		'max-len': [ 'warn', { code: 120 } ]
	}
};
