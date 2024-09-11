<template>
	<div class="neowiki-component">
		<h2>{{ store.extensionName }}</h2>
		<p>Select a Schema Type:</p>
		<select v-model="store.selectedSchemaType" @change="onSchemaTypeChange">
			<option value="">Select a Schema Type</option>
			<option v-for="type in store.schemaTypes" :key="type" :value="type">
				{{ type }}
			</option>
		</select>
		<p v-if="store.selectedSchemaType" class="selected-schema">
			Selected Schema: "{{ store.selectedSchemaType }}"
		</p>
		<PropertyList/>
	</div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import PropertyList from './PropertyList.vue';
import { useNeoWikiStore } from '../store.ts';

// @vue/component
module.exports = exports = defineComponent( {
	name: 'App',
	components: {
		PropertyList
	},
	inject: [ 'store' ],
	setup() {
		const store = useNeoWikiStore();
		return { store };
	},
	methods: {
		onSchemaTypeChange( event: Event ) {
			const target = event.target as HTMLSelectElement;
			this.store.updateSchemaType( target.value );
		}
	},
	mounted() {
		console.log( 'NeoWiki component mounted' );
	}
} );
</script>

<style>
.neowiki-component {
	font-family: Arial, sans-serif;
	max-width: 600px;
	margin: 0 auto;
	padding: 20px;
	border: 1px solid #cccccc;
	border-radius: 5px;
}

h1 {
	color: #333333;
}

button {
	background-color: #4caf50;
	color: white;
	padding: 10px 15px;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	margin-bottom: 10px;
}

button:hover {
	background-color: #45a049;
}

input {
	width: 100%;
	padding: 10px;
	margin-bottom: 10px;
	border: 1px solid #dddddd;
	border-radius: 4px;
}

ul {
	list-style-type: none;
	padding: 0;
}

li {
	background-color: #f1f1f1;
	margin-bottom: 5px;
	padding: 10px;
	border-radius: 4px;
}
</style>
