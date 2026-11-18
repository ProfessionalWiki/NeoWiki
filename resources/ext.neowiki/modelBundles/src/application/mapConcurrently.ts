/**
 * How many requests run at once, so that hundreds of items do not queue hundreds of requests.
 */
const CONCURRENCY = 4;

export async function mapConcurrently<T, R>( items: readonly T[], map: ( item: T ) => Promise<R> ): Promise<R[]> {
	const results: R[] = new Array( items.length );
	let next = 0;

	async function work(): Promise<void> {
		while ( next < items.length ) {
			const index = next++;
			results[ index ] = await map( items[ index ] );
		}
	}

	await Promise.all( Array.from( { length: Math.min( CONCURRENCY, items.length ) }, work ) );
	return results;
}
