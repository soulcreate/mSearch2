<?php
/** @var array $scriptProperties */
/** @var mSearch2 $mSearch2 */
if (!$modx->loadClass('msearch2', MODX_CORE_PATH . 'components/msearch2/model/msearch2/', false, true)) {return false;}
$mSearch2 = new mSearch2($modx, $scriptProperties);
$mSearch2->initialize($modx->context->key);
/** @var pdoFetch $pdoFetch */
if (!$modx->loadClass('pdofetch', MODX_CORE_PATH . 'components/pdotools/model/pdotools/', false, true)) {return false;}
$pdoFetch = new pdoFetch($modx, $scriptProperties);
$pdoFetch->addTime('pdoTools loaded.');
$_SESSION['mFilter2'][$modx->resource->id] = array();

if (empty($queryVar)) {$queryVar = 'query';}
if (empty($parentsVar)) {$parentsVar = 'parents';}
if (empty($minQuery)) {$minQuery = $modx->getOption('index_min_words_length', null, 3, true);}
if ($depth == '') {$depth = 10;}
if (empty($classActive)) {$classActive = 'active';}
if (isset($scriptProperties['disableSuggestions'])) {$scriptProperties['suggestions'] = empty($scriptProperties['disableSuggestions']);}
if (empty($toPlaceholders) && !empty($toPlaceholder)) {$toPlaceholders = $toPlaceholder;}
if (!empty($_REQUEST['limit'])) {$limit = (integer) $_REQUEST['limit'];}
elseif ($limit == '') {$limit = 10;}

//$maxLimit = 100;
//if ($limit > $maxLimit) {$limit = $maxLimit;}

$fastMode = !empty($fastMode);

$class = 'modResource';
$output = array('filters' => '', 'results' => '', 'total' => 0, 'limit' => $limit);
$ids = $found = $log = $where = array();

// ---------------------- Retrieving ids of resources for filter
$query = isset($_REQUEST[$queryVar]) ? $_REQUEST[$queryVar] : '';
// Filter by ids
if (!empty($resources)) {
	$resources = array_map('trim', explode(',', $resources));
	$in = $out = array();
	foreach ($resources as $v) {
		if (!is_numeric($v)) {continue;}
		if ($v < 0) {$out[] = abs($v);}
		else {$in[] = $v;}
	}
	if (!empty($in)) {$ids = $where['id:IN'] = $in;}
	if (!empty($out)) {$where['id:NOT IN'] = $out;}
	$pdoFetch->addTime('Recieved ids for include: "'.implode(',',$in).'" and exclude: "'.implode(',', $out).'"');
}
else if (isset($_REQUEST[$queryVar]) && empty($query)) {
	$output['results'] =  $modx->lexicon('mse2_err_no_query');
}
else if (isset($_REQUEST[$queryVar]) && !preg_match('/^[0-9]{2,}$/', $query) && mb_strlen($query,'UTF-8') < $minQuery) {
	$output['results'] = $modx->lexicon('mse2_err_min_query');
}
else if (isset($_REQUEST[$queryVar])) {
	$query = htmlspecialchars(strip_tags(trim($query)));
	$modx->setPlaceholder('mse2_'.$queryVar, $query);

	$found = $mSearch2->Search($query);
	$ids = array_keys($found);

	if (empty($ids)) {
		$output['results'] = $modx->lexicon('mse2_err_no_results');
	}
	$pdoFetch->addTime('Found ids: "'.implode(',',$ids).'"');
}

// Has error message - exit
if (!empty($output['results'])) {
	if (!empty($toSeparatePlaceholders)) {
		$modx->setPlaceholders($output, $toSeparatePlaceholders);
		return;
	}
	elseif (!empty($toPlaceholders)) {
		$modx->setPlaceholders($output, $toPlaceholders);
		return;
	}
	else {
		return $output;
	}
}

