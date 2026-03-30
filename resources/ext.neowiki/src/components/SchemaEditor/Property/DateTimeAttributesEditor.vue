<template>
	<div class="datetime-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="toLocalInputValue( property.minimum )"
					@input="updateMinimum"
				/>
			</CdxField>

			<CdxField>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="toLocalInputValue( property.maximum )"
					@input="updateMaximum"
				/>
			</CdxField>
		</NeoNestedField>
	</div>
</template>

<script setup lang="ts">
import { CdxField } from '@wikimedia/codex';
import { DateTimeProperty } from '@/domain/propertyTypes/DateTime.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

defineProps<AttributesEditorProps<DateTimeProperty>>();
const emit = defineEmits<AttributesEditorEmits<DateTimeProperty>>();

function toLocalInputValue( isoString: string | undefined ): string {
	if ( !isoString ) {
		return '';
	}
	return isoString.replace( /Z$/, '' ).slice( 0, 16 );
}

function fromLocalInputValue( localValue: string ): string | undefined {
	return localValue ? localValue + ':00Z' : undefined;
}

const updateMinimum = ( event: Event ): void => {
	const target = event.target as HTMLInputElement;
	emit( 'update:property', { minimum: fromLocalInputValue( target.value ) } );
};

const updateMaximum = ( event: Event ): void => {
	const target = event.target as HTMLInputElement;
	emit( 'update:property', { maximum: fromLocalInputValue( target.value ) } );
};
</script>
