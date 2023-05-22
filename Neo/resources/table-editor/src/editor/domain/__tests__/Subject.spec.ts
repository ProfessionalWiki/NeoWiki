import { describe, expect, it } from 'vitest';
import { newTestSubject, ZERO_GUID } from '../TestSubject';

describe( 'Subject', () => {

	it( 'should be constructable via newTestSubject', () => {
		const subject = newTestSubject( {
			label: 'I am a tomato',
			schemaId: 'Tomato'
		} );

		expect( subject.getId().text ).toBe( ZERO_GUID );
		expect( subject.getLabel() ).toBe( 'I am a tomato' );
		expect( subject.getSchemaId() ).toBe( 'Tomato' );
		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
	} );

} );
