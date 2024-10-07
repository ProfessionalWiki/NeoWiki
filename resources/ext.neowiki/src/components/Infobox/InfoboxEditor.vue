<template>
	<CdxDialog
		v-if="localSubject"
		v-model:open="isOpen"
		:use-close-button="true"
		title="Manage Infobox"
		class="infobox-editor"
	>
		<NeoTextField
			:model-value="localSubject.getLabel()"
			:required="true"
			:label="$i18n( 'neowiki-infobox-editor-subject-label' ).text()"
			@validation="handleValidation"
		/>
		<NeoTextField
			:model-value="localSubject.getSchemaName()"
			:required="true"
			:label="$i18n( 'neowiki-create-subject-dialog-schema' ).text()"
			disabled
		/>

		<div v-if="statements.length > 0" class="statement-editor-heading">
			<h4 class="property">
				{{ $i18n( 'neowiki-infobox-editor-property-label' ).text() }}
			</h4>
			<h4 class="value">
				{{ $i18n( 'neowiki-infobox-editor-value-label' ).text() }}
			</h4>
		</div>
		<StatementEditor
			v-for="( statement, index ) in statements"
			:key="index"
			class="statement-editor-row"
			:statement="<Statement>statement"
			:can-edit-schema="canEditSchema"
			@update="updateStatement( index, $event )"
			@remove="removeStatement( index )"
			@edit="editProperty"
		/>
		<div v-if="canEditSchema" class="add-statement-section">
			<div class="add-statement-placeholder" @click="toggleDropdown">
				<CdxIcon :icon="cdxIconAdd" class="add-icon" />
				<span>{{ $i18n( 'neowiki-infobox-editor-add-property' ).text() }}</span>
			</div>
			<NeoTypeSelectDropdown
				v-if="isDropdownOpen"
				:types="statementTypes"
				@select="addProperty"
			/>
		</div>
		<PropertyDefinitionEditor
			v-if="canEditSchema"
			:key="localSubject.getId().text + 'info'"
			ref="propertyDefinitionEditorInfo"
			:edit-mode="isEditingProperty"
			:property="editingProperty as PropertyDefinition"
			@save="handlePropertySave"
		/>
		<template #footer>
			<CdxButton
				:aria-label="$i18n( 'neowiki-create-subject-dialog-go-back' ).text()"
				weight="quiet"
				@click="goBack">
				<CdxIcon :icon="cdxIconArrowPrevious" />
			</CdxButton>

			<CdxButton
				class="neo-button"
				action="progressive"
				weight="primary"
				@click="async () => await submit()">
				{{ $i18n( 'neowiki-infobox-editor-save-button' ).text() }}
			</CdxButton>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowPrevious, cdxIconLink } from '@wikimedia/codex-icons';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import StatementEditor from '@/components/UIComponents/StatementEditor.vue';
import { Subject } from '@neo/domain/Subject.ts';
import { SubjectId } from '@neo/domain/SubjectId';
import { Schema, SchemaName } from '@neo/domain/Schema';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement';
import NeoTypeSelectDropdown from '@/components/UIComponents/NeoTypeSelectDropdown.vue';
import { cdxIconStringInteger, cdxIconTextA } from '@/assets/CustomIcons';
import { useSchemaStore } from '@/stores/SchemaStore';
import PropertyDefinitionEditor from '@/components/UIComponents/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';

const props = defineProps<{
	selectedSchema?: string;
	subject?: Subject;
	canEditSchema: boolean;
}>();

const emit = defineEmits( [ 'save', 'back' ] );
const isOpen = ref( false );
const localSubject = ref<Subject | null>( null );
const localSchema = ref<Schema | null>( null );
const statements = ref<Statement[]>( [] );
const schemaStore = useSchemaStore();
const propertyDefinitionEditorInfo = ref<InstanceType<typeof PropertyDefinitionEditor> | null>( null );
const editingProperty = ref<PropertyDefinition | null>( null );

const addMissingStatements = (): void => {
	if ( props.subject !== undefined && localSchema.value !== null ) {
		const existingPropertyNames = new Set( statements.value.map( ( stmt ) => stmt.propertyName.toString() ) );

		const missingStatements = Array.from( localSchema.value.getPropertyDefinitions() )
			.filter( ( propertyDef ) => !existingPropertyNames.has( propertyDef.name.toString() ) )
			.map( ( propertyDef ) => new Statement(
				propertyDef.name,
				propertyDef.format,
				undefined
			)
			);
		statements.value = [ ...statements.value, ...missingStatements ];
	}
};

const openDialog = (): void => {
	isOpen.value = true;
	if ( props.subject !== undefined ) {
		localSchema.value = schemaStore.getSchema( props.subject.getSchemaName() );
		localSubject.value = new Subject(
			props.subject.getId(),
			props.subject.getLabel(),
			props.subject.getSchemaName(),
			props.subject.getStatements(),
			props.subject.getPageIdentifiers()
		);
		statements.value = [ ...props.subject.getStatements() ];
		addMissingStatements();
	} else {
		localSubject.value = new Subject(
			new SubjectId( 'stodotodotodo42' ),
			'',
			props.selectedSchema as SchemaName,
			new StatementList( [] ),
			new PageIdentifiers( 1, 'page-title' )
		);
		statements.value = [];
	}
};

const isDropdownOpen = ref( false );
const isEditingProperty = ref( false );

