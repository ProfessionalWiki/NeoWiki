import { describe, expect, it } from 'vitest';
import { SubjectMap } from '../SubjectMap';
import { SubjectId } from '../SubjectId';
import { newTestSubject } from '../TestSubject';

describe( 'SubjectMap', () => {

	const subjects = new SubjectMap(
		newTestSubject( {
			id: '00000000-0000-0000-0000-000000000000',
			label: 'John Doe'
		} ),
		newTestSubject( {
			id: '00000000-0000-0000-0000-000000000001',
			label: 'Foo Bar'
		} )
	);

	it( 'should add elements in constructor', () => {
		expect( subjects.get( new SubjectId( '00000000-0000-0000-0000-000000000001' ) )?.getLabel() ).toBe( 'Foo Bar' );
		expect( subjects.get( new SubjectId( '00000000-0000-0000-0000-000000000000' ) )?.getLabel() ).toBe( 'John Doe' );
	} );

	it( 'should return undefined when getting by unknown ID', () => {
		expect( subjects.get( new SubjectId( '00000000-0000-0000-0000-000000000002' ) ) ).toBe( undefined );
	} );

	it( 'should be iterable', () => {
		const labels = [];
		for ( const subject of subjects ) {
			labels.push( subject.getLabel() );
		}
		expect( labels ).toEqual( [ 'John Doe', 'Foo Bar' ] );
	} );

} );
