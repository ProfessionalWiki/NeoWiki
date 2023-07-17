import { test, expect } from 'vitest';
import { isValidPhoneNumber } from '../PhoneNumber';

test.each( [
	[ '123.4', false ],
	[ '123.4.5', false ],
	[ '12 34', false ],
	[ '12a34', false ],
	[ '1', false ],
	[ '11', false ],
	[ '112', true ],
	[ ' +123 (456) 789-0123', true ],
	[ '+123 (456) 789-0123 ', true ],
	[ '01234567890123', true ],
	[ ' invalid_phone_number ', false ],
	[ '13:45 ', false ],
	[ ' 25:00 ', false ],
	[ ' 123 ', true ],
	[ ' abc1 ', false ]

] )( 'isValidPhoneNumber should return %s for phone number: %s', ( phoneNumber: string, expected: boolean ) => {
	const result = isValidPhoneNumber( phoneNumber );
	expect( result ).toBe( expected );
} );
