<!-- The Subjects the editor is holding, in the order they were opened. -->
<template>
	<ul
		ref="list"
		class="ext-neowiki-open-subject-list"
		role="listbox"
		:aria-label="$i18n( 'neowiki-subject-editor-open-subjects' ).text()"
		@keydown="onKeydown"
	>
		<EditorListItem
			v-for="subject in subjects"
			:key="idOf( subject )"
			:selected="isActive( subject )"
			:status="statusOf( subject )"
			:tabindex="isActive( subject ) ? 0 : -1"
			:data-mw-neowiki-subject-id="idOf( subject )"
			@click="select( subject )"
		>
			<span
				v-if="nameOf( subject ) !== null"
				class="ext-neowiki-open-subject-list__name"
			>{{ nameOf( subject ) }}</span>
			<span
				v-else
				class="ext-neowiki-open-subject-list__name ext-neowiki-open-subject-list__name--unlabeled"
			>{{ $i18n( 'neowiki-subject-no-label' ).text() }}</span>
			<template #description>
				<SchemaNameDisplay
					v-if="schemaNameToShow( subject ) !== null"
					class="ext-neowiki-open-subject-list__schema"
					:schema-name="subject.getSchemaName()"
					:display-name="subjectSchemaDisplayName( subject )"
					link="none"
				/>
			</template>
		</EditorListItem>
	</ul>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import EditorListItem from '@/components/common/EditorListItem.vue';
import { errorStatus, UNSAVED, type ItemStatus } from '@/components/common/ItemStatus.ts';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { schemaNameToShow, subjectSchemaDisplayName } from '@/presentation/schemaNameToShow.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';

const props = defineProps<{
	subjects: readonly Subject[];
	activeId: string;
	unsavedIds: readonly string[];
	// Subjects whose forms show something the save cannot get past. Each is unsaved too, which its mark says as well.
	blockingIds: readonly string[];
}>();

const emit = defineEmits<{
	select: [ SubjectId ];
}>();

const list = ref<HTMLElement | null>( null );

function idOf( subject: Subject ): string {
	return subject.getId().text;
}

// Null for a Subject nobody named, which other surfaces show under its id. Here the row says so in
// words instead, as the label field beside it does.
function nameOf( subject: Subject ): string | null {
	return subject.hasGeneratedDisplayName() && subject.getHostSubject() === null ?
		null :
		subjectDisplayName( subject );
}

function isActive( subject: Subject ): boolean {
	return idOf( subject ) === props.activeId;
}

function statusOf( subject: Subject ): ItemStatus | null {
	if ( props.blockingIds.includes( idOf( subject ) ) ) {
		return errorStatus( mw.msg( 'neowiki-subject-editor-save-blocked' ) );
	}

	return props.unsavedIds.includes( idOf( subject ) ) ? UNSAVED : null;
}

function select( subject: Subject ): void {
	emit( 'select', subject.getId() );
}

// The selection is the tab stop, so the focus goes with it and Shift+Tab comes back to the row
// the reader arrived at. Stopping at the ends rather than wrapping follows the schema editor's
// property list, the other list of its kind here.
function onKeydown( event: KeyboardEvent ): void {
	const offsets: Record<string, number> = { ArrowDown: 1, ArrowUp: -1 };
	const offset = offsets[ event.key ];

	if ( offset === undefined ) {
		return;
	}

	const index = props.subjects.findIndex( isActive ) + offset;
	const subject = props.subjects[ index ];

	// Covers both ends: a negative index reads as undefined too.
	if ( subject === undefined ) {
		return;
	}

	event.preventDefault();
	select( subject );
	optionAt( index )?.focus();
}

function optionAt( index: number ): HTMLElement | undefined {
	return list.value?.querySelectorAll<HTMLElement>( '[role="option"]' )[ index ];
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-open-subject-list {
	box-sizing: @box-sizing-base;
	margin: 0;
	padding: 0;
	list-style: none;

	&__name--unlabeled {
		color: @color-subtle;
	}

	&__schema {
		margin-block-start: @spacing-12;
	}
}
</style>
