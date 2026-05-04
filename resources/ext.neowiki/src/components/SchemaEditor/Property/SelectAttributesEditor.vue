<template>
	<div class="select-attributes cdx-field">
		<ConstraintAttributesEditor
			:property="property"
			@update:property="onUpdate"
		/>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-options' ).text() }}
			</template>
			<CdxChipInput
				:input-chips="optionChips"
				:status="optionsError === null ? 'default' : 'error'"
				@update:input-chips="updateOptions"
			/>
			<template
				v-if="optionsError !== null"
				#help-text
			>
				{{ optionsError }}
			</template>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxChipInput, CdxField } from '@wikimedia/codex';
import type { ChipInputItem } from '@wikimedia/codex';
import { SelectOption, SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import ConstraintAttributesEditor from '@/components/SchemaEditor/Property/ConstraintAttributesEditor.vue';

const props = defineProps<AttributesEditorProps<SelectProperty>>();
const emit = defineEmits<AttributesEditorEmits<SelectProperty>>();

const optionsError = ref<string | null>( null );

const optionChips = computed( (): ChipInputItem[] =>
	props.property.options.map( ( option ) => ( { value: option.label } ) )
);

function updateOptions( chips: ChipInputItem[] ): void {
	const newLabels = chips.map( ( chip ) => String( chip.value ) );
	const hasDuplicates = new Set( newLabels ).size !== newLabels.length;

	if ( hasDuplicates ) {
		optionsError.value = mw.message( 'neowiki-property-editor-options-unique' ).text();
		return;
	}

	optionsError.value = null;
	const newOptions: SelectOption[] = newLabels.map( ( label ) => ( { id: label, label } ) );
	emit( 'update:property', { options: newOptions } );
}

function onUpdate( partial: Partial<SelectProperty> ): void {
	emit( 'update:property', partial );
}
</script>
