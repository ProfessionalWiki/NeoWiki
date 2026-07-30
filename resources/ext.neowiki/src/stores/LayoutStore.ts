import { defineStore } from 'pinia';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export const useLayoutStore = defineStore( 'layout', {
	state: () => ( {
		layouts: new Map<string, Layout>(),
		mutationEpoch: 0, // See SchemaStore.mutationEpoch — same guard contract.
	} ),
	getters: {
		getLayout: ( state ) => ( layoutName: string ): Layout | undefined => state.layouts.get( layoutName ) as Layout | undefined,
	},
	actions: {
		setLayout( name: string, layout: Layout ): void {
			this.layouts.set( name, layout );
		},
		// A resolved call may have recorded nothing: the epoch guard below discards the write-back
		// when a mutation landed mid-flight, leaving the registry unchanged. Callers must read the
		// result via getLayout afterwards and handle a miss (getLayout can return undefined),
		// rather than assuming this call alone guarantees fresh data is present.
		async fetchLayout( name: string ): Promise<void> {
			const epoch = this.mutationEpoch;
			const layout = await NeoWikiExtension.getInstance().getLayoutRepository().getLayout( name );
			if ( epoch !== this.mutationEpoch ) {
				return;
			}
			this.setLayout( name, layout );
		},
		async saveLayout( layout: Layout, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getLayoutRepository().saveLayout( layout, comment );
			this.mutationEpoch++;
			this.setLayout( layout.getName(), layout );
		},
		removeLayout( name: string ): void {
			this.mutationEpoch++;
			this.layouts.delete( name );
		},
	},
} );
