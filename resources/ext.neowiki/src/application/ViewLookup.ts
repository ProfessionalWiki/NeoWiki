import type { View, ViewName } from '@/domain/View';

export interface ViewLookup {

	getView( viewName: ViewName ): Promise<View>;

}

export class InMemoryViewLookup implements ViewLookup {

	private readonly views: Map<ViewName, View> = new Map<ViewName, View>();

	public constructor( views: View[] ) {
		for ( const view of views ) {
			this.views.set( view.getName(), view );
		}
	}

	public async getView( viewName: ViewName ): Promise<View> {
		if ( !this.views.has( viewName ) ) {
			throw new Error( `View ${ viewName } not found` );
		}
		return this.views.get( viewName ) as View;
	}

}
