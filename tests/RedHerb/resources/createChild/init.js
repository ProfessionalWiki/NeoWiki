( function () {
	'use strict';

	var Vue = require( 'vue' );
	var codex = require( './codex.js' );
	var nw = require( 'ext.neowiki' );
	var CreateChildDialog = require( './CreateChildDialog.vue' );
	var useCreateChildStore = require( './store.js' );

	var TRIGGER_SELECTOR = '.ext-redherb-create-child-company-trigger';
	var NEOWIKI_APP_ID = 'ext-neowiki-app';

	var mounted = false;

	function ensureMounted( sharedPinia ) {
		if ( mounted ) {
			return;
		}
		var host = document.createElement( 'div' );
		host.className = 'ext-redherb-create-child-mount';
		document.body.appendChild( host );

		var app = Vue.createMwApp( CreateChildDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( sharedPinia );
		nw.NeoWikiServices.registerServices( app );
		app.mount( host );
		mounted = true;
	}

	function pageHasMainSubject() {
		var neoWikiApp = document.getElementById( NEOWIKI_APP_ID );
		return neoWikiApp !== null
			&& neoWikiApp.dataset.mwNeowikiPageHasMainSubject === 'true';
	}

	function handleClick( ev ) {
		var trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		if ( !pageHasMainSubject() ) {
			mw.notify(
				mw.message( 'redherb-create-child-not-eligible' ).text(),
				{ type: 'warn' }
			);
			return;
		}

		var sharedPinia = nw.NeoWikiExtension.getInstance().getPinia();
		ensureMounted( sharedPinia );
		useCreateChildStore( sharedPinia ).openDialog();
	}

	queueMicrotask( function () {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
