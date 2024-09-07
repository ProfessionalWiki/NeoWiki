( function () {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );

	Vue.createMwApp( App )
		.mount( '#neowiki-infobox' );
}() );
