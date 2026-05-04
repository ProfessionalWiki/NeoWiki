<template>
	<div class="constraint-attributes cdx-field">
		<MultipleToggle
			v-if="kinds.includes( 'multiple-toggle' )"
			:property
			@update:property="onUpdate"
		/>
		<UniqueItemsToggle
			v-if="kinds.includes( 'unique-items-toggle' )"
			:property
			@update:property="onUpdate"
		/>
		<IntegerRange
			v-if="kinds.includes( 'integer-range' )"
			:property
			@update:property="onUpdate"
		/>
		<NumericRange
			v-if="kinds.includes( 'numeric-range' )"
			:property
			@update:property="onUpdate"
		/>
		<DateTimeRange
			v-if="kinds.includes( 'datetime-range' )"
			:property
			@update:property="onUpdate"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { NeoWikiServices } from '@/NeoWikiServices';
import MultipleToggle from '@/components/SchemaEditor/Property/MultipleToggle.vue';
import UniqueItemsToggle from '@/components/SchemaEditor/Property/UniqueItemsToggle.vue';
import IntegerRange from '@/components/SchemaEditor/Property/IntegerRange.vue';
import NumericRange from '@/components/SchemaEditor/Property/NumericRange.vue';
import DateTimeRange from '@/components/SchemaEditor/Property/DateTimeRange.vue';

const props = defineProps<{
	property: PropertyDefinition;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<PropertyDefinition> ];
}>();

const kinds = computed( () => {
	const registry = NeoWikiServices.getPropertyTypeRegistry();
	return registry.getType( props.property.type ).getConstraintAttributes();
} );

function onUpdate( partial: Partial<PropertyDefinition> ): void {
	emit( 'update:property', partial );
}
</script>
