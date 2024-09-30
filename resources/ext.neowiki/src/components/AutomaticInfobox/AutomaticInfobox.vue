<template>
	<div>
		<div class="infobox">
			<div class="infobox-title">
				{{ subject.getLabel() }}
			</div>
			<div class="infobox-statements">
				<div class="infobox-statement">
					<div class="infobox-statement-property">
						{{ $i18n( 'neowiki-infobox-type' ).text() }}
					</div>
					<div class="infobox-statement-value">
						{{ schema.getName() }}
					</div>
				</div>
				<div
					v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
					:key="propertyName"
					class="infobox-statement"
				>
					<div class="infobox-statement-property">
						{{ propertyName }}
					</div>
					<div class="infobox-statement-value">
						<component
							:is="getComponent( propertyDefinition.format )"
							:value="subject.getStatementValue( propertyDefinition.name )"
							:property="propertyDefinition"
						/>
					</div>
				</div>
				<a class="cdx-docs-link" @click="editInfoBox">Edit</a>
				<!-- TODO: statements not in schema -->
			</div>
		</div>

		<CdxButton v-if="canEdit">
			{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}
		</CdxButton>

		<InfoboxEditor
			ref="infoboxEditorDialog"
			:is-edit-mode="true"
			:subject="subjectRef"
			@add-statement="addStatement"
		/>
		<PropertyDefinitionEditor
			:key="`property-editor-${editingProperty ? JSON.stringify( editingProperty ) : 'null'}`"
			ref="propertyDefinitionEditor"
			:property="editingProperty as PropertyDefinition"
			@save="handlePropertySave"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, PropType, nextTick } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition.ts';
import { ValueFormatComponentRegistry } from '@/presentation/ValueFormatComponentRegistry';
import { Schema } from '@neo/domain/Schema';
import { Component } from 'vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';
import PropertyDefinitionEditor from '@/components/UIComponents/PropertyDefinitionEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { newNumberValue, newStringValue, ValueType } from '@neo/domain/Value.ts';
import { Statement } from '@neo/domain/Statement.ts';
import { Value } from 'sass-embedded';
import { StatementList } from '@neo/domain/StatementList.ts';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	valueFormatComponentRegistry: {
		type: Object as PropType<ValueFormatComponentRegistry>,
		required: true
	},
	canEdit: {
		type: Boolean,
		required: false,
		default: false
	}
} );

const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const subjectRef = computed( () => props.subject );

const getComponent = ( formatName: string ): Component => props.valueFormatComponentRegistry.getComponent( formatName ).getInfoboxValueComponent();

const propertiesToDisplay = computed( (): Record<string, PropertyDefinition> => props.schema.getPropertyDefinitions()
	.withNames( props.subject.getNamesOfNonEmptyProperties() )
	.asRecord() );

const editInfoBox = (): void => {
	infoboxEditorDialog.value?.openDialog();
	console.log( props.subject?.getId() );
};

const propertyDefinitionEditor = ref<InstanceType<typeof PropertyDefinitionEditor> | null>( null );
const schemaStore = useSchemaStore();

const selectedPropertyName = ref<string | null>( null );
const selectedPropertyType = ref<string | null>( null );

const editingProperty = ref<PropertyDefinition | null>( null );

const addStatement = ( type: string ): void => {
	selectedPropertyName.value = ''; // Set a default empty string or generate a unique name
	selectedPropertyType.value = type;

	editingProperty.value = {
		name: new PropertyName( ' ' ),
		type: ValueType[ type as keyof typeof ValueType ],
		format: type,
		description: '',
		required: false
	};

	console.log( editingProperty.value );

	nextTick( () => {
		propertyDefinitionEditor.value?.openDialog();
	} );
};
const handlePropertySave = ( savedProperty: PropertyDefinition ): void => {

};

</script>

<style scoped lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.infobox {
	border: $border-base;
	max-width: 300px;
}

.cdx-docs-link {
	margin-left: 40%;
	margin-top: 15px;
	margin-bottom: 15px;
}

.infobox-title {
	text-align: center;
	font-weight: bold;
	padding: 5px;
}

.infobox-statement {
	display: flex;
	padding: 5px;
}

.infobox-statement-property {
	font-weight: bold;
	margin-right: 5px;
}

.infobox-statement-value {
	flex: 1;
}

a {
	color: $color-progressive;
	text-decoration: none;

	&:hover {
		text-decoration: underline;
	}
}
</style>
