import { expect, test, describe } from 'vitest';
import { Neo } from '@/core/Neo';

describe( 'Neo', () => {
	test( 'singleton', () => {
		const instance1 = Neo.getInstance();
		const instance2 = Neo.getInstance();

		expect( instance1 ).toBe( instance2 );
	} );
} );
