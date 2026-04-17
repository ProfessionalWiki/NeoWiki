<template>
	<!-- cdx-field class is used for spacing -->
	<div class="select-attributes cdx-field">
		<CdxField :hide-label="true">
			<CdxToggleSwitch
				:model-value="property.multiple"
				:align-switch="true"
				:label="$i18n( 'neowiki-property-editor-multiple' ).text()"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

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
import { CdxChipInput, CdxField, CdxToggleSwitch } from '@wikimedia/codex';
import type { ChipInputItem } from '@wikimedia/codex';
import { SelectOption, SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

const props = defineProps<AttributesEditorProps<SelectProperty>>();
const emit = defineEmits<AttributesEditorEmits<SelectProperty>>();

const optionsError = ref<string | null>( null );

const optionChips = computed( (): ChipInputItem[] =>
	props.property.options.map( ( option ) => ( { value: option.label } ) )
);

const updateOptions = ( chips: ChipInputItem[] ): void => {
	const newLabels = chips.map( ( chip ) => String( chip.value ) );
	const hasDuplicates = new Set( newLabels ).size !== newLabels.length;

	if ( hasDuplicates ) {
		optionsError.value = mw.message( 'neowiki-property-editor-options-unique' ).text();
		return;
	}

	optionsError.value = null;
	const newOptions: SelectOption[] = newLabels.map( ( label ) => ( { id: label, label } ) );
	emit( 'update:property', { options: newOptions } );
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};
</script>
