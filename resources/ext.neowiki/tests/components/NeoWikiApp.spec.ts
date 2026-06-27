import { mount, flushPromises, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { h, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import Infobox from '@/components/Views/Infobox.vue';
import { Service } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { Schema } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { Layout } from '@/domain/Layout.ts';
import { NeoWikiTestServices } from '../NeoWikiTestServices.ts';
import { createI18nMock, setupMwMock } from '../VueTestHelpers.ts';

const $i18n = createI18nMock();

const subjectId = new SubjectId( 's1demo5sssssss1' );
const subject = new Subject( subjectId, 'Test Subject', 'TestSchema', new StatementList( [] ) );
const schema = new Schema( 'TestSchema', '', new PropertyDefinitionList( [] ) );

// A registered View Type, recognisable in the DOM by its marker class.
const CustomView = {
	name: 'CustomView',
	render: () => h( 'div', { class: 'test-custom-view' }, 'custom view' ),
};

describe( 'NeoWikiApp view resolution', () => {
	let pinia: ReturnType<typeof createPinia>;
	let placeholder: HTMLElement;
	let wrapper: VueWrapper | undefined;

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message', 'msg', 'notify', 'util' ] } );

		pinia = createPinia();
		setActivePinia( pinia );
		useSubjectStore().setSubject( subject );
		useSchemaStore().setSchema( 'TestSchema', schema );
		// The View's Layout selects the View Type "custom". This is the production path:
		// a {{#view: | layout=}} placeholder carries the Layout name, and NeoWikiApp reads
		// the View Type from the Layout (the data-mw-neowiki-view-type attribute is never
		// emitted by the parser).
		useLayoutStore().setLayout( 'TestLayout', new Layout( 'TestLayout', 'TestSchema', 'custom', '', [], {} ) );

		// Don't hit the network: NeoWikiApp asks the extension singleton for its loader.
		vi.spyOn( NeoWikiExtension.getInstance(), 'getStoreStateLoader' ).mockReturnValue( {
			loadSubjectsAndSchemas: vi.fn().mockResolvedValue( undefined ),
			loadLayouts: vi.fn().mockResolvedValue( undefined ),
		} as any );

		// A View placeholder on the page, as the parser function emits it.
		placeholder = document.createElement( 'div' );
		placeholder.className = 'ext-neowiki-view';
		placeholder.dataset.mwNeowikiSubjectId = subjectId.text;
		placeholder.dataset.mwNeowikiLayoutName = 'TestLayout';
		document.body.appendChild( placeholder );
	} );

	afterEach( () => {
		wrapper?.unmount();
		wrapper = undefined;
		placeholder.remove();
		vi.restoreAllMocks();
	} );

	it( 'swaps the fallback infobox for a View Type registered after the views were resolved', async () => {
		// Registry as it is when the app boots: only the built-in infobox, "custom" not yet registered.
		const viewTypeRegistry = new ViewTypeRegistry();
		viewTypeRegistry.registerType( 'infobox', Infobox );

		wrapper = mount( NeoWikiApp, {
			attachTo: document.body,
			props: { showSubjectCreator: false, pageHasMainSubject: false },
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
				provide: {
					...NeoWikiTestServices.getServices(),
					[ Service.ViewTypeRegistry ]: viewTypeRegistry,
					[ Service.SubjectAuthorizer ]: { canEditSubject: vi.fn().mockResolvedValue( false ) },
				},
			},
		} );

		await flushPromises();

		// The app resolved the view before "custom" existed, so it fell back to the infobox.
		expect( placeholder.querySelector( '.test-custom-view' ) ).toBeNull();
		expect( placeholder.querySelector( '.ext-neowiki-infobox' ) ).not.toBeNull();

		// The extension's module loads late and registers its View Type.
		viewTypeRegistry.registerType( 'custom', CustomView );
		await nextTick();

		// The already-resolved view re-resolves and swaps to the registered component.
		expect( placeholder.querySelector( '.test-custom-view' ) ).not.toBeNull();
		expect( placeholder.querySelector( '.ext-neowiki-infobox' ) ).toBeNull();
	} );
} );
