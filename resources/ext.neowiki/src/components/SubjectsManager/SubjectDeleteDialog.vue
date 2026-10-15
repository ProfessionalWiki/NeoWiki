<template>
	<CdxDialog
		:open="open"
		class="ext-neowiki-ui"
		:title="$i18n( 'neowiki-managesubjects-delete-confirm-title' ).text()"
		:use-close-button="true"
		@update:open="emit( 'update:open', $event )"
	>
		<p>
			<I18nSlot message-key="neowiki-managesubjects-delete-confirm-message">
				<strong>{{ subjectName }}</strong>
			</I18nSlot>
		</p>
		<p v-if="hostRemoval !== null">
			{{ hostRemoval }}
		</p>
		<template #footer>
			<SummaryAction
				help-text=""
				:save-button-label="$i18n( 'neowiki-managesubjects-delete-confirm-button' ).text()"
				:save-disabled="false"
				save-button-action="destructive"
				:save-button-icon="cdxIconTrash"
				@save="emit( 'confirm', $event )"
			/>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxDialog } from '@wikimedia/codex';
import { cdxIconTrash } from '@wikimedia/codex-icons';
import I18nSlot from '@/components/common/I18nSlot.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import type { Subject } from '@/domain/Subject.ts';
import type { Schema } from '@/domain/Schema.ts';
import { isNamedAfterHostSubject } from '@/domain/dependentSubjects.ts';
import { displayNameOf, identifyingSubjectName } from '@/presentation/subjectDisplayName.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

const props = defineProps<{
	open: boolean;
	/** The Subject the confirmation asks about. */
	subject: Subject | null;
}>();

const emit = defineEmits<{
	'update:open': [ open: boolean ];
	/** Confirmed, carrying the edit summary the user typed. */
	confirm: [ summary: string ];
}>();

const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();

// So the user sees which Subject they are losing, of several that may share a name.
const subjectName = computed( (): string => props.subject === null ? '' : identifyingSubjectName(
	props.subject,
	schemaStore.schemas.get( props.subject.getSchemaName() ) as Schema | undefined
) );

// A Dependent Subject deleted on its own also goes from its Host Subject, and takes those inside it along.
const hostRemoval = computed( (): string | null => {
	const host = props.subject?.getHostSubject() ?? null;

	if ( props.subject === null || host === null ) {
		return null;
	}

	return mw.message(
		hostsDependentSubjects( props.subject ) ?
			'neowiki-managesubjects-delete-confirm-removed-from-host-with-dependents' :
			'neowiki-managesubjects-delete-confirm-removed-from-host',
		displayNameOf( host )
	).text();
} );

function hostsDependentSubjects( subject: Subject ): boolean {
	return [ ...subject.getStatements().getIdsOfReferencedSubjects() ].some( ( id ) => {
		const target = subjectStore.findSubject( id );

		return target !== undefined && isNamedAfterHostSubject( target );
	} );
}
</script>
