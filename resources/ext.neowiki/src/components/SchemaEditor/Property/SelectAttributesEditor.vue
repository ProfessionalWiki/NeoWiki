<template>
	<!-- cdx-field class is used for spacing -->
	<div class="select-attributes cdx-field">
		<CdxField :hide-label="true">
			<CdxCheckbox
				:model-value="property.multiple"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxCheckbox>
		</CdxField>

		<CdxField class="select-attributes__options ext-neowiki-severity-field">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-options' ).text() }}
			</template>
			<CdxChipInput
				:input-chips="optionChips"
				:status="optionsError === null ? 'default' : 'error'"
				@update:input-chips="updateOptions"
			/>
			<SeverityInput
				v-if="property.options.length > 0"
				:constraint="$i18n( 'neowiki-property-editor-options' ).text()"
				:model-value="property.constraintSeverities?.options"
				@update:model-value="updateSeverity"
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
import { CdxCheckbox, CdxChipInput, CdxField } from '@wikimedia/codex';
import type { ChipInputItem } from '@wikimedia/codex';
import { SelectOption, SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

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

const updateSeverity = ( severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, 'options', severity ) );
};
</script>
