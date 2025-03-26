<template>
	<CdxDialog
		v-if="localSubject"
		v-model:open="isOpen"
		:use-close-button="true"
		:title="$i18n( 'neowiki-infobox-editor-dialog-title' ).text()"
		class="infobox-editor"
	>
		<DeleteDialog
			v-if="props.subject !== undefined"
			class="delete-subject-dialog"
			:is-open="isDeleteDialogOpen"
			@delete="deleteSubject( props.subject.getId() )"
			@close="isDeleteDialogOpen = false"
		>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p v-html="$i18n( 'neowiki-infobox-editor-delete-subject-confirmation-message', props.subject.getLabel() ).text()" />
		</DeleteDialog>

		<NeoTextField
			:model-value="localSubject.getLabel()"
			:required="true"
			:label="$i18n( 'neowiki-infobox-editor-subject-label' ).text()"
			@update:model-value="updateSubjectLabel"
		/>

		<NeoTextField
			v-if="isNewSchema"
			:model-value="localSubject.getSchemaName()"
			:required="true"
			:label="$i18n( 'neowiki-infobox-editor-schema-label' ).text()"
			@update:model-value="updateSchemaName"
		/>

		<div v-else class="infobox-editor-schema-name">
			<h4 class="schema-name-label">
				{{ $i18n( 'neowiki-infobox-editor-schema-label' ).text() }}
			</h4>
			<span class="schema-name-value">{{ localSubject.getSchemaName() }}</span>
		</div>

		<div v-if="statements.length > 0 && schemaHasPropertyDefinitions" class="statement-editor-heading">
			<h4 class="property">
				{{ $i18n( 'neowiki-infobox-editor-property-label' ).text() }}
			</h4>
			<h4 class="value">
				{{ $i18n( 'neowiki-infobox-editor-value-label' ).text() }}
			</h4>
		</div>
		<div ref="statementEditorBody">
			<template v-for="( statement, index ) in statements">
				<StatementEditor
					v-if="getPropertyDefinition( statement.propertyName as PropertyName )"
					:key="index"
					class="statement-editor-row"
					:statement="statement as Statement"
					:schema-name="localSchema !== null ? localSchema.getName() : ''"
					:can-edit-schema="canEditSchema"
					:property-definition="getPropertyDefinition( statement.propertyName as PropertyName )!"
					@update="updateStatement( index, $event )"
					@remove="removeStatement( index )"
					@edit="editProperty"
				/>
			</template>
		</div>
		<div v-if="canEditSchema" class="add-statement-section">
			<div class="add-statement-placeholder" @click="toggleDropdown">
				<CdxIcon :icon="cdxIconAdd" class="add-icon" />
				<span>{{ $i18n( 'neowiki-infobox-editor-add-property' ).text() }}</span>
			</div>
			<NeoTypeSelectDropdown
				v-if="isDropdownOpen"
				class="neo-type-select-dropdown"
				:class="shouldDropUp ? 'neo-type-select-drop-up' : ''"
				:types="propertyTypes"
				@select="addProperty"
			/>
		</div>
		<PropertyDefinitionEditor
			v-if="canEditSchema && isPropertyEditorOpen"
			:key="localSubject.getId().text + 'info'"
			ref="propertyDefinitionEditorInfo"
			:is-open="isPropertyEditorOpen"
			:edit-mode="isEditingProperty"
			:property="editingProperty as PropertyDefinition"
			@cancel="isPropertyEditorOpen = false"
			@save="handlePropertySave"
		/>
		<template #footer>
			<CdxButton
				v-if="props.subject === undefined"
				:aria-label="$i18n( 'neowiki-create-subject-dialog-go-back' ).text()"
				weight="quiet"
				@click="goBack">
				<CdxIcon :icon="cdxIconArrowPrevious" />
			</CdxButton>
			<CdxButton
				v-else
				test-id="delete-subject-button"
				action="destructive"
				weight="quiet"
				@click="isDeleteDialogOpen = true"
			>
				<CdxIcon :icon="cdxIconTrash" />
				{{ $i18n( 'neowiki-infobox-editor-delete-subject' ).text() }}
			</CdxButton>

			<CdxButton
				class="neo-button"
				action="progressive"
				weight="primary"
				:disabled="!canSubmit"
				@click="submit">
				{{ $i18n( 'neowiki-infobox-editor-save-button' ).text() }}
			</CdxButton>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { CdxButton, CdxDialog, CdxIcon, useResizeObserver } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowPrevious, cdxIconTrash } from '@wikimedia/codex-icons';
