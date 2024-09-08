<template>
	<strong>{{ message }}: {{ counter }}</strong>
	<cdx-button
		action="progressive"
		weight="primary"
		@click="increment"
	>
		<cdx-icon :icon="cdxIconAdd"></cdx-icon>
		{{ $i18n( 'neowiki-increment' ) }}
	</cdx-button>
</template>

<script>
const { CdxButton, CdxIcon } = require( '../../codex.js' );
const { cdxIconAdd } = require( '../../icons.json' );
const { useCounterStore } = require( '../stores/counter.js' );

// @vue/component
module.exports = exports = {
	components: {
		CdxButton,
		CdxIcon
	},
	props: {
		message: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			cdxIconAdd
		};
	},
	computed: {
		counter() {
			const store = useCounterStore();
			return store.count;
		}
	},
	methods: {
		increment() {
			const store = useCounterStore();
			store.increment();
		}
	}
};
</script>
