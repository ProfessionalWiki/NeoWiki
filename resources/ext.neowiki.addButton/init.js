( function () {
	const Vue = require( 'vue' );
	const Pinia = require( 'pinia' );
	const App = require( './components/App.vue' );

	const pinia = Pinia.createPinia();

	Vue.createMwApp( App )
		.use( pinia )
		.mount( '#neowiki-add-button' );
}() );
