<template>
	<div>
		<HelloWorld :msg="$i18n( 'neowiki-name' ).text()" />

		<div class="neowiki-component">
			<h2>{{ store.extensionName }}</h2>
			<p>Select a Schema Type:</p>
			<select
				v-model="store.selectedSchemaType"
				class="neowiki-select"
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
			</select>
			<p
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
import { useNeoWikiStore } from '@/stores/Store';

const store = useNeoWikiStore();

function onSchemaTypeChange( event: Event ): void {
	const target = event.target as HTMLSelectElement;
	store.updateSchemaType( target.value );
}
</script>

<style lang="scss" scoped>
@import '@/assets/variables';
@import '@/assets/mixins';

.neowiki-component {
	@include card-style;

	font-family: Arial, sans-serif;
	max-width: 600px;
	margin: 0 auto;
	color: $neo-text-color;

	h1,
	h2 {
		color: $neo-primary;
	}

	.neowiki-select {
		@include input-styles;
	}

	ul {
		@include list-reset;

		li {
			background-color: $neo-light;
			margin-bottom: 5px;
			padding: 10px;
			border-radius: 4px;
		}
	}

	.selected-schema {
		color: $neo-success;
		font-weight: bold;
	}
}
</style>
