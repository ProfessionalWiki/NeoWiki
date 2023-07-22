import { describe, expect, it } from 'vitest';
import { TextFormat } from '../Text';

describe( 'getFormatName', () => {

	it( 'returns "text"', () => {
		const format = new TextFormat();
		expect( format.getFormatName() ).toBe( 'text' );
	} );

} );
