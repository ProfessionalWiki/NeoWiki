export interface DiffLine {
	readonly change: 'kept' | 'removed' | 'added';
	readonly text: string;
}

/**
 * The lines of `before` and `after`, each marked as kept in both, removed from before, or added in after,
 * with as many kept as can be. Meant for the few hundred lines of one item.
 */
export function lineDiff( before: string, after: string ): DiffLine[] {
	const a = before.split( '\n' );
	const b = after.split( '\n' );
	const longest = longestCommonSuffixLengths( a, b );
	const lines: DiffLine[] = [];
	let i = 0;
	let j = 0;

	while ( i < a.length || j < b.length ) {
		if ( i < a.length && j < b.length && a[ i ] === b[ j ] ) {
			lines.push( { change: 'kept', text: a[ i++ ] } );
			j++;
		} else if ( i < a.length && ( j === b.length || longest[ i + 1 ][ j ] >= longest[ i ][ j + 1 ] ) ) {
			lines.push( { change: 'removed', text: a[ i++ ] } );
		} else {
			lines.push( { change: 'added', text: b[ j++ ] } );
		}
	}

	return lines;
}

function longestCommonSuffixLengths( a: string[], b: string[] ): number[][] {
	const lengths = Array.from( { length: a.length + 1 }, () => new Array<number>( b.length + 1 ).fill( 0 ) );

	for ( let i = a.length - 1; i >= 0; i-- ) {
		for ( let j = b.length - 1; j >= 0; j-- ) {
			lengths[ i ][ j ] = a[ i ] === b[ j ] ? lengths[ i + 1 ][ j + 1 ] + 1 : Math.max( lengths[ i + 1 ][ j ], lengths[ i ][ j + 1 ] );
		}
	}

	return lengths;
}

/**
 * The changed lines in runs, each with up to `context` kept lines before and after it. Runs whose kept lines
 * meet are one hunk.
 */
export function diffHunks( lines: readonly DiffLine[], context: number ): DiffLine[][] {
	const changed = lines.map( ( line ) => line.change !== 'kept' );
	const shown = lines.map( ( _line, index ) => changed.slice( Math.max( 0, index - context ), index + context + 1 ).includes( true ) );
	const hunks: DiffLine[][] = [];

	lines.forEach( ( line, index ) => {
		if ( !shown[ index ] ) {
			return;
		}

		if ( index === 0 || !shown[ index - 1 ] ) {
			hunks.push( [] );
		}

		hunks[ hunks.length - 1 ].push( line );
	} );

	return hunks;
}
