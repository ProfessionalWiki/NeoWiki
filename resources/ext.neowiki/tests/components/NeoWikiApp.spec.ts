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
import type { Subject } from '@/domain/Subject.ts';
import type { StatementList } from '@/domain/StatementList.ts';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { Neo } from '@/Neo.ts';
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
		vi.spyOn( console, 'warn' ).mockImplementation( () => undefined );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/** Only the Company Schema loads. */
	function loadFrom( ...subjects: Subject[] ): void {
		vi.spyOn( NeoWikiExtension.getInstance(), 'getStoreStateLoader' ).mockReturnValue(
			new StoreStateLoader(
				new StubSubjectRepository( subjects ),
				new InMemorySchemaRepository( [ newSchema( { title: 'Company' } ) ] ),
				new InMemoryLayoutLookup( [] ),
			),
		);
	}

	function relationsTo( target: SubjectId ): StatementList {
		return Neo.getInstance().getSubjectDeserializer().deserializeStatements( {
			Products: {
				value: [ { target: target.text } ],
				propertyType: RelationType.typeName,
			},
		} );
	}

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

	function mountedSubjectIds( wrapper: VueWrapper ): string[] {
		return wrapper.findAllComponents( Infobox ).map( ( view ) => view.props( 'subjectId' ).text );
	}

	it( 'mounts the Views whose Subject loaded', async () => {
		// The repository holds only one of the two Subjects, which is how a Subject the viewer may
		// not read reaches the loader: the read answers for it as for one that does not exist.
		loadFrom( newSubject( { id: loadedId, schemaName: 'Company' } ) );
		placeViewFor( loadedId );
		placeViewFor( unloadableId );

		const wrapper = await mountApp();

		expect( mountedSubjectIds( wrapper ) ).toEqual( [ loadedId.text ] );
	} );

	// The other Subject reaches the Subject store without its Schema twice over: through its own load,
	// whose Schema read fails, and as the target of the loaded Subject's relations.
	it( 'does not mount a View whose Subject is stored without its Schema', async () => {
		loadFrom(
			newSubject( { id: loadedId, schemaName: 'Company', statements: relationsTo( unloadableId ) } ),
			newSubject( { id: unloadableId, schemaName: 'Product' } ),
		);
		placeViewFor( loadedId );
		placeViewFor( unloadableId );

		const wrapper = await mountApp();

		expect( mountedSubjectIds( wrapper ) ).toEqual( [ loadedId.text ] );
	} );

} );
