// counterStore.js
const { defineStore } = require( 'pinia' );

exports.useCounterStore = defineStore( 'counter', {
	state: () => ( {
		globalCount: 0
	} ),
	actions: {
		incrementGlobal() {
			this.globalCount++;
		}
	}
} );
