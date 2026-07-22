/**
 * Client-side "Test a User-Agent" evaluation and blocked-log bot detection.
 *
 * The engine (check_ua) walks the rules in order and stops at the FIRST rule
 * that yields a verdict. A rule yields a verdict when its UA substring matches
 * AND its qualification resolves. Only two qualifier forms are decidable in the
 * browser:
 *   - `*` (any)  — always resolves to the rule's action,
 *   - `!*`       — never resolves (a no-op),
 * everything else (country / HOST / FEED / IP / ASN / REF) is resolved
 * server-side, so the tester reports those matches as "depends on the request".
 *
 * The reported verdict is the first client-decidable rule's action; if a
 * server-dependent matching rule precedes it, `uncertain` is set because that
 * rule could preempt the decision on a real request.
 */

import { serializeRow } from './uaRules';
import { botsInUserAgent } from '../data/bots';

const QUAL_LABEL = {
	any: 'any country',
	country: 'country',
	host: 'verified host (reverse DNS)',
	feed: 'feed request',
	ip: 'IP / CIDR',
	asn: 'ASN',
	ref: 'referer',
	other: 'qualification',
};

/**
 * Does a row's UA substring match the given User-Agent? `*` matches anything.
 *
 * @param {object} row
 * @param {string} userAgent
 * @return {boolean}
 */
function uaMatches( row, userAgent ) {
	if ( row.ua === '*' ) {
		return true;
	}
	return !! row.ua && String( userAgent || '' ).includes( row.ua );
}

/**
 * Whether a matching row yields a verdict client-side:
 *   true  -> yields the rule's action,
 *   false -> never yields (a client-decidable no-op, e.g. `!*`),
 *   null  -> undecidable in the browser (server-side qualifier).
 *
 * @param {object} row
 * @return {boolean|null}
 */
function clientYield( row ) {
	if ( row.qualType === 'any' ) {
		return ! row.negate;
	}
	return null;
}

/**
 * Evaluate a User-Agent against the current rows.
 *
 * @param {string}        userAgent
 * @param {Array<object>} rows
 * @return {{
 *   ua:string,
 *   matches:Array<{index:number, rule:string, action:string, decided:boolean, note:string}>,
 *   verdict:'block'|'pass'|'none',
 *   ruleIndex:number,
 *   uncertain:boolean
 * }}
 */
export function evaluateUa( userAgent, rows ) {
	const ua = String( userAgent || '' );
	const matches = [];
	let verdict = 'none';
	let ruleIndex = -1;
	let uncertain = false;
	let decided = false;

	( rows || [] ).forEach( ( row, index ) => {
		if ( ! uaMatches( row, ua ) ) {
			return;
		}
		const yields = clientYield( row );
		const rule = serializeRow( row ) || `${ row.ua || '(blank)' }`;

		if ( yields === null ) {
			// Server-side qualifier: matched the UA, outcome resolves on the
			// real request. Could preempt a later decided rule.
			matches.push( {
				index,
				rule,
				action: row.action,
				decided: false,
				note: `depends on ${
					QUAL_LABEL[ row.qualType ] || 'the request'
				}`,
			} );
			if ( ! decided ) {
				uncertain = true;
			}
			return;
		}

		if ( yields === false ) {
			// A client-decidable no-op (e.g. negated any) — never fires.
			matches.push( {
				index,
				rule,
				action: row.action,
				decided: true,
				note: 'never matches',
			} );
			return;
		}

		// yields === true: this rule fires client-side.
		matches.push( {
			index,
			rule,
			action: row.action,
			decided: true,
			note: row.action === 'block' ? 'would block' : 'would allow',
		} );
		if ( ! decided ) {
			decided = true;
			verdict = row.action === 'block' ? 'block' : 'pass';
			ruleIndex = index;
		}
	} );

	return { ua, matches, verdict, ruleIndex, uncertain };
}

/**
 * Scan blocked-log entries for catalog bots, aggregated by token.
 *
 * @param {Array<{userAgent?:string}>} logs
 * @return {Array<{token:string, label:string, count:number, sampleUa:string}>}
 */
export function botCandidatesFromLogs( logs ) {
	const byToken = new Map();
	( logs || [] ).forEach( ( entry ) => {
		const ua = entry && entry.userAgent ? String( entry.userAgent ) : '';
		if ( ! ua ) {
			return;
		}
		botsInUserAgent( ua ).forEach( ( bot ) => {
			const current = byToken.get( bot.token );
			if ( current ) {
				current.count += 1;
			} else {
				byToken.set( bot.token, {
					token: bot.token,
					label: bot.label,
					count: 1,
					sampleUa: ua,
				} );
			}
		} );
	} );
	return [ ...byToken.values() ].sort( ( a, b ) => b.count - a.count );
}
