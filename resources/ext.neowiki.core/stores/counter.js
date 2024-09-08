const { defineStore } = require( 'pinia' );

const useCounterStore = defineStore( 'counter', {
	state: () => ( {
		count: 0
	} ),
	actions: {
		increment() {
			this.count++;
		}
	}
} );

module.exports = {
	useCounterStore
};
