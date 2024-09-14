<template>
	<div>
		<HelloWorld :msg="$i18n( 'neowiki-name' ).text()" />

		<div class="neowiki-component">
			<h2>{{ store.extensionName }}</h2>
			<p>Select a Schema Type:</p>
			<select
				v-model="store.selectedSchemaType"
				@change="onSchemaTypeChange"
			>
				<option value="">
					Select a Schema Type
				</option>
				<option
					v-for="type in store.schemaTypes"
					:key="type"
					:value="type"
				>
					{{ type }}
				</option>
			</select>      <p
				v-if="store.selectedSchemaType"
				class="selected-schema"
			>
				Selected Schema: "{{ store.selectedSchemaType }}"
			</p>
			<PropertyList />
		</div>
	</div>
</template>

<script setup lang="ts">
import PropertyList from '@/components/PropertyList.vue';
import HelloWorld from '@/components/HelloWorld.vue';
import { useNeoWikiStore } from '@/stores/Store.ts';

const store = useNeoWikiStore();

function onSchemaTypeChange( event: Event ): void {
	const target = event.target as HTMLSelectElement;
	store.updateSchemaType( target.value );
}

console.log( 'NeoWiki component mounted' );
</script>

<style scoped>
.neowiki-component {
	font-family: 'Arial', sans-serif;
	max-width: 600px;
	margin: 0 auto;
	padding: 20px;
	border: 1px solid #ccc;
	border-radius: 5px;
}

h1 {
	color: #333;
}

button {
	background-color: #4caf50;
	color: #fff;
	padding: 10px 15px;
	border: 0;
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
	border: 1px solid #ddd;
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
