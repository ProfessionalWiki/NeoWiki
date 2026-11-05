<template>
	<MonolingualTextInput
		:model-value="value"
		:label="$i18n( 'neowiki-property-editor-labels' ).text()"
		:property="labelsProperty"
		:server-violations="violations"
		@update:model-value="onInput"
	/>
</template>

<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import type { MonolingualTextValue, Value } from '@/domain/Value.ts';
import { newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import { labelsAsValue, labelsOf, repeatedLanguageViolations } from '@/components/SchemaEditor/Property/labelEditing.ts';

const props = defineProps<{
	labels?: Readonly<Record<string, string>>;
}>();

const emit = defineEmits<{
	'update:labels': [ Record<string, string> | undefined ];
}>();

const labelsProperty = newMonolingualTextProperty( {
	name: 'labels',
	description: mw.message( 'neowiki-property-editor-labels-description' ).text(),
	multiple: true
} );

// Held here rather than read back from the labels emitted, which keep one label per language: a second one the user
// typed in a language would vanish from the field.
const value = shallowRef<MonolingualTextValue | undefined>( labelsAsValue( props.labels ) );

const violations = computed( () => repeatedLanguageViolations( value.value, labelsProperty.name.toString() ) );

function onInput( newValue: Value | undefined ): void {
	value.value = newValue as MonolingualTextValue | undefined;
	emit( 'update:labels', labelsOf( value.value ) );
}

export interface PropertyLabelsInputExposes {
	unparseableInputMessage(): string | null;
}

function unparseableInputMessage(): string | null {
	return violations.value.length === 0 ? null : mw.message( 'neowiki-field-single-value-per-language' ).text();
}

defineExpose<PropertyLabelsInputExposes>( { unparseableInputMessage } );
</script>
