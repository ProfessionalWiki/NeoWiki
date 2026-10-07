import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SchemaExportButton from '@/components/SchemasPage/SchemaExportButton.vue';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { newSchema } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

interface CapturedDownload {
	fileName: string;
	/** The downloaded file as read a task after the click, or undefined if it was released by then. */
	file(): Promise<Blob | undefined>;
}

/**
 * Records the downloads started by clicking a link made with `document.createElement`, since jsdom
 * performs none and makes no object URLs for its Blobs. A file is read a task after the click, as a
 * browser that reads it late does, so one released before then reads as undefined.
 * Undone by `vi.restoreAllMocks()`.
 */
function captureDownloads(): CapturedDownload[] {
	const files = new Map<string, Blob>();
	const downloads: CapturedDownload[] = [];
	const createElement = document.createElement.bind( document );
	let urlCount = 0;

	vi.spyOn( URL, 'createObjectURL' ).mockImplementation( ( file ) => {
		const url = `blob:download-${ ++urlCount }`;
		files.set( url, file as Blob );
		return url;
	} );
	vi.spyOn( URL, 'revokeObjectURL' ).mockImplementation( ( url ) => {
		files.delete( url );
	} );
	vi.spyOn( document, 'createElement' ).mockImplementation(
		( ( tagName: string, options?: ElementCreationOptions ): HTMLElement => {
			const element = createElement( tagName, options );

			if ( element instanceof HTMLAnchorElement ) {
				element.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					const url = element.getAttribute( 'href' ) ?? '';
					downloads.push( {
						fileName: element.download,
						file: async () => {
							await new Promise( ( resolve ) => {
								setTimeout( resolve );
							} );
							return files.get( url );
						},
					} );
				} );
			}

			return element;
		} ) as typeof document.createElement,
	);

	return downloads;
}

let pinia: ReturnType<typeof createPinia>;
let downloads: CapturedDownload[];

function summary( name: string ): SchemaSummary {
	return { name, description: '', propertyCount: 0 };
}

function listSchemas( ...names: string[] ): void {
	useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( names.map( summary ) );
}

function mountButton( repository: InMemorySchemaRepository ): VueWrapper {
	return mount( SchemaExportButton, {
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SchemaRepository ]: repository },
			stubs: { CdxIcon: true },
		},
	} );
}

function exportButton( wrapper: VueWrapper ): HTMLButtonElement {
	return wrapper.find( 'button' ).element as HTMLButtonElement;
}

async function clickExport( wrapper: VueWrapper ): Promise<void> {
	await wrapper.find( 'button' ).trigger( 'click' );
	await flushPromises();
}

describe( 'SchemaExportButton', () => {
	beforeEach( () => {
		downloads = captureDownloads();
		setupMwMock( {
			config: { wgServerName: 'wiki.example.org' },
			functions: [ 'config', 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'downloads every Schema the wiki lists, in a JSON file named after the wiki', async () => {
		listSchemas( 'Person', 'Museum' );
		const wrapper = mountButton( new InMemorySchemaRepository( [
			newSchema( { title: 'Museum' } ),
			// Held but not listed, as a Schema the user may not read is.
			newSchema( { title: 'Secret' } ),
			newSchema( { title: 'Person' } ),
		] ) );

		await clickExport( wrapper );

		expect( downloads ).toHaveLength( 1 );
		expect( downloads[ 0 ].fileName ).toBe( 'wiki.example.org-schemas.json' );
		const file = await downloads[ 0 ].file();
		expect( file?.type ).toBe( 'application/json' );
		expect( Object.keys( JSON.parse( await file!.text() ).schemas ) ).toEqual( [ 'Person', 'Museum' ] );
	} );

	it( 'reports a Schema that fails to load, downloads nothing, and can be used again', async () => {
		listSchemas( 'Person', 'Museum' );
		const wrapper = mountButton( new InMemorySchemaRepository( [ newSchema( { title: 'Person' } ) ] ) );

		await clickExport( wrapper );

		expect( downloads ).toHaveLength( 0 );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-schemas-export-error-unreadable-schemaMuseum',
			expect.objectContaining( { title: 'neowiki-schemas-export-error', type: 'error' } ),
		);
		expect( exportButton( wrapper ).disabled ).toBe( false );
	} );

	it( 'reports a listing of the Schemas that fails, and can be used again', async () => {
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockRejectedValue( new Error( 'Error fetching schema summaries' ) );
		const wrapper = mountButton( new InMemorySchemaRepository( [] ) );

		await clickExport( wrapper );

		expect( mw.notify ).toHaveBeenCalledWith(
			'Error fetching schema summaries',
			expect.objectContaining( { type: 'error' } ),
		);
		expect( exportButton( wrapper ).disabled ).toBe( false );
	} );

	it( 'is disabled while the export runs', async () => {
		let finishListing!: ( summaries: SchemaSummary[] ) => void;
		useSchemaStore().fetchAllSchemaSummaries = () => new Promise<SchemaSummary[]>( ( resolve ) => {
			finishListing = resolve;
		} );
		const wrapper = mountButton( new InMemorySchemaRepository( [] ) );

		await wrapper.find( 'button' ).trigger( 'click' );

		expect( exportButton( wrapper ).disabled ).toBe( true );

		finishListing( [] );
		await flushPromises();

		expect( exportButton( wrapper ).disabled ).toBe( false );
	} );
} );
