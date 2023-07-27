import { test, expect } from 'vitest';
import { isValidNumber } from '@/editor/domain/valueFormats/Number';

test.each( [
	[ '-.123.', false ],
	[ '-123', true ],
	[ '-123.45', true ],
	[ '-0.123', true ],
	[ '0', true ],
	[ '001', true ],
	[ '1', true ],
	[ '12 34', false ],
	[ '12a34', false ],
	[ '123', true ],
	[ '123.4', true ],
	[ '123.4.5', false ],
	[ '123.45.67', false ],
	[ '123..45', false ],
	[ '123.-45', false ],
	[ '123abc', false ],
	[ 'abc123', false ],
	[ ' abc1 ', false ],
	[ ' 123 ', true ],
	[ '.123.', false ]
] )( 'isValidNumber should return %s for number: %s', ( number: string, expected: boolean ) => {
	const result = isValidNumber( number );
	expect( result ).toBe( expected );
} );
