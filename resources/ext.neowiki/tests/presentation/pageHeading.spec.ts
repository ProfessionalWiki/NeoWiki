import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { showLabelInPageHeading } from '@/presentation/pageHeading';
import { newSubject } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers';

const SUBJECT_ID = 's1demo5sssssss2';

/** The heading SubjectLabelHtml gives a page titled by the Subject's id. */
function headPageByLabelAndId( label: string ): void {
	document.body.innerHTML = '<h1 id="firstHeading"><span class="ext-neowiki-subject-label">' +
		`<bdi>${ label }</bdi></span> <span class="ext-neowiki-subject-label-id">(<bdi>${ SUBJECT_ID }</bdi>)</span></h1>`;
}

function heading(): string {
	return document.querySelector( '#firstHeading' )!.textContent!;
}

function shownLabel(): Element {
	return document.querySelector( '#firstHeading .ext-neowiki-subject-label' )!;
}

describe( 'showLabelInPageHeading', () => {

	beforeEach( () => {
		setupMwMock( { messages: { pagetitle: ( name: string ) => `${ name } - Wiki` } } );
		document.title = 'Old label - Wiki';
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'shows the new label in place of the old one, beside the id', () => {
		headPageByLabelAndId( 'Old label' );

		showLabelInPageHeading( newSubject( { id: SUBJECT_ID, label: 'New label' } ) );

		expect( heading() ).toBe( `New label (${ SUBJECT_ID })` );
	} );

	it( 'names the Subject by its new label in the browser tab', () => {
		headPageByLabelAndId( 'Old label' );

		showLabelInPageHeading( newSubject( { id: SUBJECT_ID, label: 'New label' } ) );

		expect( document.title ).toBe( 'New label - Wiki' );
	} );

	// So that a label in one script direction does not reorder the id beside it.
	it( 'isolates the new label', () => {
		headPageByLabelAndId( 'Old label' );

		showLabelInPageHeading( newSubject( { id: SUBJECT_ID, label: 'New label' } ) );

		expect( shownLabel().querySelector( 'bdi' )!.textContent ).toBe( 'New label' );
	} );

	it( 'shows a label as text, never as markup', () => {
		headPageByLabelAndId( 'Old label' );

		showLabelInPageHeading( newSubject( { id: SUBJECT_ID, label: '<img src=x onerror=alert(1)>' } ) );

		expect( shownLabel().textContent ).toBe( '<img src=x onerror=alert(1)>' );
		expect( document.querySelector( '#firstHeading img' ) ).toBeNull();
	} );

	describe( 'for a Subject whose label was removed', () => {

		const unnamed = newSubject( { id: SUBJECT_ID, label: null, displayNameIsGenerated: true } );

		it( 'says no label is defined, as the heading of a Subject without one does', () => {
			headPageByLabelAndId( 'Old label' );

			showLabelInPageHeading( unnamed );

			expect( heading() ).toBe( `neowiki-subject-no-label (${ SUBJECT_ID })` );
			expect( shownLabel().querySelector( '.ext-neowiki-subject-label-none' ) ).not.toBeNull();
		} );

		it( 'names it by its bracketed id in the browser tab', () => {
			headPageByLabelAndId( 'Old label' );

			showLabelInPageHeading( unnamed );

			expect( document.title ).toBe( `(${ SUBJECT_ID }) - Wiki` );
		} );

		// Special:Subject heads such a Subject by the name of the page it is the Main Subject of.
		it( 'shows the page name it is named after', () => {
			document.body.innerHTML = '<h1 id="firstHeading"><span class="ext-neowiki-subject-label"><bdi>Old label</bdi></span></h1>';

			showLabelInPageHeading( newSubject( { label: null, displayName: 'ACME Inc', displayNameIsGenerated: false } ) );

			expect( heading() ).toBe( 'ACME Inc' );
		} );

	} );

	it( 'leaves the title of a page someone named as it is', () => {
		document.body.innerHTML = '<h1 id="firstHeading">ACME Inc</h1>';

		showLabelInPageHeading( newSubject( { label: 'New label' } ) );

		expect( heading() ).toBe( 'ACME Inc' );
		expect( document.title ).toBe( 'Old label - Wiki' );
	} );

} );