import NeoTextField from '@/components/NeoTextField.vue';
import StatementEditor from '@/components/Editor/StatementEditor.vue';
import { Subject } from '@neo/domain/Subject.ts';
import { SubjectId } from '@neo/domain/SubjectId';
import { Schema, SchemaName } from '@neo/domain/Schema';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement';
import NeoTypeSelectDropdown from '@/components/Editor/NeoTypeSelectDropdown.vue';
import DeleteDialog from '@/components/Editor/DeleteDialog.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import PropertyDefinitionEditor from '@/components/Editor/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Value } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	selectedSchema?: string;
	subject?: Subject;
	canEditSchema: boolean;
}>();

const emit = defineEmits( [ 'save', 'back' ] );
const isOpen = ref( false );
const isDeleteDialogOpen = ref( false );
const localSubject = ref<Subject | null>( null );
const localSchema = ref<Schema | null>( null );
const statements = ref<Statement[]>( [] );
const initialSubject = ref<Subject | null>( null );
const initialStatements = ref<Statement[]>( [] );
const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();
const propertyDefinitionEditorInfo = ref<InstanceType<typeof PropertyDefinitionEditor> | null>( null );
const editingProperty = ref<PropertyDefinition | null>( null );
const selectedSchema = computed( () => props.selectedSchema );
const isNewSchema = computed( () => props.selectedSchema === '' );
const isNewSubject = computed( () => props.subject === undefined );
const isPropertyEditorOpen = ref( false );
const statementEditorBody = ref<Element | undefined>( undefined );
const statementEditorDimensions = useResizeObserver( statementEditorBody );
const shouldDropUp = computed( () => {
	if ( statementEditorDimensions.value.height !== undefined ) {
		return statementEditorDimensions.value.height > 320;
	}
	return false;
} );
const shouldSaveSchema = ref( false );

const propertyTypes = NeoWikiServices.getComponentRegistry().getLabelsAndIcons().map( ( { value, label, icon } ) => ( {
	value: value,
	label: mw.message( label ).text(),
	icon: icon
} ) );

const validator = NeoWikiServices.getSubjectValidator();

// eslint-disable-next-line arrow-body-style
const canSubmit = computed( () => {
	return localSubject.value !== null &&
		localSchema.value !== null &&
		(
			localSubject.value.getLabel() !== initialSubject.value?.getLabel() ||
			localSubject.value.getSchemaName() !== initialSubject.value?.getSchemaName() ||
			JSON.stringify( statements.value ) !== JSON.stringify( initialStatements.value )
		) &&
		validator.validate( getCurrentSubject(), localSchema.value as Schema );
} );

const getPropertyDefinition = ( propertyName: PropertyName ): PropertyDefinition | undefined => {
	if ( localSchema.value instanceof Schema ) {
		if ( localSchema.value.getPropertyDefinitions().has( propertyName ) ) {
			return localSchema.value.getPropertyDefinition( propertyName );
		}
	}
	return undefined;
};

const schemaHasPropertyDefinitions = computed( (): boolean => {
	if ( !localSchema.value ) {
		return false;
	}
	return Object.keys( localSchema.value.getPropertyDefinitions().asRecord() ).length > 0;
} );

const addMissingStatements = (): void => {
	if ( localSubject.value !== null && localSchema.value !== null ) {
		const existingPropertyNames = new Set( statements.value.map( ( stmt ) => stmt.propertyName.toString() ) );

		const missingStatements = Array.from( localSchema.value.getPropertyDefinitions() )
			.filter( ( propertyDef ) => !existingPropertyNames.has( propertyDef.name.toString() ) )
			.map( ( propertyDef ) => new Statement(
				propertyDef.name,
				propertyDef.format,
				propertyDef.default as Value
			)
			);
		statements.value = [ ...statements.value, ...missingStatements ];
	}
};

const openDialog = async (): Promise<void> => {
	await nextTick();

	isOpen.value = true;
	shouldSaveSchema.value = false;

	if ( props.subject !== undefined ) {
		setupExistingSubject( props.subject );
	} else if ( selectedSchema.value !== undefined ) {
		setupNewSubject( selectedSchema.value );
	} else {
		throw new Error( 'No subject and no schema' );
	}

	setupInitialState();
};

const setupExistingSubject = ( subject: Subject ): void => {
	localSchema.value = schemaStore.getSchema( subject.getSchemaName() );
	localSubject.value = new Subject(
		subject.getId(),
		subject.getLabel(),
		subject.getSchemaName(),
		subject.getStatements(),
		subject.getPageIdentifiers()
	);
	statements.value = [ ...subject.getStatements() ];
	addMissingStatements();
};

