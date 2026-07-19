/**
 * Local draft reconciliation for the Simple-mode precise-rule editor.
 *
 * The precise-rule list is serialized into the same country-rule string the rest
 * of the Simple view edits (see lib/rules.js). `serializeRules` intentionally
 * drops rows with an empty `value`, so a freshly-added — still blank — row can
 * never survive a round-trip through the stored string. The editor therefore
 * keeps its own draft (which DOES hold empty, in-progress rows) and only rebuilds
 * it from storage when the stored string changes from OUTSIDE the editor.
 *
 * This is the pure core of that reconciliation; SimpleBlocking wires it to
 * component state + a "last written" ref.
 */

import { parseRules } from './rules';

/**
 * Reconcile the editor's draft rows against the stored rule string.
 *
 * - When the stored string is exactly what the editor last serialized, the
 *   change originated here: keep the draft so empty/in-progress rows survive the
 *   render that immediately drops them from the stored string.
 * - Otherwise the settings changed from outside (reset, preset, save + reload):
 *   rebuild the draft from storage.
 *
 * @param {Array<{country:string, level:string, value:string}>} prevDraft         current draft rows
 * @param {string}                                              storedString      the stored rule string (pub.white_list / black_list)
 * @param {string}                                              lastWrittenString the string the editor last serialized into storage
 * @return {Array<{country:string, level:string, value:string}>} the next draft rows
 */
export function syncDraft( prevDraft, storedString, lastWrittenString ) {
	if ( storedString === lastWrittenString ) {
		return prevDraft;
	}
	return parseRules( storedString ).precise;
}
