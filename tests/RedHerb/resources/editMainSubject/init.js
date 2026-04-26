( function () {
	'use strict';

	var Vue = require( 'vue' );
	var codex = require( './codex.js' );
	var nw = require( 'ext.neowiki' );
	var EditMainSubjectDialog = require( './EditMainSubjectDialog.vue' );
	var useEditMainSubjectStore = require( './store.js' );

	var TRIGGER_SELECTOR = '.ext-redherb-edit-main-subject-trigger';
	var MAIN_SUBJECT_SELECTOR = '.ext-neowiki-view[data-mw-neowiki-subject-id]';

	var mounted = false;

	function ensureMounted( sharedPinia ) {
		if ( mounted ) {
			return;
		}
		var host = document.createElement( 'div' );
		host.className = 'ext-redherb-edit-main-subject-mount';
		document.body.appendChild( host );

		var app = Vue.createMwApp( EditMainSubjectDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( sharedPinia );
		nw.NeoWikiServices.registerServices( app );
		app.mount( host );
		mounted = true;
	}

	function resolveMainSubjectId() {
		var el = document.querySelector( MAIN_SUBJECT_SELECTOR );
		if ( el === null ) {
			return null;
		}
		return el.dataset.mwNeowikiSubjectId || null;
	}

	function handleClick( ev ) {
		var trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		var subjectId = resolveMainSubjectId();
		if ( subjectId === null ) {
			mw.notify(
				mw.message( 'redherb-edit-main-subject-no-main' ).text(),
				{ type: 'warn' }
			);
			return;
		}

		var sharedPinia = nw.NeoWikiExtension.getInstance().getPinia();
		ensureMounted( sharedPinia );
		useEditMainSubjectStore( sharedPinia ).openDialog( subjectId );
	}

	queueMicrotask( function () {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
