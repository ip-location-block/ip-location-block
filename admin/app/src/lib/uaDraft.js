/**
 * Local draft reconciliation for the bot-rule builder.
 *
 * Mirrors lib/preciseDraft.js. serializeUaList intentionally drops rows that
 * are blank or have a value-required qualifier left empty, so a freshly-added,
 * still-incomplete row can never survive a round-trip through the stored string.
 * The editor keeps its own draft (which DOES hold in-progress rows) and only
 * rebuilds it from storage when the stored string changes from OUTSIDE the
 * editor (reset, preset apply, migration, save + reload).
 */

import { parseUaList } from './uaRules';

/**
 * Reconcile the editor's draft rows against the stored `ua_list` string.
 *
 * @param {Array<object>} prevDraft         current draft rows
 * @param {string}        storedString      the stored ua_list string
 * @param {string}        lastWrittenString the string the editor last serialized
 * @return {Array<object>} the next draft rows
 */
export function syncUaDraft( prevDraft, storedString, lastWrittenString ) {
	if ( storedString === lastWrittenString ) {
		return prevDraft;
	}
	return parseUaList( storedString );
}
