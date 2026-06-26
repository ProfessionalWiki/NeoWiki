( function () {
	'use strict';

	const Vue = require( 'vue' );
	const codex = require( './codex.js' );
	const nw = require( 'ext.neowiki' );
	const EditMainSubjectDialog = require( './EditMainSubjectDialog.vue' );
	const DIALOG_STATE_KEY = require( './constants.js' ).DIALOG_STATE_KEY;

	const TRIGGER_SELECTOR = '.ext-redherb-edit-main-subject-trigger';
	const MAIN_SUBJECT_SELECTOR = '.ext-neowiki-view[data-mw-neowiki-subject-id]';

	const dialogState = Vue.reactive( { open: false, subjectId: null } );
	let mounted = false;

	function ensureMounted() {
		if ( mounted ) {
			return;
		}
		const host = document.createElement( 'div' );
		host.className = 'ext-redherb-edit-main-subject-mount';
		document.body.appendChild( host );

		const app = Vue.createMwApp( EditMainSubjectDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( nw.NeoWikiExtension.getInstance().getPinia() );
		nw.NeoWikiServices.registerServices( app );
		app.provide( DIALOG_STATE_KEY, dialogState );
		app.mount( host );
		mounted = true;
	}

	function resolveMainSubjectId() {
		const el = document.querySelector( MAIN_SUBJECT_SELECTOR );
		if ( el === null ) {
			return null;
		}
		return el.dataset.mwNeowikiSubjectId || null;
	}

	function handleClick( ev ) {
		const trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		const subjectId = resolveMainSubjectId();
		if ( subjectId === null ) {
			mw.notify(
				mw.message( 'redherb-edit-main-subject-no-main' ).text(),
				{ type: 'warn' }
			);
			return;
		}

		ensureMounted();
		dialogState.subjectId = subjectId;
		dialogState.open = true;
	}

	queueMicrotask( () => {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
