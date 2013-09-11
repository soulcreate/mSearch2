<?php
/* @var mSearch2 $mSearch2 */
$mSearch2 = $modx->getService('msearch2','mSearch2',$modx->getOption('msearch2.core_path',null,$modx->getOption('core_path').'components/msearch2/').'model/msearch2/',$scriptProperties);
/* @var pdoFetch $pdoFetch */
$pdoFetch = $modx->getService('pdofetch','pdoFetch', MODX_CORE_PATH.'components/pdotools/model/pdotools/',$scriptProperties);
$pdoFetch->addTime('pdoTools loaded.');

$class = 'modResource';
if (empty($queryVar)) {$queryVar = 'query';}
if (empty($parentsVar)) {$parentsVar = 'parents';}
if (empty($minQuery)) {$minQuery = $modx->getOption('index_min_words_length', null, 3, true);}
if (empty($depth)) {$depth = 10;}
if (empty($offset)) {$offset = 0;}
if (empty($htagOpen)) {$htagOpen = '<b>';}
if (empty($htagClose)) {$htagClose = '</b>';}
if (empty($outputSeparator)) {$outputSeparator = "\n";}
if (empty($plPrefix)) {$plPrefix = 'mse2_';}
$returnIds = !empty($returnIds);
$fastMode = !empty($fastMode);
$output = null;

$found = array();
$query = !empty($_REQUEST[$queryVar]) ? $_REQUEST[$queryVar] : '';
if (empty($resources)) {
	if (empty($query) && isset($_REQUEST[$queryVar])) {
		return $modx->lexicon('mse2_err_no_query');
	}
	else if (!empty($query) && !preg_match('/^[0-9]{2,}$/', $query) && mb_strlen($query,'UTF-8') < $minQuery) {
		return $modx->lexicon('mse2_err_min_query');
	}
	else if (empty($query)) {
		return;
	}
	else {
		$query = htmlspecialchars(strip_tags(trim($query)));
		$modx->setPlaceholder($plPrefix.$queryVar, $query);
	}

	$found = $mSearch2->Search($query);
	$ids = array_keys($found);
	$resources = implode(',', $ids);

	if ($returnIds) {
		return !empty($resources) ? $resources : '0';
	}
	else if (empty($found)) {
		$output = $modx->lexicon('mse2_err_no_results');
		if (!empty($tplWrapper) && !empty($wrapIfEmpty)) {
			$output = $pdoFetch->getChunk(
				$tplWrapper,
				array(
					'output' => $output,
					'total' => 0,
					'query' => $query,
					'parents' => $modx->getPlaceholder($plPrefix.$parentsVar),
				),
				$fastMode
			);
		}
		if (!empty($toPlaceholder)) {
			$modx->setPlaceholder($toPlaceholder, $output);
		}
		else {
			return $output;
		}
	}
}
else if (strpos($resources, '{') === 0) {
	$found = $modx->fromJSON($resources);
	$resources = implode(',', array_keys($found));
}

/*----------------------------------------------------------------------------------*/

// Start building "Where" expression
$where = array($class.".id IN ({$resources})");
if (empty($showUnpublished)) {$where['published'] = 1;}
if (empty($showHidden)) {$where['hidemenu'] = 0;}
if (empty($showDeleted)) {$where['deleted'] = 0;}
if (!empty($hideContainers)) {$where['isfolder'] = 0;}

// Filter by parents
$parents = !empty($scriptProperties[$parentsVar])
	? $scriptProperties[$parentsVar]
	: !empty($_REQUEST[$parentsVar])
		? $modx->stripTags($_REQUEST[$parentsVar])
		: '';
$modx->setPlaceholder($plPrefix.$parentsVar, $parents);

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
	foreach ($pids as $k => $v) {
		if (in_array($k, $parents_in)) {
			$parents_in = array_merge($parents_in, $modx->getChildIds($k, $depth, array('context' => $v)));
		}
		else {
			$parents_out = array_merge($parents_out, $modx->getChildIds($k, $depth, array('context' => $v)));
		}
	}

	if (!empty($parents_in)) {$where['parent:IN'] = $parents_in;}
	if (!empty($parents_out)) {$where['parent:NOT IN'] = $parents_out;}

	$pdoFetch->addTime('Received parents for include "'.implode(',',$parents_in).'" and exclude: "'.implode(',', $parents_out).'"');
}

// Adding custom where parameters
if (!empty($scriptProperties['where'])) {
	$tmp = $modx->fromJSON($scriptProperties['where']);
	if (is_array($tmp)) {
		$where = array_merge($where, $tmp);
	}
}
unset($scriptProperties['where']);
$pdoFetch->addTime('"Where" expression built.');
// End of building "Where" expression

// Joining tables
$leftJoin = array(
	'{"class":"mseIntro","alias":"Intro","on":"`modResource`.`id`=`Intro`.`resource`"}'
);

// Fields to select
$resourceColumns = !empty($includeContent) ?  $modx->getSelectColumns($class, $class) : $modx->getSelectColumns($class, $class, '', array('content'), true);
$select = array('"'.$class.'":"'.$resourceColumns.'"');
$select[] = '"Intro":"intro" ';

// Default parameters
$default = array(
	'class' => $class
	,'where' => $modx->toJSON($where)
	,'leftJoin' => '['.implode(',',$leftJoin).']'
	,'select' => '{'.implode(',',$select).'}'
	,'sortby' => !empty($sortby) ? $sortby : "find_in_set(`$class`.`id`,'{$resources}')"
	,'sortdir' => !empty($sortdir) ? $sortdir : ''
	//,'groupby' => $class.'.id'
	,'fastMode' => $fastMode
	,'return' => 'data'
	,'nestedChunkPrefix' => 'msearch2_'
);

// Merge all properties and run!
$pdoFetch->setConfig(array_merge($default,$scriptProperties));
$pdoFetch->addTime('Query parameters are prepared.');
$rows = $pdoFetch->run();

// Processing rows
if (!empty($rows) && is_array($rows)) {
	foreach ($rows as $k => $row) {
		// Processing main fields
		$row['weight'] = isset($found[$row['id']]) ? $found[$row['id']] : '';
		$row['intro'] = $mSearch2->Highlight($row['intro'], $query, $htagOpen, $htagClose);

		$row['idx'] = $pdoFetch->idx++;
		$tplRow = $pdoFetch->defineChunk($row);
		$output[] .= empty($tplRow)
			? $pdoFetch->getChunk('', $row)
			: $pdoFetch->getChunk($tplRow, $row, $pdoFetch->config['fastMode']);
	}
	$pdoFetch->addTime('Returning processed chunks');
	if (!empty($output)) {
		$output = implode($outputSeparator, $output);
	}
}

if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
	$output .= '<pre class="mSearchLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
}

// Return output
if (!empty($tplWrapper) && (!empty($wrapIfEmpty) || !empty($output))) {
	$output = $pdoFetch->getChunk(
		$tplWrapper,
		array(
			'output' => $output,
			'total' => $modx->getPlaceholder($pdoFetch->config['totalVar']),
			'query' => $modx->getPlaceholder($plPrefix.$queryVar),
			'parents' => $modx->getPlaceholder($plPrefix.$parentsVar),
		),
		$fastMode
	);
}

if (!empty($toPlaceholder)) {
	$modx->setPlaceholder($toPlaceholder, $output);
}
else {
	return $output;
}