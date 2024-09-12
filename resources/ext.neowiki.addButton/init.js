( function () {
	const Vue = require( 'vue' );
	const { createPinia } = require( 'pinia' );
	const App = require( './components/App.vue' );
	const app = Vue.createMwApp( {
		render() {
			const buttons = document.querySelectorAll( '.neowiki-add-button' );
			return Array.from( buttons ).map( ( el, index ) => Vue.h( App, {
				key: index,
				ref: ( instance ) => {
					if ( instance ) {
						el.innerHTML = '';
						el.appendChild( instance.$el );
					}
				}
			} ) );
		}
	} );

	app.use( createPinia() );
	app.mount( '#app-container' );
}() );
