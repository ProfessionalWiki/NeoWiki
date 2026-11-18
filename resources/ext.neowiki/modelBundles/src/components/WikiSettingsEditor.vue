<template>
	<div class="ext-neowiki-wiki-settings">
		<h2 class="ext-neowiki-wiki-settings__title">
			{{ $i18n( 'neowiki-bundle-setup-settings' ).text() }}
		</h2>
		<p>
			{{ config.canEditSettings ?
				$i18n( 'neowiki-bundle-setup-settings-intro' ).text() :
				$i18n( 'neowiki-bundle-setup-settings-read-only' ).text() }}
		</p>

		<CdxField
			:is-fieldset="true"
			:disabled="!config.canEditSettings"
		>
			<template #label>
				{{ $i18n( 'neowiki-config-setting-subject-first' ).text() }}
			</template>
			<CdxRadio
				v-model="subjectFirst"
				:input-value="false"
				name="ext-neowiki-wiki-settings-type"
			>
				{{ $i18n( 'neowiki-config-setting-subject-first-page-first' ).text() }}
				<template #description>
					{{ $i18n( 'neowiki-config-setting-subject-first-page-first-help' ).text() }}
				</template>
			</CdxRadio>
			<CdxRadio
				v-model="subjectFirst"
				:input-value="true"
				name="ext-neowiki-wiki-settings-type"
			>
				{{ $i18n( 'neowiki-config-setting-subject-first-subject-first' ).text() }}
				<template #description>
					{{ $i18n( 'neowiki-config-setting-subject-first-subject-first-help' ).text() }}
				</template>
			</CdxRadio>
		</CdxField>

		<LanguageListInput
			v-model="dataLanguages"
			:label="$i18n( 'neowiki-config-setting-data-languages' ).text()"
			:disabled="!config.canEditSettings"
		>
			<template #description>
				{{ $i18n( 'neowiki-config-setting-data-languages-help' ).text() }}
			</template>
		</LanguageListInput>

		<CdxMessage
			v-if="failure !== null"
			type="error"
		>
			{{ failure }}
		</CdxMessage>

		<CdxButton
			class="ext-neowiki-wiki-settings__continue"
			action="progressive"
			weight="primary"
			:disabled="saving"
			@click="next"
		>
			{{ $i18n( 'neowiki-bundle-setup-continue' ).text() }}
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, type WritableComputedRef } from 'vue';
import { CdxButton, CdxField, CdxMessage, CdxRadio } from '@wikimedia/codex';
import { LanguageListInput, pageSource, type ConfigValues, type PageValues } from 'ext.neowiki';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import { setupConfig } from '../presentation/setupConfig.ts';

const emit = defineEmits<{
	done: [];
}>();

const config = setupConfig();
const pageSaver = ModelBundleServices.getPageSaver();
const values = ref<PageValues>( { ...config.settings.pageValues } );
const subjectFirst = valueInEffect( 'subjectFirst' );
const dataLanguages = valueInEffect( 'dataLanguages' );
const saving = ref( false );
const failure = ref<string | null>( null );

/**
 * As on MediaWiki:NeoWiki: the value the page sets, else the one LocalSettings.php gives. Changing it sets it on
 * the page, and what is not changed stays as it is.
 */
function valueInEffect<Key extends keyof ConfigValues>( key: Key ): WritableComputedRef<ConfigValues[ Key ]> {
	return computed( {
		get: () => ( values.value[ key ] ?? config.settings.localSettingsValues[ key ] ) as ConfigValues[ Key ],
		set: ( value: ConfigValues[ Key ] ): void => {
			values.value[ key ] = value;
		}
	} );
}

async function next(): Promise<void> {
	const source = pageSource( values.value );

	if ( config.canEditSettings && source !== pageSource( config.settings.pageValues ) ) {
		saving.value = true;
		failure.value = null;
		const status = await pageSaver.saveRevision(
			'MediaWiki:NeoWiki',
			source,
			mw.msg( 'neowiki-bundle-setup-settings-summary' ),
			'json',
			config.settings.revisionId
		);
		saving.value = false;

		if ( !status.success ) {
			failure.value = status.message;
			return;
		}
	}

	// What later steps read, and the frontend reads as the page loads.
	mw.config.set( 'wgNeoWikiSubjectFirst', subjectFirst.value );
	mw.config.set( 'wgNeoWikiDataLanguages', dataLanguages.value );
	emit( 'done' );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-wiki-settings {
	max-width: @size-4000;

	&__title {
		margin-top: 0;
	}

	&__continue {
		margin-top: @spacing-150;
	}
}
</style>
