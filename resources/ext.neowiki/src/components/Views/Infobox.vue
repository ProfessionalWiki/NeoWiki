<!-- eslint-disable vue/multi-word-component-names -->
<template>
	<div v-if="subject !== null" class="ext-neowiki-infobox">
		<div class="ext-neowiki-infobox__header">
			<div class="ext-neowiki-infobox__header__text">
				<div
					class="ext-neowiki-infobox__title"
					role="heading"
					aria-level="2"
				>
					{{ subject.getLabel() }}
				</div>
				<div
					class="ext-neowiki-infobox__schema"
					role="heading"
					aria-level="3"
				>
					<a :href="schemaUrl">
						{{ schema?.getName() }}
					</a>
				</div>
			</div>
			<CdxButton
				v-if="canEditSubject"
				weight="quiet"
				:aria-label="$i18n( 'neowiki-infobox-edit-link' ).text()"
				@click="isEditorOpen = true"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
			<SubjectEditorDialog
				v-if="canEditSubject"
				v-model:open="isEditorOpen"
				:subject="subject as Subject"
				:on-save="handleSaveSubject"
				:on-save-schema="handleSaveSchema"
			/>
		</div>
		<div class="ext-neowiki-infobox__content">
			<div
				v-for="resolved in resolvedProperties"
				:key="resolved.propertyDefinition.name.toString()"
				class="ext-neowiki-infobox__item"
			>
				<div class="ext-neowiki-infobox__property">
					{{ resolved.propertyDefinition.name.toString() }}
				</div>
				<div class="ext-neowiki-infobox__value">
					<component
						:is="getComponent( resolved.propertyDefinition.type )"
						:key="`${resolved.propertyDefinition.name}${resolved.value}-ext-neowiki-infobox`"
						:value="resolved.value"
						:property="resolved.propertyDefinition"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Component, computed, ref } from 'vue';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useViewStore } from '@/stores/ViewStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
import { resolveDisplayProperties, type ResolvedProperty } from '@/domain/resolveDisplayProperties.ts';

const props = defineProps( {
	subjectId: {
		type: SubjectId,
		required: true
	},
	canEditSubject: {
		type: Boolean,
		required: true
	},
	viewName: {
		type: String,
		default: undefined
	}
} );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const viewStore = useViewStore();

const isEditorOpen = ref( false );

const subject = computed( () => subjectStore.getSubject( props.subjectId ) as Subject ); // TODO: handle not found
const schema = computed( () => schemaStore.getSchema( subject.value.getSchemaName() ) ); // TODO: handle not found

const handleSaveSubject = async ( updatedSubject: Subject, comment: string ): Promise<void> => {
	await subjectStore.updateSubject( updatedSubject, comment );
};

const handleSaveSchema = async ( updatedSchema: Schema, comment: string ): Promise<void> => {
	await schemaStore.saveSchema( updatedSchema, comment );
};

function getComponent( propertyType: string ): Component {
	return NeoWikiServices.getComponentRegistry().getValueDisplayComponent( propertyType );
}

const schemaUrl = computed( () => {
	if ( !schema.value ) {
		return '';
	}
	return mw.util.getUrl( `Schema:${ schema.value.getName() }` );
} );

const view = computed( () => {
	if ( !props.viewName ) {
		return undefined;
	}
	return viewStore.getView( props.viewName );
} );

const resolvedProperties = computed( (): ResolvedProperty[] => {
	if ( !schema.value || !subject.value ) {
		return [];
	}
	return resolveDisplayProperties( schema.value, subject.value, view.value );
} );

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-infobox {
	margin-inline: auto;
	margin-bottom: @spacing-100;
	max-width: 20rem;
	width: 100%;
	border: @border-base;
	border-radius: @border-radius-base;
	color: @color-base;
	background-color: @background-color-base;
	line-height: @line-height-small;

	@media ( min-width: @min-width-breakpoint-tablet ) {
		clear: both;
		float: right;
		margin-inline: @spacing-100 @spacing-0;
	}

	&__header {
		padding: @spacing-100 @spacing-75;
		display: flex;
		align-items: flex-start;

		&__text {
			flex-grow: 1;
		}
	}

	&__title {
		font-size: @font-size-x-large;
		font-weight: @font-weight-bold;
	}

	&__schema {
		color: @color-subtle;
		font-size: @font-size-small;
	}

	&__content {
		padding: @spacing-75;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		margin-bottom: @spacing-75;
		padding-bottom: @spacing-75;
		border-bottom: @border-subtle;
		column-gap: @spacing-150;

		&:last-child {
			border-bottom: none;
			margin-bottom: @spacing-0;
			padding-bottom: @spacing-0;
		}
	}

	&__property {
		flex: 0 0 40%;
		font-weight: @font-weight-bold;
		color: @color-emphasized;
	}

	&__value {
		flex: 0 1 60%;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
}

// TODO: This is a temporary fix until we implement Views.
@media ( min-width: @min-width-breakpoint-tablet ) {
	.ext-neowiki-view ~ h2,
	.ext-neowiki-view ~ h3,
	.ext-neowiki-view ~ h4,
	.ext-neowiki-view ~ h5,
	.ext-neowiki-view ~ h6,
	.ext-neowiki-view ~ .mw-heading {
		clear: both;
	}
}
</style>
