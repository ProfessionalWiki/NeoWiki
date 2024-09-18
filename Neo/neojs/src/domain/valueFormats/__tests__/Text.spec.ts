import { describe, expect, it } from 'vitest';
import { TextFormat } from '../Text';

describe( 'TextFormat', () => {

	const format = new TextFormat();

	describe( 'getFormatName', () => {

		it( 'returns "text"', () => {
			expect( format.getFormatName() ).toBe( 'text' );
		} );

	} );
} );
