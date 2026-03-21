import type { Layout, LayoutName } from '@/domain/Layout';

export interface LayoutLookup {

	getLayout( layoutName: LayoutName ): Promise<Layout>;

}

export class InMemoryLayoutLookup implements LayoutLookup {

	private readonly layouts: Map<LayoutName, Layout> = new Map<LayoutName, Layout>();

	public constructor( layouts: Layout[] ) {
		for ( const layout of layouts ) {
			this.layouts.set( layout.getName(), layout );
		}
	}

	public async getLayout( layoutName: LayoutName ): Promise<Layout> {
		if ( !this.layouts.has( layoutName ) ) {
			throw new Error( `Layout ${ layoutName } not found` );
		}
		return this.layouts.get( layoutName ) as Layout;
	}

}
