import { describe, expect, it } from 'vitest';
import { SubjectMap } from '@/domain/SubjectMap';
import { SubjectId } from '@/domain/SubjectId';
import { newSubject } from '@/TestHelpers';

describe( 'SubjectMap', () => {

	const subjects = new SubjectMap(
		newSubject( {
			id: 's11111111111111',
			label: 'John Doe'
		} ),
		newSubject( {
			id: 's11111111111112',
			label: 'Foo Bar'
		} )
	);

	it( 'should add elements in constructor', () => {
		expect( subjects.get( new SubjectId( 's11111111111112' ) )?.getLabel() ).toBe( 'Foo Bar' );
		expect( subjects.get( new SubjectId( 's11111111111111' ) )?.getLabel() ).toBe( 'John Doe' );
	} );

	it( 'should return undefined when getting by unknown ID', () => {
		expect( subjects.get( new SubjectId( 's11111111111113' ) ) ).toBe( undefined );
	} );

	it( 'should be iterable', () => {
		const labels = [];
		for ( const subject of subjects ) {
			labels.push( subject.getLabel() );
		}
		expect( labels ).toEqual( [ 'John Doe', 'Foo Bar' ] );
	} );

} );
