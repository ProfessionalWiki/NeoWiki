export interface DiffLine {
	readonly change: 'kept' | 'removed' | 'added';
	readonly text: string;
}

/**
 * The lines of `before` and `after`, each marked as kept in both, removed from before, or added in after,
 * with as many kept as can be.
 */
export function lineDiff( before: string, after: string ): DiffLine[] {
	const a = before.split( '\n' );
	const b = after.split( '\n' );
	// What both start and end with is kept as it is, so that the table the rest is compared by grows with the change.
	const prefix = commonLength( a, b );
	const suffix = commonLength( a.slice( prefix ).reverse(), b.slice( prefix ).reverse() );
	const kept = ( text: string ): DiffLine => ( { change: 'kept', text } );

	return [
		...a.slice( 0, prefix ).map( kept ),
		...changedLines( a.slice( prefix, a.length - suffix ), b.slice( prefix, b.length - suffix ) ),
		...a.slice( a.length - suffix ).map( kept ),
	];
}

function commonLength( a: string[], b: string[] ): number {
	let length = 0;

	while ( length < a.length && length < b.length && a[ length ] === b[ length ] ) {
		length++;
	}

	return length;
}

function changedLines( a: string[], b: string[] ): DiffLine[] {
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
