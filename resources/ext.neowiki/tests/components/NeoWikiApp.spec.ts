import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import Infobox from '@/components/Views/Infobox.vue';
import { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';
import { StubSubjectRepository } from '@/domain/SubjectRepository.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';

const loadedId = new SubjectId( 's11111111111111' );
const unloadableId = new SubjectId( 's22222222222222' );

describe( 'NeoWikiApp', () => {

	let pinia: ReturnType<typeof createPinia>;

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message', 'msg', 'util' ] } );
		document.body.innerHTML = '';
		pinia = createPinia();
		setActivePinia( pinia );

		// The repository holds only one of the two Subjects, which is how a Subject the viewer may
		// not read arrives: the read answers for it as it does for one that does not exist.
		vi.spyOn( NeoWikiExtension.getInstance(), 'getStoreStateLoader' ).mockReturnValue(
			new StoreStateLoader(
				new StubSubjectRepository( [ newSubject( { id: loadedId, schemaName: 'Company' } ) ] ),
				new InMemorySchemaRepository( [ newSchema( { title: 'Company' } ) ] ),
				new InMemoryLayoutLookup( [] ),
			),
		);
		vi.spyOn( console, 'warn' ).mockImplementation( () => undefined );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	function placeViewFor( subjectId: SubjectId ): void {
		const placeholder = document.createElement( 'div' );
		placeholder.className = 'ext-neowiki-view';
		placeholder.dataset.mwNeowikiSubjectId = subjectId.text;
		document.body.append( placeholder );
	}

	async function mountApp(): Promise<VueWrapper> {
		const wrapper = mount( NeoWikiApp, {
			props: { showSubjectCreator: false, pageHasMainSubject: false },
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				directives: { tooltip: {} },
				provide: {
					...NeoWikiTestServices.getServices(),
					[ Service.SubjectPermissionHints ]: { canEditSubject: () => Promise.resolve( false ) },
				},
			},
		} );
		await flushPromises();

		return wrapper;
	}

	// A View Type reads its Subject from the store, so a View is mounted only once the Subject is
	// there: the placeholder of one that did not load stays empty, and the others render as usual.
	it( 'mounts the Views whose Subject loaded', async () => {
		placeViewFor( loadedId );
		placeViewFor( unloadableId );

		const wrapper = await mountApp();

		expect(
			wrapper.findAllComponents( Infobox ).map( ( view ) => view.props( 'subjectId' ).text ),
		).toEqual( [ loadedId.text ] );
	} );

} );
