import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, beforeEach } from 'vitest';
import { ref, type Ref } from 'vue';
import { createPinia, setActivePinia, type Pinia } from 'pinia';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { RelationTargetCreationKey, RelationTargetUrlKey } from '@/components/Value/ValueDisplayContract.ts';
import { newRelation, newStringValue, RelationValue, type Value } from '@/domain/Value.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import type { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { useSubjectStore, type SubjectCreatorTarget } from '@/stores/SubjectStore.ts';
import type { Subject } from '@/domain/Subject.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { newSubject } from '@/TestHelpers.ts';
import { clickIsFollowed, setupMwMock } from '../../VueTestHelpers.ts';

const FOUND_ID = 's1111111111111A';
const MISSING_ID = 's1111111111111B';

function storeSubject( id: string, label: string | null, pageName: string, displayName?: string ): void {
	useSubjectStore().setSubject( newSubject( {
		id,
		label,
		displayName: displayName ?? label ?? '',
		pageIdentifiers: new PageIdentifiers( 42, pageName ),
	} ) );
}

function readAsNotFound( ...ids: string[] ): void {
	useSubjectStore().setSubjectsWithTargets( [], [], ids.map( ( id ) => new SubjectId( id ) ) );
}

function relationsTo( ...ids: string[] ): RelationValue {
	return new RelationValue( ids.map( ( id ) => newRelation( 'not-important', id ) ) );
}