// Filter ids by parents
if (empty($scriptProperties[$parentsVar]) && !empty($_REQUEST[$parentsVar])) {$parents = $_REQUEST[$parentsVar];}
else {$parents = $scriptProperties[$parentsVar];}
if (!empty($parents)) {
	$pids = array();
	$parents = array_map('trim', explode(',', $parents));
	$parents_in = $parents_out = array();
	foreach ($parents as $v) {
		if (!is_numeric($v)) {continue;}
		if ($v < 0) {$parents_out[] = abs($v);}
		else {$parents_in[] = $v;}
	}
	$q = $modx->newQuery($class, array('id:IN' => array_merge($parents_in, $parents_out)));
	$q->select('id,context_key');
	if ($q->prepare() && $q->stmt->execute()) {
		while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
			$pids[$row['id']] = $row['context_key'];
		}
	}
	if (!empty($depth) && $depth > 0) {
		foreach ($pids as $k => $v) {
			if (in_array($k, $parents_in)) {
				$parents_in = array_merge($parents_in, $modx->getChildIds($k, $depth, array('context' => $v)));
			}
			else {
				$parents_out = array_merge($parents_out, $modx->getChildIds($k, $depth, array('context' => $v)));
			}
		}
	}

	// Support for ms2 multi categories
	$members = array();
	if ($mSearch2->checkMS2() && (!empty($parents_in) || !empty($parents_out))) {
		$q = $modx->newQuery('msCategoryMember');
		if (!empty($parents_in)) {$q->where(array('parent:IN' => $parents_in));}
		if (!empty($parents_out)) {$q->where(array('parent:NOT IN' => $parents_out));}
		$q->select('product_id');
		if ($q->prepare() && $q->stmt->execute()) {
			$members = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
		}
	}

	if (!empty($members) && !empty($parents_in)) {
		$where[] = '(`'.$class.'`.`parent` IN ('.implode(',',$parents_in).') OR `'.$class.'`.`id` IN ('.implode(',',$members).'))';
	}
	elseif (!empty($parents_in)) {
		$where['parent:IN'] = $parents_in;
	}
	elseif (!empty($members)) {
		$where['id:IN'] = $members;
	}
	if (!empty($parents_out)) {
		$where['parent:NOT IN'] = $parents_out;
	}

	$pdoFetch->addTime('Received parents for include "'.implode(',',$parents_in).'" and exclude: "'.implode(',', $parents_out).'"');
}

// ---------------------- Checking resources by status and custom "where" parameter
if (!empty($where)) {
	if (!empty($ids) && !empty($where['id:IN'])) {
		$where['id:IN'] = array_merge($ids, $where['id:IN']);
	}
	elseif(!empty($ids)) {
		$where['id:IN'] = $ids;
	}
	if (empty($showUnpublished)) {$where['published'] = 1;}
	if (empty($showHidden)) {$where['hidemenu'] = 0;}
	if (empty($showDeleted)) {$where['deleted'] = 0;}
	if (!empty($hideContainers)) {$where['isfolder'] = 0;}
	if (!empty($scriptProperties['where'])) {
		$tmp = $modx->fromJSON($scriptProperties['where']);
		if (!empty($tmp) && is_array($tmp)) {
			$where = array_merge($where, $tmp);
		}
	}
	unset($scriptProperties['where']);
	$q = $modx->newQuery($class, $where);
	$q->select('id');
	if ($q->prepare() && $q->stmt->execute()) {
		$tmp = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
		$ids = !empty($ids) ? array_intersect($ids, $tmp) : $tmp;
		$pdoFetch->addTime('Fetched ids for building filters: "'.implode(',',$ids).'"');
	}
}

// ---------------------- Nothing to filter, exit
if (empty($ids)) {
	if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
		$log = '<pre class="mFilterLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
	}
	else {$log = '';}

	$output = array_merge($output, array(
		'filters' => $modx->lexicon('mse2_err_no_filters')
		,'results' => $modx->lexicon('mse2_err_no_results')
	));
	if (!empty($toPlaceholders)) {
		$output['log'] = $log;
		$modx->setPlaceholders($output, $toPlaceholders);
		return;
	}
	else {
		$output = $pdoFetch->getChunk($scriptProperties['tplOuter'], $output, $fastMode);
		$output .= $log;

		return $output;
	}
}

