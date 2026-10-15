<template>
	<CdxTable
		class="ext-neowiki-schemas-table"
		:columns="columns"
		:data="schemas"
		:caption="$i18n( 'neowiki-special-schemas' ).text()"
		:hide-caption="true"
	>
		<template #item-name="{ row }">
			<a
				:id="nameId( row.name )"
				class="ext-neowiki-schemas-table__name"
				:href="pageUrl( `Schema:${ row.name }` )"
				:title="row.name"
			>{{ row.name }}</a>
		</template>

		<template #item-description="{ item }">
			<span class="ext-neowiki-schemas-table__description">{{ item }}</span>
		</template>

		<template #item-actions="{ row }">
			<div class="ext-neowiki-schemas-table__cell">
				<CdxButton
					v-if="canEdit"
					weight="quiet"
					:aria-label="$i18n( 'neowiki-edit-schema' ).text()"
					:aria-describedby="nameId( row.name )"
					:title="$i18n( 'neowiki-edit-schema' ).text()"
					@click="emit( 'edit', row.name )"
				>
					<CdxIcon :icon="cdxIconEdit" />
				</CdxButton>
				<SchemaActionsMenu
					:can-duplicate="canDuplicate"
					:can-delete="canDelete"
					:aria-describedby="nameId( row.name )"
					@duplicate="emit( 'duplicate', row.name )"
					@delete="emit( 'delete', row.name )"
				/>
			</div>
		</template>

		<template #item-subjects="{ row }">
			<div class="ext-neowiki-schemas-table__cell">
				<span
					v-if="subjectCountPending"
					class="ext-neowiki-schemas-table__subject-count"
				/>
				<a
					v-else
					class="ext-neowiki-schemas-table__subject-count"
					:href="pageUrl( `Special:Subjects/${ row.name }` )"
					:aria-describedby="nameId( row.name )"
				>{{ subjectListLinkText( subjectCountOf( row.name ) ) }}</a>
				<CdxButton
					v-if="canCreateSubject && !row.subjectsAreDependent"
					action="progressive"
					:aria-describedby="nameId( row.name )"
					@click="emit( 'create-subject', row.name )"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-schemas-create-subject' ).text() }}
				</CdxButton>
			</div>
		</template>
	</CdxTable>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxButton, CdxIcon, CdxTable, type TableColumn, useGeneratedId } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit } from '@wikimedia/codex-icons';
import { type SubjectCounts, subjectListLinkText } from '@/composables/useSubjectCounts.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import SchemaActionsMenu from './SchemaActionsMenu.vue';

const props = defineProps<{
	schemas: SchemaSummary[];
	canEdit: boolean;
	canDuplicate: boolean;
	canDelete: boolean;
	canCreateSubject: boolean;
	subjectCountOf: SubjectCounts['subjectCountOf'];
	subjectCountPending: boolean;
}>();

const emit = defineEmits<{
	edit: [ schemaName: string ];
	duplicate: [ schemaName: string ];
	delete: [ schemaName: string ];
	'create-subject': [ schemaName: string ];
}>();

const idPrefix = useGeneratedId( 'ext-neowiki-schemas-table' );

const columns = computed( (): TableColumn[] => [
	{ id: 'name', label: mw.msg( 'neowiki-schemas-column-name' ) },
	{ id: 'description', label: mw.msg( 'neowiki-schemas-column-description' ) },
	...( props.canEdit || props.canDuplicate || props.canDelete ?
		[ { id: 'actions', label: '' } ] :
		[] ),
	{ id: 'subjects', label: mw.msg( 'neowiki-schemas-column-subjects' ) }
] );

// Names the Schema to screen readers on its row's buttons and link. Encoded: an id holds no spaces, a name can.
function nameId( schemaName: string ): string {
	return `${ idPrefix }-${ encodeURIComponent( schemaName ) }`;
}

function pageUrl( page: string ): string {
	return mw.util.getUrl( page );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schemas-table {
	// Middle-aligned, so a row's text lines up with its taller buttons. Scoped under the table to outrank Codex's top
	// alignment, which MediaWiki loads after this.
	.cdx-table__table td {
		vertical-align: middle;
	}

	// Lets a row's menu open past the table, whose sideways scrolling would cut it off. Codex 2.3 and up, which
	// MediaWiki 1.45 ships, do so themselves.
	.cdx-table__table-wrapper,
	.cdx-menu-button__menu-wrapper {
		position: static;
	}

	&__cell {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		white-space: nowrap;
	}

	// Caps a long name, so its row still fits the page.
	&__name {
		display: inline-block;
		max-width: 12em;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		vertical-align: top;
	}

	// Holds a two-digit count's width while the counts load, so the column does not widen when they arrive. Sends the
	// button after it to the cell's end, where the buttons line up across the rows.
	&__subject-count {
		min-width: 5.5em;
		margin-inline-end: auto;
	}

	// One line per Schema: the description gives way, cut with an ellipsis.
	&__description {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		overflow: hidden;
		-webkit-hyphens: none;
		hyphens: none;
	}
}
</style>
