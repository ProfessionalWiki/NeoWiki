<template>
	<div>
		<h3> Neowiki FE starts </h3>
		<button class="button-custom1" @click="incrementGlobal">
			Global Count: <strong>{{ globalCount }}</strong>
		</button>
		<button class="button-custom" @click="incrementIndividual">
			Individual Count: <strong>{{ individualCount }}</strong>
		</button>

		<h3> Neowiki FE end </h3>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { useCounterStore } = require( '../counterStore.js' );
const { storeToRefs } = require( 'pinia' );

module.exports = defineComponent( {
	name: 'NeowikiComponent',
	setup() {
		const store = useCounterStore();
		const { globalCount } = storeToRefs( store );

		// Local state for individual count
		const individualCount = ref( 0 );

		const incrementGlobal = () => store.incrementGlobal();
		const incrementIndividual = () => {
			individualCount.value++;
		};

		return {
			globalCount,
			individualCount,
			incrementGlobal,
			incrementIndividual
		};
	}
} );
</script>

<style>
.neowiki-add-button {
	margin-bottom: 20px;
	margin-top: 20px;
}

.button-custom {
	padding: 10px;
	font-size: 16px;
	background-color: #007bff;
	color: white;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	margin-right: 10px;
	text-align: center;
	display: inline-block;
	transition: background-color 0.3s ease;
}

.button-custom:hover {
	background-color: #002044;
}

.button-custom1 {
	padding: 10px;
	font-size: 16px;
	background-color: #158f68;
	color: white;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	margin-right: 10px;
	text-align: center;
	display: inline-block;
	transition: background-color 0.3s ease;
}

.button-custom1:hover {
	background-color: #106c4f;
}

</style>