describe( 'RelationDisplay.vue', () => {
	let pinia: Pinia;

	function mountDisplay(
		value: Value,
		provide: Record<symbol, unknown> = {},
		property: RelationProperty = newRelationProperty(),
	): VueWrapper {
		return mount( RelationDisplay, {
			props: {
				value: value,
				property: property,
			},
			global: {
				plugins: [ pinia ],
				provide: provide,
			},
		} );
	}

	function useWikiMode( subjectFirst: boolean ): void {
		setupMwMock( {
			functions: [ 'config', 'message', 'msg', 'util' ],
			config: { wgNeoWikiSubjectFirst: subjectFirst },
		} );
	}

	beforeEach( () => {
		useWikiMode( false );
		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'links a target to the page it is stored on on a page-first wiki', () => {
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		const link = wrapper.find( 'a' );
		expect( link.text() ).toBe( 'Test Subject 1' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Page_Name_1' );
	} );

	// Where a relation leads without a host to say otherwise follows the wiki's mode (ADR 33): the
	// page the target is stored on where the page is the entity, the target itself where it is not.
	it( 'links a relation to the target Subject itself on a subject-first wiki', () => {
		useWikiMode( true );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).attributes( 'href' ) ).toBe( `/wiki/Special:Subject/${ FOUND_ID }` );
	} );

	// A host that says where relations lead outranks the mode: it is showing Subjects, not pages.
	it( 'links a relation where the host says, rather than to the target\'s page', () => {
		useWikiMode( true );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay(
			relationsTo( FOUND_ID ),
			{ [ RelationTargetUrlKey as symbol ]: ( target: Subject ) => '/elsewhere/' + target.getId().text },
		);

		expect( wrapper.find( 'a' ).attributes( 'href' ) ).toBe( `/elsewhere/${ FOUND_ID }` );
	} );

	it( 'renders the display name of a target that has no label, rather than its id', () => {
		storeSubject( FOUND_ID, null, 'Page_Name_1', 'Page Name 1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( 'Page Name 1' );
	} );

	// A relation link is article content a reader sees, so a target nobody named must say so there
	// too, not only in the editing UI.
	it( 'marks a target whose name the server generated', () => {
		useSubjectStore().setSubject( newSubject( {
			id: FOUND_ID,
			label: null,
			displayNameIsGenerated: true,
			schemaName: 'Attendance',
			pageIdentifiers: new PageIdentifiers( 42, 'Rijksmuseum' ),
		} ) );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( `(${ FOUND_ID })` );
	} );

	it( 'shows a target that was not found as a red link to its Special:Subject page', () => {
		readAsNotFound( MISSING_ID );

		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		const link = wrapper.find( 'a.new' );
		expect( link.text() ).toBe( MISSING_ID );
		expect( link.attributes( 'href' ) ).toBe( `/wiki/Special:Subject/${ MISSING_ID }` );
	} );

	it( 'says on a red link that its target was not found', () => {
		readAsNotFound( MISSING_ID );

		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		expect( wrapper.find( 'a.new' ).attributes( 'title' ) )
			.toBe( `neowiki-relation-target-not-found-title${ MISSING_ID }` );
	} );

	// Only a read that found nothing makes a red link. A target that was never read, or whose read
	// failed, may well exist.
	it( 'shows a target not read as its id, marked as an error', () => {
		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
		expect( wrapper.find( 'span.error' ).text() ).toBe( MISSING_ID );
	} );

	// A row whose targets are fetched only when the reader opens it renders before they are in the
	// registry, so resolving once would leave those targets showing their bare ids forever.
	it( 'resolves a target that reaches the registry after the first render', async () => {
		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );
		expect( wrapper.find( 'span' ).classes() ).toContain( 'error' );

		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'a' ).text() ).toBe( 'Test Subject 1' );
	} );

	// Someone may create the target between the read that missed it and one that finds it.
	it( 'links a target that a later read found', () => {
		readAsNotFound( FOUND_ID );
		storeSubject( FOUND_ID, 'Test Subject 1', 'Page_Name_1' );

		const wrapper = mountDisplay( relationsTo( FOUND_ID ) );

		expect( wrapper.find( 'a' ).text() ).toBe( 'Test Subject 1' );
	} );

	it( 'turns a target into a red link once a read finds nothing for it', async () => {
		const wrapper = mountDisplay( relationsTo( MISSING_ID ) );

		readAsNotFound( MISSING_ID );
		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'a.new' ).text() ).toBe( MISSING_ID );
	} );

	describe( 'a red link whose target can be created', () => {

		const birthPlace = newRelationProperty( {
			name: 'Birth place',
			targetSchemas: [ 'City', { source: 'geo', name: 'Region' }, 'Place' ],
		} );

		function mountRedLinkTo(
			target: string,
			creationAllowed: Ref<boolean> = ref( true ),
			property: RelationProperty = birthPlace,
		): VueWrapper {
			readAsNotFound( target );

			return mountDisplay( relationsTo( target ), { [ RelationTargetCreationKey as symbol ]: creationAllowed }, property );
		}

		function clickRedLink( wrapper: VueWrapper, init: MouseEventInit = {} ): boolean {
			return clickIsFollowed( wrapper.find( 'a.new' ).element, init );
		}

		function openCreatorTarget(): SubjectCreatorTarget | null {
			const store = useSubjectStore();

			return store.subjectCreatorOpen ? store.subjectCreatorTarget : null;
		}

		it( 'opens the creator on the target, with the property\'s target Schemas of this wiki', () => {
			const wrapper = mountRedLinkTo( MISSING_ID );

			const followed = clickRedLink( wrapper );

			expect( followed ).toBe( false );
			expect( openCreatorTarget() ).toEqual( { subjectId: new SubjectId( MISSING_ID ), schemaNames: [ 'City', 'Place' ] } );
		} );

		it( 'is followed where this user may not create the target', () => {
			const wrapper = mountRedLinkTo( MISSING_ID, ref( false ) );

			expect( clickRedLink( wrapper ) ).toBe( true );
		} );

		// The wiki creates Subjects of its own Source only.
		it( 'is followed for a target of another Source', () => {
			const wrapper = mountRedLinkTo( 'geo:Q1492' );

			expect( clickRedLink( wrapper ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		it( 'is followed where the property targets no Schema of this wiki', () => {
			const wrapper = mountRedLinkTo(
				MISSING_ID,
				ref( true ),
				newRelationProperty( { targetSchemas: [ { source: 'geo', name: 'Region' } ] } ),
			);

			expect( clickRedLink( wrapper ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		// Opened in a new tab or window, the link leads to the target's Special:Subject page instead.
		it.each( [ 'ctrlKey', 'metaKey', 'shiftKey', 'altKey' ] )( 'is followed when clicked with %s', ( modifier ) => {
			const wrapper = mountRedLinkTo( MISSING_ID );

			expect( clickRedLink( wrapper, { [ modifier ]: true } ) ).toBe( true );
			expect( openCreatorTarget() ).toBeNull();
		} );

		// A host learns whether this user may create Subjects after the page has rendered.
		it( 'opens the creator once the host has granted it', () => {
			const granted = ref( false );
			const wrapper = mountRedLinkTo( MISSING_ID, granted );

			granted.value = true;

			expect( clickRedLink( wrapper ) ).toBe( false );
			expect( openCreatorTarget() ).not.toBeNull();
		} );

	} );

	// What a property given the relation type leaves behind in the Statements it already had.
	it( 'renders nothing for a value of another type', () => {
		const wrapper = mountDisplay( newStringValue( 'Málaga' ) );

		expect( wrapper.find( 'div > :is(a, span)' ).exists() ).toBe( false );
	} );
} );
