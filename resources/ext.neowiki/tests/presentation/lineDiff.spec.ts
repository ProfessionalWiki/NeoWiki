import { describe, expect, it } from 'vitest';
import { diffHunks, lineDiff } from '@/presentation/lineDiff';

function marked( before: string, after: string ): string[] {
	const marks = { kept: ' ', removed: '-', added: '+' };
	return lineDiff( before, after ).map( ( line ) => marks[ line.change ] + line.text );
}

describe( 'lineDiff', () => {

	it( 'shows a changed line as removed, then added, between the lines kept', () => {
		expect( marked( '{\n"label": "female"\n}', '{\n"label": "woman"\n}' ) )
			.toEqual( [ ' {', '-"label": "female"', '+"label": "woman"', ' }' ] );
	} );

	it( 'keeps as many lines as can be kept', () => {
		expect( marked( 'a\nb\nc\nd', 'a\nc\nd\ne' ) ).toEqual( [ ' a', '-b', ' c', ' d', '+e' ] );
	} );

} );

describe( 'diffHunks', () => {

	const LINES = Array.from( { length: 20 }, ( _value, index ) => `line ${ index + 1 }` );

	function hunksAfterChanging( ...changed: number[] ): string[][] {
		const after = LINES.map( ( line, index ) => changed.includes( index + 1 ) ? `${ line } changed` : line );
		const marks = { kept: ' ', removed: '-', added: '+' };

		return diffHunks( lineDiff( LINES.join( '\n' ), after.join( '\n' ) ), 3 )
			.map( ( hunk ) => hunk.map( ( line ) => marks[ line.change ] + line.text ) );
	}

	it( 'shows a change with three kept lines before and after it', () => {
		expect( hunksAfterChanging( 10 ) ).toEqual( [ [
			' line 7', ' line 8', ' line 9', '-line 10', '+line 10 changed', ' line 11', ' line 12', ' line 13',
		] ] );
	} );

	it( 'shows changes far apart as hunks of their own', () => {
		expect( hunksAfterChanging( 3, 15 ).map( ( hunk ) => hunk.filter( ( line ) => line.startsWith( '+' ) ) ) )
			.toEqual( [ [ '+line 3 changed' ], [ '+line 15 changed' ] ] );
	} );

	it( 'joins changes whose kept lines meet into one hunk', () => {
		expect( hunksAfterChanging( 5, 11 ) ).toHaveLength( 1 );
	} );

} );
