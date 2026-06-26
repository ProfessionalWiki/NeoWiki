( function () {
	'use strict';

	const Vue = require( 'vue' );
	const codex = require( './codex.js' );
	const nw = require( 'ext.neowiki' );
	const CreateChildDialog = require( './CreateChildDialog.vue' );
	const DIALOG_OPEN_KEY = require( './constants.js' ).DIALOG_OPEN_KEY;

	const TRIGGER_SELECTOR = '.ext-redherb-create-child-company-trigger';

	const open = Vue.ref( false );
	let mounted = false;

	function ensureMounted() {
		if ( mounted ) {
			return;
		}
		const host = document.createElement( 'div' );
		host.className = 'ext-redherb-create-child-mount';
		document.body.appendChild( host );

		const app = Vue.createMwApp( CreateChildDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( nw.NeoWikiExtension.getInstance().getPinia() );
		nw.NeoWikiServices.registerServices( app );
		app.provide( DIALOG_OPEN_KEY, open );
		app.mount( host );
		mounted = true;
	}

	function handleClick( ev ) {
		const trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		ensureMounted();
		open.value = true;
	}

	queueMicrotask( () => {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
