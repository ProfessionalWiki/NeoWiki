import { expect, test, describe } from 'vitest';
import { Neo } from '../src/Neo';
import { Something } from '../src/application/Something';

describe( 'Neo', () => {
	test( 'singleton', () => {
		const instance1 = Neo.getInstance();
		const instance2 = Neo.getInstance();

		expect( instance1 ).toBe( instance2 );
	} );

	describe( 'methods', () => {
		const neo = Neo.getInstance();

		test( 'add function', () => {
			expect( neo.add( 2, 3 ) ).toBe( 5 );
			expect( neo.add( -1, 1 ) ).toBe( 0 );
			expect( neo.add( 0, 0 ) ).toBe( 0 );
		} );

		test( 'multiply function', () => {
			expect( neo.multiply( 2, 3 ) ).toBe( 6 );
			expect( neo.multiply( -1, 4 ) ).toBe( -4 );
			expect( neo.multiply( 0, 5 ) ).toBe( 0 );
		} );
	} );

	test( 'getSomething method', () => {
		const neo = Neo.getInstance();
		const something = neo.getSomething();

		expect( something ).toBeInstanceOf( Something );
		expect( something.doSomething() ).toBe( 'Something is done!' );
	} );

	test( 'getAnotherThing method', () => {
		const neo = Neo.getInstance();
		const something = neo.getSomething();
		const anotherThing = something.getAnotherThing();

		expect( anotherThing.doAnotherThing() ).toBe( 'AnotherThing is done!' );
	} );
} );
