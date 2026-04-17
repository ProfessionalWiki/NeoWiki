import './neowiki-test-env';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { Pinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore';
import { registerSubjectCreatorClickHandler } from '@/neowiki';

describe( 'registerSubjectCreatorClickHandler', () => {
	let pinia: Pinia;
	let store: ReturnType<typeof useSubjectStore>;
	let controller: AbortController;

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );
		store = useSubjectStore();
		controller = new AbortController();
		registerSubjectCreatorClickHandler( pinia, controller.signal );
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		controller.abort();
	} );

	it( 'opens the subject creator when a matching element is directly clicked', () => {
		const button = document.createElement( 'button' );
		button.setAttribute( 'data-mw-neowiki-action', 'open-subject-creator' );
		document.body.appendChild( button );

		const notPrevented = button.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( true );
		expect( notPrevented ).toBe( false );
	} );

	it( 'opens the subject creator when a descendant inside a matching element is clicked', () => {
		const link = document.createElement( 'a' );
		link.setAttribute( 'data-mw-neowiki-action', 'open-subject-creator' );
		const span = document.createElement( 'span' );
		link.appendChild( span );
		document.body.appendChild( link );

		const notPrevented = span.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( true );
		expect( notPrevented ).toBe( false );
	} );

	it( 'does nothing when an unrelated element is clicked', () => {
		const unrelated = document.createElement( 'div' );
		document.body.appendChild( unrelated );

		const notPrevented = unrelated.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( false );
		expect( notPrevented ).toBe( true );
	} );
} );