// ---------------------- Checking for suggestions processing
// Checking by results count
if (!empty($scriptProperties['suggestionsMaxResults']) && count($ids) > $scriptProperties['suggestionsMaxResults']) {
	$scriptProperties['suggestions'] = false;
	$pdoFetch->addTime('Suggestions disabled by "suggestionsMaxResults" parameter: results count is '.count($ids).', max allowed is '.$scriptProperties['suggestionsMaxResults']);
}
else {
	$pdoFetch->addTime('Total number of results: '.count($ids));
}

// Then get filters
$pdoFetch->addTime('Getting filters for ids: "'.implode(',',$ids).'"');
$filters = '';
if (!empty($ids)) {
	$filters = $mSearch2->getFilters($ids);
	// And checking by filters count
	if (!empty($filters) && $scriptProperties['suggestions']) {
		$count = 0;
		foreach ($filters as $tmp) {
			$count += count(array_values($tmp));
		}
		if (!empty($scriptProperties['suggestionsMaxFilters']) && $count > $scriptProperties['suggestionsMaxFilters']) {
			$scriptProperties['suggestions'] = false;
			$pdoFetch->addTime('Suggestions disabled by "suggestionsMaxFilters" parameter: filters count is '.$count.', max allowed is '.$scriptProperties['suggestionsMaxFilters']);
		}
		else {
			$pdoFetch->addTime('Total number of filters: '.$count);
		}
	}
}


// ---------------------- Loading results
$start_sort = implode(',', array_map('trim' , explode(',', $scriptProperties['sort'])));
$start_limit = $limit;
$suggestions = array();
$page = $sort = '';
if (!empty($ids)) {
	/* @var modSnippet $paginator */
	if ($paginator = $modx->getObject('modSnippet', array('name' => $scriptProperties['paginator']))) {
		$paginatorProperties = array_merge(
			$paginator->getProperties()
			,$scriptProperties
			,array(
				'resources' => implode(',',$ids)
				,'parents' => '0'
				,'element' => $scriptProperties['element']
				,'defaultSort' => $start_sort
				,'toPlaceholder' => false
				,'limit' => $limit
			)
		);

		// Switching chunk for rows, if specified
		if (!empty($scriptProperties['tpls'])) {
			$tmp = isset($_REQUEST['tpl']) ? (integer) $_REQUEST['tpl'] : 0;
			$tpls = array_map('trim', explode(',', $scriptProperties['tpls']));
			$paginatorProperties['tpls'] = $tpls;
			if (isset($tpls[$tmp])) {
				$paginatorProperties['tpl'] = $tpls[$tmp];
				$paginatorProperties['tpl_idx'] = $tmp;
			}
		}

		// Trying to save weight of found ids if using mSearch2
		$weight = false;
		if (!empty($found) && strtolower($paginatorProperties['element']) == 'msearch2') {
			$tmp = array();
			foreach ($ids as $v) {$tmp[$v] = isset($found[$v]) ? $found[$v] : 0;}
			$paginatorProperties['resources'] = $modx->toJSON($tmp);
			$weight = true;
		}

		if (!empty($_REQUEST['sort'])) {$sort = $_REQUEST['sort'];}
		else if (!empty($start_sort)) {$sort = $start_sort;}
		else {
			$sortby = !empty($scriptProperties['sortby']) ? $scriptProperties['sortby'] : '';
			if (!empty($sortby)) {
				$sortdir = !empty($scriptProperties['sortdir']) ? $scriptProperties['sortdir'] : 'asc';
				$sort = $sortby.$mSearch2->config['method_delimeter'].$sortdir;
			}
		}
		if (!empty($_REQUEST[$paginatorProperties['pageVarKey']])) {
			$page = (int) $_REQUEST[$paginatorProperties['pageVarKey']];
		}
		if (!empty($sort)) {
			$paginatorProperties['sortby'] = $mSearch2->getSortFields($sort);
			$paginatorProperties['sortdir'] = '';
		}

		$_SESSION['mFilter2'][$modx->resource->id]['paginatorProperties'] = $paginatorProperties;

		// We have a delimeters in $_GET, so need to filter resources
		if (strpos(implode(array_keys($_GET)), $mSearch2->config['filter_delimeter']) !== false) {
			$matched = $mSearch2->Filter($ids, $_REQUEST);
			$matched = array_intersect($ids, $matched);
			if ($scriptProperties['suggestions']) {
				$suggestions = $mSearch2->getSuggestions($ids, $_REQUEST, $matched);
				$pdoFetch->addTime('Suggestions retrieved.');
			}
			// Trying to save weight of found ids again
			if ($weight) {
				$tmp = array();
				foreach ($matched as $v) {$tmp[$v] = isset($found[$v]) ? $found[$v] : 0;}
				$paginatorProperties['resources'] = $modx->toJSON($tmp);
			}
			else {
				$paginatorProperties['resources'] = implode(',', $matched);
			}
		}
		$paginator->setProperties($paginatorProperties);
		$paginator->setCacheable(false);

		// Saving log
		$log = $pdoFetch->timings;
		$pdoFetch->timings = array();

		$output['results'] = !empty($paginatorProperties['resources'])
			? $paginator->process()
			: $modx->lexicon('mse2_err_no_results');
		$output['total'] = $modx->getPlaceholder($pdoFetch->config['totalVar']);
	}
	else {
		$modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Could not find pagination snippet with name: "'.$scriptProperties['paginator'].'"');
	}
}

