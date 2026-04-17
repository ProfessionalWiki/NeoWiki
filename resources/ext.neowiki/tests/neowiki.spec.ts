import './neowiki-test-env';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore';
import { registerSubjectCreatorClickHandler } from '@/neowiki';

describe( 'registerSubjectCreatorClickHandler', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		document.body.innerHTML = '';
	} );

	it( 'opens the subject creator when a matching element is directly clicked', () => {
		const pinia = createPinia();
		setActivePinia( pinia );
		const store = useSubjectStore();
		registerSubjectCreatorClickHandler( pinia );

		const button = document.createElement( 'button' );
		button.setAttribute( 'data-mw-neowiki-action', 'open-subject-creator' );
		document.body.appendChild( button );

		button.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'opens the subject creator when a descendant inside a matching element is clicked', () => {
		const pinia = createPinia();
		setActivePinia( pinia );
		const store = useSubjectStore();
		registerSubjectCreatorClickHandler( pinia );

		const link = document.createElement( 'a' );
		link.setAttribute( 'data-mw-neowiki-action', 'open-subject-creator' );
		const span = document.createElement( 'span' );
		link.appendChild( span );
		document.body.appendChild( link );

		span.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'does nothing when an unrelated element is clicked', () => {
		const pinia = createPinia();
		setActivePinia( pinia );
		const store = useSubjectStore();
		registerSubjectCreatorClickHandler( pinia );

		const unrelated = document.createElement( 'div' );
		document.body.appendChild( unrelated );

		unrelated.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		expect( store.subjectCreatorOpen ).toBe( false );
	} );
} );
