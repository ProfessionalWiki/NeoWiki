<template>
	<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script lang="ts">
import { Store } from '../store';
import { defineComponent, inject } from 'vue';

const component =  defineComponent( {
	setup() {
		return {
			store: inject('store') as Store
		}
	}
});

export default component; // this can be replaced with module.exports = exports = component in build.js
module.exports = exports = component; // we have to add this for MW to work, that's the only redundancy
</script>

<style scoped>
.user-greeting {
	margin-top: 15px;
	padding: 10px;
	background-color: #f0f0f0;
	border-radius: 4px;
}

p {
	font-weight: bold;
	color: #2c3e50;
}
</style>
