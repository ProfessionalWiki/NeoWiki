import { test, expect } from 'vitest';
import { isValidTime } from '../Time';

test.each( [
	[ '12:34', false ],
	[ '13:45', false ],
	[ '25:00', false ],
	[ '12 34', false ],
	[ '123.4', false ],
	[ '123.4.5', false ],
	[ '0:00', false ],
	[ '1:00', false ],
	[ '2:00', false ],
	[ '3:00', false ],
	[ '00:01', false ],
	[ '23:59', false ],
	[ '24:00', false ],
	[ '12:60', false ],
	[ '12:345', false ],
	[ ' 13:45 ', false ],
	[ '12:34:56', true ],
	[ '23:59:59', true ],
	[ '00:00:00', true ]

] )( 'isValidTime should return %s for time: %s', ( time: string, expected: boolean ) => {
	const result = isValidTime( time );
	expect( result ).toBe( expected );
} );
