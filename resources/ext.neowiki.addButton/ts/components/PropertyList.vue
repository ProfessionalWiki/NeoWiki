<template>
	<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script lang="ts">
import { useNeoWikiStore } from '../store';
import { defineComponent } from 'vue';

// @vue/component
module.exports = defineComponent( {
	inject: [ 'store' ],
	created() {
		this.store = this.store as ReturnType<typeof useNeoWikiStore>;
	}
} );
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
