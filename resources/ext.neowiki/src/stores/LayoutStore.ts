import { defineStore } from 'pinia';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export const useLayoutStore = defineStore( 'layout', {
	state: () => ( {
		layouts: new Map<string, Layout>(),
	} ),
	getters: {
		getLayout: ( state ) => ( layoutName: string ): Layout | undefined => state.layouts.get( layoutName ) as Layout | undefined,
	},
	actions: {
		setLayout( name: string, layout: Layout ): void {
			this.layouts.set( name, layout );
		},
		async fetchLayout( name: string ): Promise<void> {
			const layout = await NeoWikiExtension.getInstance().getLayoutLookup().getLayout( name );
			this.setLayout( name, layout );
		},
		async getOrFetchLayout( name: string ): Promise<Layout> {
			if ( !this.layouts.has( name ) ) {
				await this.fetchLayout( name );
			}
			return this.layouts.get( name ) as Layout;
		},
	},
} );
