<template>
	<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script setup lang="ts">
import { useNeoWikiStore } from '@/stores/Store';

const store = useNeoWikiStore();
</script>

<style lang="scss" scoped>
@import '@/assets/scss/variables';

.user-greeting {
	margin-top: 15px;
	padding: 10px;
	background-color: $neo-light;
	border-radius: 4px;

	p {
		font-weight: bold;
		color: $neo-text-color;

		&:hover {
			color: $neo-primary;
		}
	}
}
</style>
