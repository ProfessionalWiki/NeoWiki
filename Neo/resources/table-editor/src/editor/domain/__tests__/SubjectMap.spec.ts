import { describe, expect, it } from 'vitest';
import { SubjectMap } from '../SubjectMap';
import { SubjectId } from '../SubjectId';
import { newTestSubject } from '../TestSubject';

describe( 'SubjectMap', () => {

	const subjects = new SubjectMap(
		newTestSubject(
			'00000000-0000-0000-0000-000000000000',
			'John Doe'
		),
		newTestSubject(
			'00000000-0000-0000-0000-000000000001',
			'Foo Bar'
		)
	);

	it( 'should add elements in constructor', () => {
		expect( subjects.get( new SubjectId( '00000000-0000-0000-0000-000000000000' ) )?.getLabel() ).toBe( 'John Doe' );
		expect( subjects.get( new SubjectId( '00000000-0000-0000-0000-000000000001' ) )?.getLabel() ).toBe( 'Foo Bar' );
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
