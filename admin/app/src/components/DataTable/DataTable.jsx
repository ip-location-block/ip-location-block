/**
 * <DataTable> — bespoke, zero-dependency (beyond @wordpress/components) data
 * grid for the Beta admin. Client-side sort / search+highlight / pagination /
 * row-selection + bulk actions. Live update is the caller's concern (re-fetch
 * and pass fresh `rows`).
 *
 * Column: { key, header, sortable?, searchable?, priority?, render?(row, query) }
 */
/* eslint-disable no-nested-ternary */
import { useState, useMemo, useEffect, Fragment } from '@wordpress/element';
import {
	Button,
	SearchControl,
	SelectControl,
	CheckboxControl,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	sortRows,
	filterRows,
	paginate,
	pageCount,
	clampPage,
	highlightSegments,
	nextSort,
} from './logic';

const defaultGetRowId = ( row ) => row.id;

export default function DataTable( {
	columns,
	rows,
	getRowId = defaultGetRowId,
	selectable = false,
	bulkActions = [],
	bulkActionMode = 'buttons',
	searchable = true,
	searchKeys = null,
	initialQuery = '',
	query: controlledQuery,
	onQueryChange,
	renderRowDetails = null,
	pageSizes = [ 10, 25, 50, 100 ],
	initialPageSize = 25,
	loading = false,
	emptyMessage = __( 'No data available.', 'ip-location-block' ),
} ) {
	const [ internalQuery, setInternalQuery ] = useState( initialQuery );
	const [ sort, setSort ] = useState( { sortKey: null, sortDir: 'none' } );
	const [ page, setPage ] = useState( 1 );
	const [ pageSize, setPageSize ] = useState( initialPageSize );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ selectedAction, setSelectedAction ] = useState( '' );
	const [ expandedRowId, setExpandedRowId ] = useState( null );
	const query = controlledQuery ?? internalQuery;
	const setQuery = onQueryChange || setInternalQuery;

	const filtered = useMemo(
		() => filterRows( rows, query, searchKeys ),
		[ rows, query, searchKeys ]
	);
	const sorted = useMemo(
		() => sortRows( filtered, sort.sortKey, sort.sortDir ),
		[ filtered, sort ]
	);
	const total = sorted.length;
	const pages = pageCount( total, pageSize );
	const current = clampPage( page, total, pageSize );
	const pageRows = useMemo(
		() => paginate( sorted, current, pageSize ),
		[ sorted, current, pageSize ]
	);
	const colSpan =
		columns.length + ( selectable ? 1 : 0 ) + ( renderRowDetails ? 1 : 0 );

	useEffect( () => {
		if (
			expandedRowId !== null &&
			! pageRows.some( ( row ) => getRowId( row ) === expandedRowId )
		) {
			setExpandedRowId( null );
		}
	}, [ expandedRowId, getRowId, pageRows ] );

	const toggleRow = ( id ) =>
		setSelected( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );

	const allOnPageSelected =
		pageRows.length > 0 &&
		pageRows.every( ( r ) => selected.has( getRowId( r ) ) );

	const toggleAllOnPage = () =>
		setSelected( ( prev ) => {
			const next = new Set( prev );
			pageRows.forEach( ( r ) => {
				const id = getRowId( r );
				if ( allOnPageSelected ) {
					next.delete( id );
				} else {
					next.add( id );
				}
			} );
			return next;
		} );

	const onSort = ( key ) =>
		setSort( ( s ) => nextSort( s.sortKey, s.sortDir, key ) );

	const runBulkAction = ( action ) => {
		if ( ! action ) {
			return;
		}
		const result = action.onClick( [ ...selected ] );
		Promise.resolve( result )
			.then( () => {
				setSelected( new Set() );
				setSelectedAction( '' );
			} )
			.catch( () => {} );
	};

	const activeBulkAction =
		selectedAction === '' ? null : bulkActions[ Number( selectedAction ) ];

	const renderCell = ( col, row ) => {
		if ( col.render ) {
			return col.render( row, query );
		}
		const value = row[ col.key ];
		if ( searchable && query && col.searchable !== false ) {
			return highlightSegments( value, query ).map( ( seg, i ) =>
				seg.match ? (
					<mark key={ i }>{ seg.text }</mark>
				) : (
					<span key={ i }>{ seg.text }</span>
				)
			);
		}
		return value === null || value === undefined ? '' : String( value );
	};

	const from = total === 0 ? 0 : ( current - 1 ) * pageSize + 1;
	const to = Math.min( current * pageSize, total );

	return (
		<div className="ilb-datatable">
			<div className="ilb-datatable__toolbar">
				{ searchable && (
					<SearchControl
						__nextHasNoMarginBottom
						value={ query }
						onChange={ ( v ) => {
							setQuery( v );
							setPage( 1 );
						} }
						placeholder={ __( 'Search…', 'ip-location-block' ) }
					/>
				) }
				{ selectable && bulkActions.length > 0 && selected.size > 0 && (
					<div className="ilb-datatable__bulk">
						<span>
							{ sprintf(
								/* translators: %d: number of selected rows */
								__( '%d selected', 'ip-location-block' ),
								selected.size
							) }
						</span>
						{ bulkActionMode === 'select' ? (
							<>
								<SelectControl
									__nextHasNoMarginBottom
									label={ __(
										'Bulk action',
										'ip-location-block'
									) }
									hideLabelFromVision
									value={ selectedAction }
									options={ [
										{
											label: __(
												'Choose an action…',
												'ip-location-block'
											),
											value: '',
										},
										...bulkActions.map(
											( action, index ) => ( {
												label: action.label,
												value: String( index ),
											} )
										),
									] }
									onChange={ setSelectedAction }
								/>
								<Button
									variant="secondary"
									isDestructive={
										activeBulkAction?.destructive
									}
									disabled={ ! activeBulkAction }
									onClick={ () =>
										runBulkAction( activeBulkAction )
									}
								>
									{ __( 'Apply', 'ip-location-block' ) }
								</Button>
							</>
						) : (
							bulkActions.map( ( action ) => (
								<Button
									key={ action.label }
									variant="secondary"
									isDestructive={ action.destructive }
									onClick={ () => runBulkAction( action ) }
								>
									{ action.label }
								</Button>
							) )
						) }
					</div>
				) }
			</div>

			<div className="ilb-datatable__table-wrap">
				<table className="wp-list-table widefat fixed striped ilb-datatable__table">
					<thead>
						<tr>
							{ selectable && (
								<td className="manage-column check-column">
									<CheckboxControl
										__nextHasNoMarginBottom
										checked={ allOnPageSelected }
										onChange={ toggleAllOnPage }
										aria-label={ __(
											'Select all rows',
											'ip-location-block'
										) }
									/>
								</td>
							) }
							{ renderRowDetails && (
								<th
									scope="col"
									className="ilb-datatable__expand-column"
								>
									<span className="screen-reader-text">
										{ __( 'Details', 'ip-location-block' ) }
									</span>
								</th>
							) }
							{ columns.map( ( col ) => {
								const active = sort.sortKey === col.key;
								return (
									<th
										key={ col.key }
										scope="col"
										className={
											col.priority
												? `ilb-col-${ col.key } ilb-prio-${ col.priority }`
												: `ilb-col-${ col.key }`
										}
										aria-sort={
											active
												? sort.sortDir === 'asc'
													? 'ascending'
													: 'descending'
												: 'none'
										}
									>
										{ col.sortable !== false ? (
											<button
												type="button"
												className="ilb-datatable__sort"
												onClick={ () =>
													onSort( col.key )
												}
											>
												{ col.header }
												<span
													className="ilb-datatable__sort-ind"
													aria-hidden="true"
												>
													{ active
														? sort.sortDir === 'asc'
															? ' ▲'
															: ' ▼'
														: '' }
												</span>
											</button>
										) : (
											col.header
										) }
									</th>
								);
							} ) }
						</tr>
					</thead>
					<tbody>
						{ loading ? (
							<tr>
								<td
									colSpan={ colSpan }
									className="ilb-datatable__empty"
								>
									<Spinner />
								</td>
							</tr>
						) : pageRows.length === 0 ? (
							<tr>
								<td
									colSpan={ colSpan }
									className="ilb-datatable__empty"
								>
									{ emptyMessage }
								</td>
							</tr>
						) : (
							pageRows.map( ( row ) => {
								const id = getRowId( row );
								const expanded = expandedRowId === id;
								const detailId = `ilb-row-details-${ String(
									id
								).replace( /[^a-zA-Z0-9_-]/g, '-' ) }`;
								return (
									<Fragment key={ id }>
										<tr
											className={
												selected.has( id )
													? 'is-selected'
													: undefined
											}
										>
											{ selectable && (
												<th
													scope="row"
													className="check-column"
												>
													<CheckboxControl
														__nextHasNoMarginBottom
														checked={ selected.has(
															id
														) }
														onChange={ () =>
															toggleRow( id )
														}
														aria-label={ __(
															'Select row',
															'ip-location-block'
														) }
													/>
												</th>
											) }
											{ renderRowDetails && (
												<td className="ilb-datatable__expand-cell">
													<button
														type="button"
														className="ilb-datatable__expand"
														aria-expanded={
															expanded
														}
														aria-controls={
															detailId
														}
														onClick={ () =>
															setExpandedRowId(
																expanded
																	? null
																	: id
															)
														}
													>
														<span aria-hidden="true">
															{ expanded
																? '−'
																: '+' }
														</span>
														<span className="screen-reader-text">
															{ expanded
																? __(
																		'Hide details',
																		'ip-location-block'
																  )
																: __(
																		'Show details',
																		'ip-location-block'
																  ) }
														</span>
													</button>
												</td>
											) }
											{ columns.map( ( col ) => (
												<td
													key={ col.key }
													className={
														col.priority
															? `ilb-col-${ col.key } ilb-prio-${ col.priority }`
															: `ilb-col-${ col.key }`
													}
												>
													{ renderCell( col, row ) }
												</td>
											) ) }
										</tr>
										{ expanded && (
											<tr
												id={ detailId }
												className="ilb-datatable__details-row"
											>
												<td colSpan={ colSpan }>
													{ renderRowDetails( row ) }
												</td>
											</tr>
										) }
									</Fragment>
								);
							} )
						) }
					</tbody>
				</table>
			</div>

			<div className="ilb-datatable__footer">
				<span className="ilb-datatable__count">
					{ sprintf(
						/* translators: 1: first row, 2: last row, 3: total rows */
						__( '%1$d–%2$d of %3$d', 'ip-location-block' ),
						from,
						to,
						total
					) }
				</span>
				<div className="ilb-datatable__page-size">
					<span>{ __( 'Rows per page', 'ip-location-block' ) }</span>
					<SelectControl
						__nextHasNoMarginBottom
						className="ilb-datatable__pagesize"
						label={ __( 'Rows per page', 'ip-location-block' ) }
						hideLabelFromVision
						value={ String( pageSize ) }
						options={ pageSizes.map( ( n ) => ( {
							label: String( n ),
							value: String( n ),
						} ) ) }
						onChange={ ( v ) => {
							setPageSize( Number( v ) );
							setPage( 1 );
						} }
					/>
				</div>
				<div className="ilb-datatable__pager">
					<Button
						variant="secondary"
						disabled={ current <= 1 }
						onClick={ () => setPage( current - 1 ) }
					>
						{ __( 'Previous', 'ip-location-block' ) }
					</Button>
					<span className="ilb-datatable__pageno">
						{ sprintf(
							/* translators: 1: current page, 2: total pages */
							__( 'Page %1$d of %2$d', 'ip-location-block' ),
							current,
							pages
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ current >= pages }
						onClick={ () => setPage( current + 1 ) }
					>
						{ __( 'Next', 'ip-location-block' ) }
					</Button>
				</div>
			</div>
		</div>
	);
}