// ----------------------  Loading filters
$pdoFetch->timings = $log;
if (is_object($paginator)) {
	$pdoFetch->addTime('Fired paginator: "'.$scriptProperties['paginator'].'"');
}
else {
	$pdoFetch->addTime('Could not find pagination snippet with name: "'.$scriptProperties['paginator'].'"');
}
if (empty($filters)) {
	$pdoFetch->addTime('No filters retrieved');
	$output['filters'] = $modx->lexicon('mse2_err_no_filters');
	if (empty($output['results'])) {$output['results'] = $modx->lexicon('mse2_err_no_results');}
}
else {
	$pdoFetch->addTime('Filters retrieved');
	$request = array();
	foreach ($_GET as $k => $v) {
		$request[$k] = explode($mSearch2->config['values_delimeter'], $v);
	}

	foreach ($filters as $filter => $data) {
		if (empty($data)) {continue;}
		$tplOuter = !empty($scriptProperties['tplFilter.outer.'.$filter]) ? $scriptProperties['tplFilter.outer.'.$filter] : $scriptProperties['tplFilter.outer.default'];
		$tplRow = !empty($scriptProperties['tplFilter.row.'.$filter]) ? $scriptProperties['tplFilter.row.'.$filter] : $scriptProperties['tplFilter.row.default'];
		$tplEmpty = !empty($scriptProperties['tplFilter.empty.'.$filter]) ? $scriptProperties['tplFilter.empty.'.$filter] : '';

		// Caching chunk for quick placeholders
		$pdoFetch->getChunk($tplRow);

		$rows = $has_active = '';
		list($table,$filter2) = explode($mSearch2->config['filter_delimeter'], $filter);
		$idx = 0;
		foreach ($data as $v) {
			if (empty($v)) {continue;}
			$checked = isset($request[$filter]) && in_array($v['value'], $request[$filter]) && isset($v['type']) && $v['type'] != 'number';
			if ($scriptProperties['suggestions']) {
				if ($checked) {$num = ''; $has_active = 'has_active';}
				else if (isset($suggestions[$filter][$v['value']])) {
					$num = $suggestions[$filter][$v['value']];
				}
				else {
					$num = !empty($v['resources']) ? count($v['resources']) : '';
				}
			} else {$num = '';}

			$rows .= $pdoFetch->getChunk($tplRow, array(
				'filter' => $filter2
				,'table' => $table
				,'title' => $v['title']
				,'value' => $v['value']
				,'type' => $v['type']
				,'checked' => $checked ? 'checked' : ''
				,'selected' => $checked ? 'selected' : ''
				,'disabled' => !$checked && empty($num) && $scriptProperties['suggestions'] ? 'disabled' : ''
				,'delimeter' => $mSearch2->config['filter_delimeter']
				,'idx' => $idx++
				,'num' => $num
			), $fastMode);
		}

		$tpl = empty($rows) ? $tplEmpty : $tplOuter;
		$output['filters'][$filter] .= $pdoFetch->getChunk($tpl, array(
			'filter' => $filter2
			,'table' => $table
			,'rows' => $rows
			,'has_active' => $has_active
			,'delimeter' => $mSearch2->config['filter_delimeter']
		), $fastMode);
	}

	if (empty($output['filters'])) {
		$output['filters'] = $modx->lexicon('mse2_err_no_filters');
		if (empty($output['results'])) {$output['results'] = $modx->lexicon('mse2_err_no_results');}
	}
	else {
		$pdoFetch->addTime('Filters templated');
	}
}

