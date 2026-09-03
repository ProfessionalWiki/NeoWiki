<template>
	<CdxDialog
		:open="props.open"
		class="ext-neowiki-ui ext-neowiki-move-subject-dialog"
		:title="$i18n( 'neowiki-managesubjects-move-title' ).text()"
		:use-close-button="true"
		@update:open="onOpenChange"
	>
		<CdxField
			:status="fieldStatus"
			:messages="fieldMessages"
		>
			<template #label>
				{{ $i18n( 'neowiki-managesubjects-move-target-label' ).text() }}
			</template>

			<PagePicker
				:excluded-page-id="props.currentPageId"
				:aria-label="$i18n( 'neowiki-managesubjects-move-target-label' ).text()"
				@update:selected="onTargetSelected"
			/>
		</CdxField>

		<p
			v-if="targetIsNewPage"
			class="ext-neowiki-move-subject-dialog__note"
		>
			<I18nSlot message-key="neowiki-managesubjects-move-creates-page">
				<strong>{{ target?.title }}</strong>
			</I18nSlot>
		</p>

		<CdxCheckbox
			v-model="makeMainSubject"
			:disabled="target === null"
		>
			{{ $i18n( 'neowiki-managesubjects-move-make-main' ).text() }}
		</CdxCheckbox>

		<p
			v-if="demotedMainSubjectName !== null && makeMainSubject"
			class="ext-neowiki-move-subject-dialog__consequence"
		>
			<I18nSlot message-key="neowiki-managesubjects-move-demotes">
				<strong>{{ demotedMainSubjectName }}</strong>
			</I18nSlot>
		</p>

		<CdxMessage
			v-if="props.subjectIsMainSubject"
			type="warning"
			inline
			class="ext-neowiki-move-subject-dialog__warning"
		>
			<I18nSlot message-key="neowiki-managesubjects-move-source-loses-main">
				{{ props.currentPageTitle }}
			</I18nSlot>
		</CdxMessage>

		<template #footer>
			<SummaryAction
				help-text=""
				:save-button-label="$i18n( 'neowiki-managesubjects-move-confirm-button' ).text()"
				:save-disabled="target === null || moving"
				:save-button-icon="cdxIconMove"
				@save="onMove"
			/>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CdxCheckbox, CdxDialog, CdxField, CdxMessage } from '@wikimedia/codex';
import type { ValidationStatusType } from '@wikimedia/codex';
import { cdxIconMove } from '@wikimedia/codex-icons';
import PagePicker from '@/components/common/PagePicker.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';
import type { PageChoice } from '@/components/common/PageChoice.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

const props = defineProps<{
	open: boolean;
	subjectId: string;
	subjectName: string;
	currentPageId: number;
	currentPageTitle: string;
	subjectIsMainSubject: boolean;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'moved': [ targetTitle: string ];
}>();

const subjectStore = useSubjectStore();

const target = ref<PageChoice | null>( null );
const makeMainSubject = ref( false );
const demotedMainSubjectName = ref<string | null>( null );
const errorMessage = ref<string | null>( null );
const moving = ref( false );

const targetIsNewPage = computed( (): boolean => target.value !== null && target.value.pageId === null );

const fieldStatus = computed( (): ValidationStatusType =>
	errorMessage.value === null ? 'default' : 'error' );

const fieldMessages = computed( (): Record<string, string> =>
	errorMessage.value === null ? {} : { error: errorMessage.value } );

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		target.value = null;
		makeMainSubject.value = false;
		demotedMainSubjectName.value = null;
		errorMessage.value = null;
		moving.value = false;
	}
} );