const statementTypes = [
	{ value: 'text', label: 'Text', icon: cdxIconTextA },
	{ value: 'url', label: 'URL', icon: cdxIconLink },
	{ value: 'number', label: 'Number', icon: cdxIconStringInteger }
];

const editProperty = ( propertyName: PropertyName ): void => {
	isEditingProperty.value = true;
	if ( localSubject.value && localSchema.value !== null ) {
		const property = localSchema.value.getPropertyDefinitions().get( propertyName );
		if ( property ) {
			editingProperty.value = property as PropertyDefinition;
			propertyDefinitionEditorInfo.value?.openDialog();
		}
	}
};

const handlePropertySave = ( savedProperty: PropertyDefinition ): void => {
	const propertyName = editingProperty.value?.name;

	if ( !propertyName || localSchema.value === null ) {
		console.error( 'No property name found to update' );
		return;
	}

	if ( isEditingProperty.value === false ) {
		handleAddProperty( savedProperty );
		return;
	}

	const currentProperties = localSchema.value.getPropertyDefinitions();

	const updatedProperties = Array.from( currentProperties ).map( ( prop ) => prop.name.toString() === propertyName.toString() ? savedProperty : prop
	);
	const newPropertyList = new PropertyDefinitionList( updatedProperties );

	localSchema.value = new Schema(
		localSchema.value.getName(),
		localSchema.value.getDescription(),
		newPropertyList
	);

	statements.value = statements.value.map( ( statement ) => statement.propertyName.toString() === editingProperty.value?.name.toString() ?
		new Statement(
			new PropertyName( savedProperty.name.toString() ),
			savedProperty.format,
			statement.value
		) :
		statement
	);

	editingProperty.value = null;
};

const handleAddProperty = ( savedProperty: PropertyDefinition ): void => {
	if ( props.subject !== undefined && localSchema.value !== null ) {
		const updatedProperties = [ ...localSchema.value.getPropertyDefinitions(), savedProperty ];

		const newPropertyList = new PropertyDefinitionList( updatedProperties );

		localSchema.value = new Schema(
			localSchema.value.getName(),
			localSchema.value.getDescription(),
			newPropertyList
		);

		addMissingStatements();
	}
	editingProperty.value = null;
};

const toggleDropdown = (): void => {
	isDropdownOpen.value = !isDropdownOpen.value;
};

const addProperty = ( type: string ): void => {
	isDropdownOpen.value = false;
	isEditingProperty.value = false;
	editingProperty.value = {
		name: new PropertyName( ' ' ),
		format: type, // TODO: is this correct? Name mismatch
		description: '',
		required: false
	};
	propertyDefinitionEditorInfo.value?.openDialog();
};

const updateStatement = ( index: number, updatedStatement: Statement ): void => {
	statements.value[ index ] = updatedStatement;
};

const removeStatement = ( index: number ): void => {
	statements.value.splice( index, 1 );
};

const handleValidation = ( isValid: boolean ): void => {
	console.log( isValid );
};

const goBack = (): void => {
	emit( 'back' );
	isOpen.value = false;
};

const submit = async (): Promise<void> => {
	if ( localSubject.value ) {
		const properStatements = statements.value.map( ( stmt ) => {
			console.log( 'Statement:', stmt );
			return new Statement(
				new PropertyName( stmt.propertyName.toString() ),
				stmt.format,
				stmt.value
			);
		} );

		localSubject.value = new Subject(
			localSubject.value.getId(),
			localSubject.value.getLabel(),
			localSubject.value.getSchemaName(),
			new StatementList( properStatements ),
			localSubject.value.getPageIdentifiers()
		);
	}
	if ( localSchema.value !== null ) {
		const updatedSchema = new Schema(
			localSchema.value.getName(),
			localSchema.value.getDescription(),
			localSchema.value.getPropertyDefinitions()
		);

		await schemaStore.saveSchema( updatedSchema );
	}
	emit( 'save', localSubject.value );
	isOpen.value = false;
};

defineExpose( { openDialog } );
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.cdx-dialog.infobox-editor {
	max-width: $size-5600;
	max-height: 90vh;
	display: flex;
	flex-direction: column;

	.statement-editor-heading {
		margin-top: $spacing-100;
		display: grid;
		grid-template-columns: 1fr 1fr 4fr;
		padding-right: 1.2rem;

		.property {
			grid-column: 1;
		}

		.value {
			grid-column: 3;
		}
	}

	.cdx-dialog__body {
		flex-grow: 1;
		overflow-y: auto;
	}

	footer {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
	}
}

.infobox-editor__content {
	overflow-y: auto;
	max-height: calc( 90vh - #{$size-800} );
	padding-right: $spacing-100;
}

.add-statement-section {
	margin: $spacing-100 0;
	position: relative;
	cursor: $cursor-base--hover;
	float: right;
}

.add-statement-placeholder {
	display: flex;
	align-items: center;
	padding: $spacing-50 $spacing-75;
	background-color: $background-color-interactive-subtle;
	border: $border-width-base $border-style-dashed $border-color-base;
	border-radius: $border-radius-base;
	transition: all $transition-duration-base $transition-timing-function-system;

	&:hover {
		background-color: $background-color-interactive;
		border-color: $border-color-interactive;
	}

	.add-icon {
		margin-right: $spacing-50;
		color: $color-success;
	}

	span {
		color: $color-subtle;
		font-size: $font-size-small;
	}
}

.neo-type-select-dropdown {
	margin-top: $spacing-25;
	width: 137px;
	position: fixed;
	z-index: $z-index-dropdown;
}
</style>
