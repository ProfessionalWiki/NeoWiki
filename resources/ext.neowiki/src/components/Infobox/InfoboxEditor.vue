<template>
	<CdxDialog
		v-model:open="isOpen"
		:use-close-button="true"
		title="Manage Infobox"
		class="infobox-editor">
		<NeoTextField
			v-model="localSubject.label"
			:required="true"
			:label="$i18n( 'neowiki-infobox-editor-subject-label' ).text()"
			@validation="handleValidation"
		/>
		<NeoTextField
			v-model="localSubject.schemaName"
			:required="true"
			:min-length="3"
			:max-length="50"
			:label="$i18n( 'neowiki-create-subject-dialog-schema' ).text()"
			disabled
		/>
		<div class="add-statement-section">
			<div class="add-statement-placeholder" @click="toggleDropdown">
				<CdxIcon :icon="cdxIconAdd" class="add-icon" />
				<span>{{ $i18n( 'neowiki-infobox-editor-add-property' ).text() }}</span>
			</div>
			<NeoTypeSelectDropdown
				v-if="isDropdownOpen"
				:types="statementTypes"
				@select="addStatement"
			/>
		</div>
		<StatementEditor
			v-for="( statement, index ) in statements"
			:key="index"
			class="statement-editor-row"
			:statement="statement"
			@update="updateStatement( index, $event )"
			@remove="removeStatement( index )"
			@edit="editProperty"
		/>
		<PropertyDefinitionEditor
			ref="propertyDefinitionEditor"
			:property="editingProperty"
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
				@click="submit">
				{{ $i18n( 'neowiki-infobox-editor-save-button' ).text() }}
			</CdxButton>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxDialog, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArrowPrevious, cdxIconLink } from '@wikimedia/codex-icons';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import StatementEditor from '@/components/UIComponents/StatementEditor.vue';
import { Subject } from '@neo/domain/Subject.ts';
import type { SubjectId } from '@neo/domain/SubjectId';
import { Schema, SchemaName } from '@neo/domain/Schema';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Statement } from '@neo/domain/Statement';
import NeoTypeSelectDropdown from '@/components/UIComponents/NeoTypeSelectDropdown.vue';
import { cdxIconTextA, cdxIconStringInteger } from '@/assets/CustomIcons';
import { useSchemaStore } from '@/stores/SchemaStore';
import PropertyDefinitionEditor from '@/components/UIComponents/PropertyDefinitionEditor.vue';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';

const props = defineProps<{
	selectedSchemaType?: string;
	subject?: Subject;
	isEditMode: boolean;
}>();

const emit = defineEmits( [ 'complete', 'back', 'addStatement' ] );
const isOpen = ref( false );
const localSubject = ref<Subject | null>( null );
const statements = ref<Statement[]>( [] );
const schemaStore = useSchemaStore();
const propertyDefinitionEditor = ref<InstanceType<typeof PropertyDefinitionEditor> | null>( null );
const editingProperty = ref<PropertyDefinition | null>( null );

const openDialog = (): void => {
	isOpen.value = true;
	if ( props.subject ) {
		localSubject.value = new Subject(
			props.subject.getId(),
			props.subject.getLabel(),
			props.subject.getSchemaName(),
			props.subject.getStatements(),
			props.subject.getPageIdentifiers()
		);
		statements.value = [ ...props.subject.getStatements() ];
	} else {
		localSubject.value = new Subject(
			'' as SubjectId, // Temporary ID
			'',
			props.selectedSchemaType as SchemaName,
			new StatementList( [] ),
			{}
		);
		statements.value = [];
	}
};

const isDropdownOpen = ref( false );

const statementTypes = [
	{ value: 'text', label: 'Text', icon: cdxIconTextA },
	{ value: 'url', label: 'URL', icon: cdxIconLink },
	{ value: 'number', label: 'Number', icon: cdxIconStringInteger }
];

const editProperty = ( propertyName: PropertyName ): void => {
	if ( localSubject.value ) {
		const schema = schemaStore.getSchema( localSubject.value.getSchemaName() );
		const property = schema.getPropertyDefinitions().get( propertyName );
		if ( property ) {
			editingProperty.value = property;
			propertyDefinitionEditor.value?.openDialog();
		}
	}
};

const handlePropertySave = ( savedProperty: PropertyDefinition ): void => {

};

const toggleDropdown = (): void => {
	isDropdownOpen.value = !isDropdownOpen.value;
};

const addStatement = ( type: string ): void => {
	emit( 'addStatement', type );
	isDropdownOpen.value = false;
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

const submit = (): void => {
	if ( localSubject.value ) {
		const updatedSubject = new Subject(
			localSubject.value.getId(),
			localSubject.value.getLabel(),
			localSubject.value.getSchemaName(),
			new StatementList( statements.value ),
			localSubject.value.getPageIdentifiers()
		);
		isOpen.value = false;
		emit( 'complete', updatedSubject );
	}
};

const updateSubject = ( newSubject: Subject ): void => {
	localSubject.value = newSubject;
	statements.value = [ ...newSubject.getStatements() ];
};

defineExpose( { openDialog, updateSubject } );
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.cdx-dialog.infobox-editor {
	max-width: $size-5600;
	max-height: 90vh;
	display: flex;
	flex-direction: column;

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

.add-statement-section {
	margin: $spacing-150 0;
	position: relative;
	width: 18%;
	float: right;
}

.neo-type-select-dropdown {
	margin-top: $spacing-25;
	width: $size-full;
	position: absolute;
	z-index: $z-index-dropdown;
}
</style>
