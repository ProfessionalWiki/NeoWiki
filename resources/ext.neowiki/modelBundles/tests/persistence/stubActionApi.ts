import type { ActionApi } from '../../src/persistence/ActionApi';

/**
 * Answers every request with an empty object, apart from those given.
 */
export function stubActionApi( overrides: Partial<ActionApi> = {} ): ActionApi {
	return {
		get: async () => ( {} ),
		post: async () => ( {} ),
		postWithEditToken: async () => ( {} ),
		...overrides,
	};
}
