<template>
	<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script setup lang="ts">
import { inject } from 'vue';
import { Store } from '@/stores/Store.ts';

const store = inject( 'store' ) as Store;
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
