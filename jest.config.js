/**
 * For a detailed explanation regarding each configuration property, visit:
 * https://jestjs.io/docs/configuration
 */

const config = {
	clearMocks: true,
	collectCoverage: true,
	collectCoverageFrom: [
		'resources/**/*.(js|vue)'
	],
	coverageDirectory: 'coverage',

	// An array of regexp pattern strings used to skip coverage collection
	// coveragePathIgnorePatterns: [
	//   "/node_modules/"
	// ],
	globals: {
		'vue-jest': {
			babelConfig: false,
			hideStyleWarn: true,
			experimentalCssCompile: true
		}
	},

	// An array of file extensions your modules use
	moduleFileExtensions: [
		'js',
		'json',
		'vue'
	],

	// A map from regular expressions to module names or to arrays of module
	// names that allow to stub out resources with a single module
	moduleNameMapper: {
		'icons.json': '@wikimedia/codex-icons',
		'codex.js': '@wikimedia/codex'
	},

	// The paths to modules that run some code to configure or set up the
	// testing environment before each test
	setupFiles: [
		'./jest.setup.js'
	],

	// The test environment that will be used for testing
	testEnvironment: 'jsdom',

	// Options that will be passed to the testEnvironment
	testEnvironmentOptions: {
		customExportConditions: [ 'node', 'node-addons' ]
	},

	// Adds a location field to test results
	// testLocationInResults: false,

	// The glob patterns Jest uses to detect test files
	// testMatch: [
	//   "**/__tests__/**/*.[jt]s?(x)",
	//   "**/?(*.)+(spec|test).[tj]s?(x)"
	// ],

	// An array of regexp pattern strings that are matched against all test
	// paths, matched tests are skipped
	// testPathIgnorePatterns: [
	//   "/node_modules/"
	// ],

	// The regexp pattern or array of patterns that Jest uses to detect test files
	// testRegex: [],

	// A map from regular expressions to paths to transformers
	transform: {
		'.+\\.vue$': '@vue/vue3-jest'
	}

	// An array of regexp pattern strings that are matched against all source
	// file paths, matched files will skip transformation
	// transformIgnorePatterns: [
	//   "/node_modules/",
	//   "\\.pnp\\.[^\\/]+$"
	// ],

	// An array of regexp pattern strings that are matched against all modules
	// before the module loader will automatically return a mock for them
	// unmockedModulePathPatterns: undefined,

	// Indicates whether each individual test should be reported during the run
	// verbose: undefined,

	// An array of regexp patterns that are matched against all source file
	// paths before re-running tests in watch mode
	// watchPathIgnorePatterns: [],

	// Whether to use watchman for file crawling
	// watchman: true,
};

module.exports = config;
