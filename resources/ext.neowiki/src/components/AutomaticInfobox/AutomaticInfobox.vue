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
							:key="`${propertyDefinition.name}${subjectRef?.getStatementValue( propertyDefinition.name )}-automatic-infobox`"
							:value="subjectRef?.getStatementValue( propertyDefinition.name )"
							:property="propertyDefinition"
						/>
					</div>
				</div>
				<a
					v-if="canEdit"
					class="cdx-docs-link"
					@click="editInfoBox">{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}</a>
				<!-- TODO: statements not in schema -->
			</div>
		</div>

		<InfoboxEditor
			v-if="canEditSubjects"
			ref="infoboxEditorDialog"
			:is-edit-mode="true"
			:subject="subjectRef as Subject"
			:can-edit-schema="canEditSchemas"
			@save="saveSubject" />
	</div>
</template>

<script setup lang="ts">
import { computed, ref, PropType } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { Schema } from '@neo/domain/Schema';
import { Component } from 'vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { injectComponentRegistry } from '@/Service.ts';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	canEdit: {
		type: Boolean,
		default: false
	}
} );

const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const subjectRef = ref( props.subject );

const schemaStore = useSchemaStore();

const getComponent = ( formatName: string ): Component => injectComponentRegistry().getValueDisplayComponent( formatName );

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

const canEditSubjects = computed( (): boolean => props.canEdit ); // TODO: add right checks
const canEditSchemas = computed( (): boolean => props.canEdit ); // TODO: add right checks

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
