/**
 * Zero-dependency, theme-aware charts (HTML bars) for the Statistics tab.
 * Palette + surfaces from the dataviz reference (validated categorical set:
 * blue/green/magenta/yellow/aqua). CSS custom properties (light/dark) live in
 * style.scss under .ilb-viz.
 */
import { __ } from '@wordpress/i18n';
import './charts.scss';

export const HOOKS = [
	{ key: 'comment', label: __( 'Comment', 'ip-location-block' ), color: 'var(--ilb-s1)' },
	{ key: 'xmlrpc', label: __( 'XML-RPC', 'ip-location-block' ), color: 'var(--ilb-s2)' },
	{ key: 'login', label: __( 'Login', 'ip-location-block' ), color: 'var(--ilb-s3)' },
	{ key: 'admin', label: __( 'Admin', 'ip-location-block' ), color: 'var(--ilb-s4)' },
	{ key: 'public', label: __( 'Public', 'ip-location-block' ), color: 'var(--ilb-s5)' },
];

export function CountryBars( { data, limit = 15 } ) {
	if ( ! data || ! data.length ) {
		return <p className="ilb-viz-empty">{ __( 'No country data yet.', 'ip-location-block' ) }</p>;
	}
	const rows = data.slice( 0, limit );
	const max = Math.max( 1, ...rows.map( ( d ) => d.count ) );
	return (
		<div className="ilb-viz ilb-bars">
			{ rows.map( ( d ) => (
				<div className="ilb-bars__row" key={ d.code || '??' }>
					<span className="ilb-bars__label">{ d.code || '??' }</span>
					<span className="ilb-bars__track">
						<span
							className="ilb-bars__fill"
							style={ { width: `${ ( d.count / max ) * 100 }%` } }
							title={ `${ d.code }: ${ d.count }` }
						/>
					</span>
					<span className="ilb-bars__value">{ d.count }</span>
				</div>
			) ) }
		</div>
	);
}

export function DailyStacked( { data, limit = 30 } ) {
	if ( ! data || ! data.length ) {
		return <p className="ilb-viz-empty">{ __( 'No daily data yet.', 'ip-location-block' ) }</p>;
	}
	const rows = data.slice( -limit );
	const max = Math.max( 1, ...rows.map( ( d ) => d.total ) );
	return (
		<div className="ilb-viz ilb-daily">
			<div className="ilb-legend">
				{ HOOKS.map( ( h ) => (
					<span className="ilb-legend__item" key={ h.key }>
						<span className="ilb-legend__swatch" style={ { background: h.color } } />
						{ h.label }
					</span>
				) ) }
			</div>
			<div className="ilb-daily__rows">
				{ rows.map( ( d ) => (
					<div className="ilb-daily__row" key={ d.date }>
						<span className="ilb-daily__date">
							{ new Date( d.date * 1000 ).toLocaleDateString( undefined, {
								month: 'short',
								day: 'numeric',
							} ) }
						</span>
						<span className="ilb-daily__track">
							{ HOOKS.filter( ( h ) => d[ h.key ] > 0 ).map( ( h ) => (
								<span
									key={ h.key }
									className="ilb-daily__seg"
									title={ `${ h.label }: ${ d[ h.key ] }` }
									style={ { width: `${ ( d[ h.key ] / max ) * 100 }%`, background: h.color } }
								/>
							) ) }
						</span>
						<span className="ilb-daily__value">{ d.total }</span>
					</div>
				) ) }
			</div>
		</div>
	);
}
