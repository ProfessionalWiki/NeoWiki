module.exports = {
	moduleFileExtensions: ['js', 'ts', 'json', 'vue'],
	transform: {
		'^.+\\.ts$': 'ts-jest',
		'^.+\\.vue$': '@vue/vue3-jest'
	},
	moduleNameMapper: {
		'^@/(.*)$': '<rootDir>/resources/$1',
		'^@ext.neowiki.addButton/(.*)$': '<rootDir>/resources/ext.neowiki.addButton/$1'
	},
	testEnvironment: 'jsdom',
	testEnvironmentOptions: {
		customExportConditions: ["node", "node-addons"],
	},
	setupFilesAfterEnv: ['<rootDir>/jest.setup.ts']
}
