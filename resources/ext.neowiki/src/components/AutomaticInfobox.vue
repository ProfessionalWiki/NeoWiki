<template>
	<div class="auto-infobox">
		<div class="auto-infobox__header">
			<h2 class="auto-infobox__title">
				{{ subject.getLabel() }}
			</h2>
		</div>
		<div class="auto-infobox__content">
			<div class="auto-infobox__item auto-infobox__schema-badge">
				<div class="auto-infobox__property">
					{{ $i18n( 'neowiki-infobox-type' ).text() }}
				</div>
				<div class="auto-infobox__value">
					{{ schema.getName() }}
				</div>
			</div>
			<div
				v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
				:key="propertyName"
				class="auto-infobox__item"
			>
				<div class="auto-infobox__property">
					{{ propertyName }}
				</div>
				<div class="auto-infobox__value">
					<component
						:is="getComponent( propertyDefinition.format )"
						:key="`${propertyDefinition.name}${subjectRef?.getStatementValue( propertyDefinition.name )}-auto-infobox`"
						:value="subjectRef?.getStatementValue( propertyDefinition.name )"
						:property="propertyDefinition"
					/>
				</div>
			</div>
		</div>
		<div v-if="canEditSubject" class="auto-infobox__footer">
			<CdxButton
				class="cdx-docs-link"
				weight="quiet"
				@click="editInfoBox">
				{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}
			</CdxButton>
		</div>

		<InfoboxEditor
			v-if="canEditSubject"
			ref="infoboxEditorDialog"
			:is-edit-mode="true"
			:subject="subjectRef as Subject"
			:can-edit-schema="canEditSchema"
			@save="saveSubject"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, PropType, onMounted } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { Schema } from '@neo/domain/Schema';
import { Component } from 'vue';
import InfoboxEditor from '@/components/Editor/InfoboxEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { CdxButton } from '@wikimedia/codex';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	canEditSubject: {
		type: Boolean,
		required: true
	}
} );

const canEditSchema = ref( false );

const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const subjectRef = ref( props.subject );

const schemaStore = useSchemaStore();

const getComponent = ( formatName: string ): Component => NeoWikiServices.getComponentRegistry().getValueDisplayComponent( formatName );

const propertiesToDisplay = computed( (): Record<string, PropertyDefinition> => {
	if ( !subjectRef.value ) {
		console.log( 'subjectRef is null or undefined' );
		return {};
	}

	const schemaName = subjectRef.value.getSchemaName();
	const schema = schemaStore.getSchema( schemaName );

	if ( !schema ) {
		console.error( `Schema not found for name: ${ schemaName }` );
		return {};
	}

	const nonEmptyProperties = subjectRef.value.getNamesOfNonEmptyProperties();

	return schema.getPropertyDefinitions()
		.withNames( nonEmptyProperties )
		.asRecord();
} );

const editInfoBox = (): void => {
	infoboxEditorDialog.value?.openDialog();
	console.log( props.subject?.getId() );
};

const saveSubject = ( savedSubject: Subject ): void => {
	console.log( 'Saved Subject:', savedSubject );
	console.log( 'Saved Subject Statements:', savedSubject.getStatements() );
	subjectRef.value = savedSubject;
	console.log( 'Updated subjectRef:', subjectRef.value );
};

onMounted( async (): Promise<void> => {
	canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( props.schema.getName() );
} );

</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.auto-infobox {
	margin-left: $spacing-100;
	margin-bottom: $spacing-100;
	max-width: 325px;
	border-radius: 5px;
	background-color: $background-color-interactive-subtle !important;
	border: 1px solid $border-color-base;
	float: right;

	&__header {
		background-color: #eaecf0a8 !important;
		border-top-left-radius: 5px;
		border-top-right-radius: 5px;
		padding-top: $spacing-125;
		padding-left: $spacing-125;
		padding-bottom: $spacing-30;
	}

	&__title {
		font-size: $font-size-xx-large;
		margin: $spacing-0 !important;
		position: relative;
		z-index: $z-index-stacking-1;
		border: none;
	}

	&__content {
		padding: $spacing-125;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		margin-bottom: $spacing-75;
		padding-bottom: $spacing-75;
		border-bottom: $border-width-base $border-style-base $border-color-subtle;
		gap: $spacing-50;

		&:last-child {
			border-bottom: none !important;
			margin-bottom: $spacing-0;
			padding-bottom: $spacing-0;
		}
	}

	&__property {
		flex: 0 0 50%;
		font-weight: $font-weight-bold;
		color: $color-emphasized;
		font-size: $font-size-small;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	&__value {
		flex: 1;
		color: $color-subtle;
		font-size: $font-size-small;
		line-height: $line-height-xx-small;
		overflow-wrap: anywhere;
	}

	&__footer {
		padding: $spacing-75 $spacing-125;
		text-align: right;

		button {
			color: $color-progressive !important;
			font-size: $font-size-medium;
		}

		.cdx-button:enabled:focus:not( :active ):not( .cdx-button--is-active ) {
			border: none;
			box-shadow: none;
		}
	}

	&__edit-icon {
		margin-right: $spacing-50;
		font-size: $font-size-large;
	}
}
</style>