async function onTargetSelected( choice: PageChoice | null ): Promise<void> {
	target.value = choice;
	errorMessage.value = null;
	demotedMainSubjectName.value = null;

	if ( choice === null || choice.pageId === null ) {
		return;
	}

	// Read who would be demoted, so promoting states its own consequence rather than springing it
	// on a page the user is not looking at. Read straight from the repository, not through the
	// store: the store's pageSubjects holds the page being managed, and loading another page's
	// listing into it would replace that.
	try {
		const { pageSubjects } = await NeoWikiExtension.getInstance()
			.getSubjectRepository().getPageSubjects( choice.pageId );
		const mainSubjectId = pageSubjects.getMainSubjectId();
		const mainSubject = mainSubjectId === null ? undefined : pageSubjects.getSubject( mainSubjectId );

		// Ignored if the user has moved on to another target while this was out.
		if ( target.value?.pageId === choice.pageId ) {
			demotedMainSubjectName.value = mainSubject?.getDisplayName() ?? null;
		}
	} catch ( error ) {
		// Only the warning is lost, and the server still demotes correctly, so the move stays
		// available rather than being blocked on a failed read.
		console.error( 'Failed to read the target page\'s main subject:', error );
	}
}

async function onMove( summary: string ): Promise<void> {
	if ( target.value === null || moving.value ) {
		return;
	}

	// Taken once, up front: the picker stays live while the request is out, so a keystroke in it
	// would otherwise null this ref out from under the awaits below and turn a move that committed
	// into an error.
	const chosen = target.value;

	moving.value = true;
	errorMessage.value = null;

	try {
		let targetPageId = chosen.pageId;

		if ( targetPageId === null ) {
			targetPageId = await createTargetPage( chosen.title, summary );
			// Recorded on the choice as well: if the move then fails, a retry has to move onto the
			// page just created rather than try to create it a second time.
			target.value = { pageId: targetPageId, title: chosen.title };
		}

		await subjectStore.moveSubject(
			new SubjectId( props.subjectId ),
			targetPageId,
			makeMainSubject.value,
			summary || undefined
		);

		emit( 'moved', chosen.title );
		emit( 'update:open', false );
	} catch ( error ) {
		errorMessage.value = messageFor( error );
	} finally {
		moving.value = false;
	}
}

/**
 * The page is created through MediaWiki's own API rather than by the move, which has no page
 * creation of its own and would bypass the createpage right if it did. It is created only once the
 * user confirms, so an abandoned dialog leaves no empty page behind.
 */
async function createTargetPage( title: string, summary: string ): Promise<number> {
	let response;

	try {
		response = await new mw.Api().create(
			title,
			{ summary: summary || mw.msg( 'neowiki-managesubjects-move-create-page-summary-default' ) },
			''
		);
	} catch ( error ) {
		// Everything that goes wrong here is about the page - an invalid title, a namespace the user
		// may not create in, a filter - so it is reported as such rather than as a failed move. The
		// title already being taken has its own message.
		if ( codeOf( error ) === 'articleexists' ) {
			throw error;
		}

		throw new Error( mw.msg( 'neowiki-managesubjects-move-create-page-error', title ) );
	}

	if ( response.result !== 'Success' ) {
		throw new Error( mw.msg( 'neowiki-managesubjects-move-create-page-error', title ) );
	}

	return response.pageid;
}

function codeOf( error: unknown ): string | undefined {
	return typeof error === 'string' ? error : ( error as { code?: string } )?.code;
}

function messageFor( error: unknown ): string {
	if ( codeOf( error ) === 'articleexists' ) {
		return mw.msg( 'neowiki-managesubjects-move-page-taken' );
	}

	if ( error instanceof Error ) {
		return error.message;
	}

	return mw.msg( 'neowiki-managesubjects-move-error', props.subjectName );
}

function onOpenChange( isOpen: boolean ): void {
	emit( 'update:open', isOpen );
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-move-subject-dialog {
	/* Set off from the target field above it, whether or not the new-page note sits between them:
		the note's own bottom margin collapses into this one. */
	.cdx-checkbox {
		margin-top: @spacing-75;
	}

	&__note {
		margin: @spacing-25 0 @spacing-50;
		color: @color-subtle;
	}

	&__consequence {
		margin: @spacing-25 0 0 @spacing-150;
		color: @color-subtle;
	}

	&__warning {
		margin-top: @spacing-100;
	}
}
</style>