const setupNewSubject = ( schemaName: string ): void => {
	if ( schemaName === '' ) {
		shouldSaveSchema.value = true;
		localSchema.value = new Schema(
			'' as SchemaName,
			'',
			new PropertyDefinitionList( [] )
		);
	} else {
		localSchema.value = schemaStore.getSchema( schemaName );
	}

	localSubject.value = new Subject(
		new SubjectId( 'stodotodotodo42' ),
		'',
		schemaName as SchemaName,
		new StatementList( [] ),
		new PageIdentifiers( mw.config.get( 'wgArticleId' ), 'page-title' )
	);
	statements.value = [];
	addMissingStatements();
};

const setupInitialState = (): void => {
	initialSubject.value = getCurrentSubject();
	initialStatements.value = [ ...statements.value ];
};

const isDropdownOpen = ref( false );
const isEditingProperty = ref( false );

const editProperty = ( propertyName: PropertyName ): void => {
	isEditingProperty.value = true;
	if ( localSubject.value && localSchema.value !== null ) {
		const property = localSchema.value.getPropertyDefinitions().get( propertyName );
		if ( property ) {
			editingProperty.value = property as PropertyDefinition;
			isPropertyEditorOpen.value = true;
		}
	}
};

const handlePropertySave = ( savedProperty: PropertyDefinition ): void => {
	if ( editingProperty.value === null || localSchema.value === null ) {
		console.error( 'No property name found to update' );
		return;
	}

	shouldSaveSchema.value = true;

	const propertyName = editingProperty.value?.name;

	if ( isEditingProperty.value === false ) {
		handleAddProperty( savedProperty );
		isPropertyEditorOpen.value = false;
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
			statement.value || savedProperty.default as Value
		) :
		statement
	);

	editingProperty.value = null;
	isPropertyEditorOpen.value = false;
};

const handleAddProperty = ( savedProperty: PropertyDefinition ): void => {
	if ( localSubject.value !== null && localSchema.value !== null ) {
		localSchema.value = localSchema.value.withAddedPropertyDefinition( savedProperty );
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
	editingProperty.value = { // TODO: do we need to get a basic property definition via the plugin system?
		name: '' as unknown as PropertyName,
		format: type, // TODO: is this correct? Name mismatch
		description: '',
		required: false
	};
	isPropertyEditorOpen.value = true;
};

const updateStatement = ( index: number, updatedStatement: Statement ): void => {
	statements.value[ index ] = updatedStatement;
};

const removeStatement = ( index: number ): void => {
	if ( localSchema.value !== null ) {
		shouldSaveSchema.value = true;
		localSchema.value = localSchema.value.withRemovedPropertyDefinition( statements.value[ index ].propertyName as PropertyName );
	}
	statements.value.splice( index, 1 );
};

const goBack = (): void => {
	emit( 'back' );
	isOpen.value = false;
};

function getCurrentSubject(): Subject {
	if ( localSubject.value === null ) {
		throw new Error( 'Subject is missing' );
	}
	return localSubject.value.withStatements( new StatementList( statements.value as Statement[] ) );
}

const submit = async (): Promise<void> => {
	if ( localSchema.value === null || localSubject.value === null ) {
		throw new Error( 'Schema or Subject is missing' );
	}

	if ( shouldSaveSchema.value ) {
		await schemaStore.saveSchema( localSchema.value as Schema );
	}

	localSubject.value = getCurrentSubject();

	if ( isNewSubject.value ) {
		await subjectStore.createMainSubject( localSubject.value as Subject );
	} else {
		await subjectStore.updateSubject( localSubject.value as Subject );
	}

	emit( 'save', localSubject.value );
	isOpen.value = false;
};

const deleteSubject = async ( subjectId: SubjectId ): Promise<void> => {
	try {
		await subjectStore.deleteSubject( subjectId );
		isOpen.value = false;
		emit( 'save', null );
	} catch ( e ) {
		console.error( e );
	}
};

const updateSubjectLabel = ( label: string ): void => {
	localSubject.value = localSubject.value!.withLabel( label );
};

const updateSchemaName = ( name: string ): void => {
	localSchema.value = localSchema.value!.withName( name );
	localSubject.value = localSubject.value!.withSchemaName( name as SchemaName );
};

defineExpose( { openDialog } );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

@mixin grid-header {
	margin-top: $spacing-100;
	display: grid;
	grid-template-columns: 1fr 1fr 3fr;
	margin-bottom: 11px;
	padding-right: $size-75;
}

.cdx-dialog.infobox-editor {
	max-width: 48rem;
	max-height: 90vh;
	display: flex;
	flex-direction: column;

	.infobox-editor-schema-name {
		@include grid-header;
		margin-top: $spacing-150;

		.schema-name-label {
			padding: 0;
			margin: 0;
		}

		.schema-name-value {
			grid-column: 3;
		}
	}

	.statement-editor-heading {
		@include grid-header;

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

.neo-type-select-drop-up {
	position: absolute;
	bottom: 100%;
	left: 0;
	margin-bottom: $spacing-25;
}
</style>
