module.exports = {
	root: true,
	plugins: [
		'@stylistic'
	],
	extends: [
		'wikimedia',
		'wikimedia/node',
		'wikimedia/language/es2022',
		'plugin:vue/strongly-recommended',
		'@wmde/wikimedia-typescript',
		'wikimedia/vue/es6'
	],
	parser: 'vue-eslint-parser',
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module'
	},
	globals: {
		mw: 'readonly'
	},
	rules: {
		// These @typescript-eslint rules are disabled because they are replaced by the @stylistic rules.
		'@typescript-eslint/indent': 'off',
		'@typescript-eslint/member-delimiter-style': 'off',
		'@typescript-eslint/type-annotation-spacing': 'off',
		'@typescript-eslint/semi': 'off',
		'es-x/no-rest-spread-properties': 'off',
		'@typescript-eslint/explicit-module-boundary-types': 'off',
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
		'jsdoc/require-returns': 'off',
		'no-new': 'off',
		'es-x/no-optional-catch-binding': 'off'
	},
	overrides: [
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
		}
	]
};
