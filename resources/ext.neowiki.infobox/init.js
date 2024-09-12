/**
 * @typedef {import('../../Neo/neojs/dist/neo').Neo} Neo
 */

( function () {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );
	const { Neo } = require( '../../Neo/neojs/dist/neo.js' );

	Vue.createMwApp( App )
		.mount( '#neowiki-infobox' );

	/** @type {Neo} */
	const neo = Neo.getInstance();
	console.log( neo.add( 2, 3 ) );
	console.log( neo.multiply( 2, 3 ) );
	console.log( neo.getSomething().doSomething() );

	const anotherThing = neo.getSomething().getAnotherThing();
	console.log( anotherThing.doAnotherThing() );
}() );
