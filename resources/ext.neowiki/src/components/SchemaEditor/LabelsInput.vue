<template>
	<MonolingualTextInput
		ref="input"
		:model-value="value"
		:label="props.label"
		:property="labelsProperty"
		:server-violations="violations"
		@update:model-value="onInput"
	/>
</template>

<script setup lang="ts">
import { computed, ref, shallowRef } from 'vue';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import type { MonolingualTextValue, Value } from '@/domain/Value.ts';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
import { newMonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import { labelsAsValue, labelsOf, repeatedLanguageViolations } from '@/components/SchemaEditor/labelEditing.ts';

const props = defineProps<{
	labels?: Readonly<Record<string, string>>;
	label: string;
	description: string;
}>();

const emit = defineEmits<{
	'update:labels': [ Record<string, string> | undefined ];
}>();

const labelsProperty = newMonolingualTextProperty( {
	name: 'labels',
	description: props.description,
	multiple: true,
	uniqueLanguages: true
} );

const input = ref<ValueInputExposes | null>( null );

// Held here rather than read back from the labels emitted, which keep one label per language: a second one the user
// typed in a language would vanish from the field.
const value = shallowRef<MonolingualTextValue | undefined>( labelsAsValue( props.labels ) );

const violations = computed( () => repeatedLanguageViolations( value.value, labelsProperty.name.toString() ) );

function onInput( newValue: Value | undefined ): void {
	value.value = newValue as MonolingualTextValue | undefined;
	emit( 'update:labels', labelsOf( value.value ) );
}

export interface LabelsInputExposes {
	unparseableInputMessage(): string | null;
}

function unparseableInputMessage(): string | null {
	return input.value?.unparseableInputMessage?.() ?? repeatedLanguageMessage();
}

function repeatedLanguageMessage(): string | null {
	return violations.value.length === 0 ? null : mw.message( 'neowiki-field-single-value-per-language' ).text();
}

defineExpose<LabelsInputExposes>( { unparseableInputMessage } );
</script>
