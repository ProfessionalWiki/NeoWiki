<template>
	<TextAttributesEditor
		class="ext-neowiki-monolingual-text-attributes"
		:property="property"
		@update:property="emit( 'update:property', $event )"
	>
		<template #values>
			<CdxField :is-fieldset="true">
				<template #label>
					{{ $i18n( 'neowiki-property-editor-values' ).text() }}
				</template>

				<div
					v-for="{ label, example, attributes, constraint } in OPTIONS"
					:key="label"
					class="ext-neowiki-monolingual-text-attributes__option ext-neowiki-severity-radio-row"
				>
					<CdxRadio
						:model-value="chosenOption.label"
						:input-value="label"
						:name="radioName"
						@update:model-value="choose( attributes )"
					>
						{{ $i18n( label ).text() }}
						<template #description>
							{{ $i18n( example ).text() }}
						</template>
					</CdxRadio>
					<SeverityInput
						v-if="label === chosenOption.label && constraint !== undefined"
						:constraint="$i18n( label ).text()"
						:model-value="property.constraintSeverities?.[ constraint ]"
						@update:model-value="( severity: Severity ) => updateSeverity( constraint, severity )"
					/>
				</div>
			</CdxField>
		</template>
	</TextAttributesEditor>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxField, CdxRadio, useGeneratedId } from '@wikimedia/codex';
import type { MonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

interface ValuesOption {
	readonly label: string;
	readonly example: string;
	readonly attributes: Pick<MonolingualTextProperty, 'multiple' | 'uniqueLanguages'>;
	readonly constraint?: 'multiple' | 'uniqueLanguages';
}

const ONE_VALUE: ValuesOption = {
	label: 'neowiki-property-editor-values-one',
	example: 'neowiki-property-editor-values-one-help',
	attributes: { multiple: false, uniqueLanguages: false },
	constraint: 'multiple'
};

const ONE_VALUE_PER_LANGUAGE: ValuesOption = {
	label: 'neowiki-property-editor-values-one-per-language',
	example: 'neowiki-property-editor-values-one-per-language-help',
	attributes: { multiple: true, uniqueLanguages: true },
	constraint: 'uniqueLanguages'
};

const MULTIPLE_VALUES_PER_LANGUAGE: ValuesOption = {
	label: 'neowiki-property-editor-values-multiple-per-language',
	example: 'neowiki-property-editor-values-multiple-per-language-help',
	attributes: { multiple: true, uniqueLanguages: false }
};

const OPTIONS = [ ONE_VALUE, ONE_VALUE_PER_LANGUAGE, MULTIPLE_VALUES_PER_LANGUAGE ];

const props = defineProps<AttributesEditorProps<MonolingualTextProperty>>();
const emit = defineEmits<AttributesEditorEmits<MonolingualTextProperty>>();

const radioName = useGeneratedId( 'ext-neowiki-monolingual-text-values' );

const chosenOption = computed( (): ValuesOption => {
	if ( !props.property.multiple ) {
		return ONE_VALUE;
	}

	return props.property.uniqueLanguages ? ONE_VALUE_PER_LANGUAGE : MULTIPLE_VALUES_PER_LANGUAGE;
} );

function choose( attributes: ValuesOption[ 'attributes' ] ): void {
	emit( 'update:property', { ...attributes } );
}

function updateSeverity( constraint: 'multiple' | 'uniqueLanguages', severity: Severity ): void {
	emit( 'update:property', withConstraintSeverity( props.property, constraint, severity ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-monolingual-text-attributes {
	&__option + &__option {
		margin-top: @spacing-75;
	}
}
</style>