// Saving params into session for ajax requests
$_SESSION['mFilter2'][$modx->resource->id]['scriptProperties'] = $scriptProperties;

// Active class for sort links
if (!empty($sort)) {$output[$sort] = $classActive;}
if (isset($paginatorProperties['tpl_idx'])) {
	$output['tpl'.$paginatorProperties['tpl_idx']] = $classActive;
	$output['tpls'] = 1;
}

// Setting values for frontend javascript
$modx->regClientStartupScript('<script type="text/javascript">
	mSearch2Config.start_sort = "'.$start_sort.'";
	mSearch2Config.start_limit= "'.$start_limit.'";
	mSearch2Config.start_page = "1";
	mSearch2Config.start_tpl = "";
	mSearch2Config.sort = "'.($sort == $start_sort ? '' : $sort).'";
	mSearch2Config.limit = "'.($limit == $start_limit ? '' : $limit).'";
	mSearch2Config.page = "'.$page.'";
	mSearch2Config.tpl = "'.(!empty($paginatorProperties['tpl_idx']) ? $paginatorProperties['tpl_idx'] : '').'";
	mSearch2Config.queryVar = "'.$queryVar.'";
	mSearch2Config.parentsVar = "'.$parentsVar.'";
	mSearch2Config.'.$queryVar.' = "'.(isset($_REQUEST[$queryVar]) ? $_REQUEST[$queryVar] : '').'";
	mSearch2Config.'.$parentsVar.' = "'.(isset($_REQUEST[$parentsVar]) ? $_REQUEST[$parentsVar] : '').'";
</script>');

$pdoFetch->addTime('Total filter operations: '.$mSearch2->filter_operations);
// Process main chunk
$log = '';
if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
	$log = '<pre class="mFilterLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
}

if (!empty($toSeparatePlaceholders)) {
	$modx->setPlaceholders($output['filters'], $toSeparatePlaceholders);
	$output['log'] = $log;
	if (is_array($output['filters'])) {
		$output['filters'] = implode($output['filters']);
	}
	$modx->setPlaceholders($output, $toSeparatePlaceholders);
}
else {
	if (is_array($output['filters'])) {
		$output['filters'] = implode($output['filters']);
	}
	if (!empty($toPlaceholders)) {
		$output['log'] = $log;
		$modx->setPlaceholders($output, $toPlaceholders);
	}
	else {
		$output = $pdoFetch->getChunk($scriptProperties['tplOuter'], $output, $fastMode);
		$output .= $log;

		return $output;
	}
}