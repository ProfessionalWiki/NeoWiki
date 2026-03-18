import { defineStore } from 'pinia';
import { View } from '@/domain/View.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export const useViewStore = defineStore( 'view', {
	state: () => ( {
		views: new Map<string, View>(),
	} ),
	getters: {
		getView: ( state ) => ( viewName: string ): View | undefined => state.views.get( viewName ) as View | undefined,
	},
	actions: {
		setView( name: string, view: View ): void {
			this.views.set( name, view );
		},
		async fetchView( name: string ): Promise<void> {
			const view = await NeoWikiExtension.getInstance().getViewLookup().getView( name );
			this.setView( name, view );
		},
		async getOrFetchView( name: string ): Promise<View> {
			if ( !this.views.has( name ) ) {
				await this.fetchView( name );
			}
			return this.views.get( name ) as View;
		},
	},
} );
