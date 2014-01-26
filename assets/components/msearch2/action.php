<?php

if (!isset($modx)) {
	define('MODX_API_MODE', true);
	require_once dirname(dirname(dirname(dirname(__FILE__)))).'/index.php';
	$modx->getService('error','error.modError');
	$modx->getRequest();
	$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
	$modx->setLogTarget('FILE');
	$modx->error->message = null;
}

if (empty($_REQUEST['action'])) {
	exit($modx->toJSON(array('success' => false, 'message' => 'Access denied')));
}
else {
	$action = $_REQUEST['action'];
}

/** @var pdoFetch $pdoFetch */
$fqn = $modx->getOption('pdoFetch.class', null, 'pdotools.pdofetch', true);
if (!$pdoClass = $modx->loadClass($fqn, '', false, true)) {return false;}
$pdoFetch = new $pdoClass($modx, array());
$pdoFetch->addTime('pdoTools loaded.');

if (!empty($_REQUEST['pageId']) && !empty($_REQUEST['key'])) {
	$modx->resource = $modx->getObject('modResource', $_REQUEST['pageId']);
	if ($modx->resource->get('context_key') != 'web') {
		$modx->switchContext($modx->resource->context_key);
	}
	$config = @$_SESSION['mSearch2'][$_REQUEST['key']];
}

if (empty($config) || !is_array($config)) {
	$action = 'no_config';
	$config = $scriptProperties = array();
}
else {
	$scriptProperties = isset($config['scriptProperties'])
		? $config['scriptProperties']
		: $config;
	/** @var mSearch2 $mSearch2 */
	$mSearch2 = $modx->getService('msearch2','mSearch2', MODX_CORE_PATH.'components/msearch2/model/msearch2/', $scriptProperties);
}
unset($_REQUEST['pageId'], $_REQUEST['action'], $_REQUEST['key']);

switch ($action) {
	case 'filter':
		$paginatorProperties = $config['paginatorProperties'];

		// Get sorting parameters
		if (!empty($_REQUEST['sort'])) {
			$sort = $_REQUEST['sort'];
		}
		elseif (!empty($paginatorProperties['defaultSort'])) {
			$sort = $paginatorProperties['defaultSort'];
		}
		$paginatorProperties['sortby'] = !empty($sort)
			? $mSearch2->getSortFields($sort)
			: '';
		$paginatorProperties['sortdir'] = '';

		if (empty($_REQUEST['limit'])) {
			$paginatorProperties['limit'] = $_REQUEST['limit'] = $paginatorProperties['start_limit'];
		}

		// Switching chunk for rows, if specified
		if (!empty($paginatorProperties['tpls']) && is_array($paginatorProperties['tpls'])) {
			$tmp = isset($_REQUEST['tpl']) ? (integer) $_REQUEST['tpl'] : 0;
			if (isset($paginatorProperties['tpls'][$tmp])) {
				$paginatorProperties['tpl'] = $paginatorProperties['tpls'][$tmp];
			}
		}

		// Processing filters
		if (strpos($paginatorProperties['resources'], '{') === 0) {
			$found = $modx->fromJSON($paginatorProperties['resources']);
			$ids = array_keys($found);
		}
		else {
			$ids = explode(',', $paginatorProperties['resources']);
		}

		$resources = implode(',', $ids);
		$pdoFetch->addTime('Getting filters for saved ids: ('.$resources.')');

		$matched = $mSearch2->Filter($ids, $_REQUEST);
		$ids = array_intersect($ids, $matched);

		$pdoFetch->addTime('Filters retrieved.');
		if (!empty($scriptProperties['suggestions'])) {
			$suggestions = $mSearch2->getSuggestions($resources, $_REQUEST, $ids);
			$pdoFetch->addTime('Suggestions retrieved.');
		} else {
			$suggestions = array();
			$pdoFetch->addTime('Suggestions disabled by snippet parameter.');
		}

		// Saving log
		$log = $pdoFetch->timings;
		$pdoFetch->timings = array();

		// Retrieving results
		if (!empty($ids)) {
			$_GET = $_REQUEST;

			$paginatorProperties['resources'] = is_array($ids) ? implode(',', $ids) : $ids;
			// Trying to save weight of found ids if using mSearch2
			if (!empty($found) && strtolower($paginatorProperties['element']) == 'msearch2') {
				$tmp = array();
				foreach ($ids as $v) {
					$tmp[$v] = @$found[$v];
				}
				$paginatorProperties['resources'] = $modx->toJSON($tmp);
			}

			$results = $modx->runSnippet($mSearch2->config['paginator'], $paginatorProperties);
			$pagination = $modx->getPlaceholder($paginatorProperties['pageNavVar']);
			$total = $modx->getPlaceholder($paginatorProperties['totalVar']);

			if (!empty($paginatorProperties['fastMode'])) {
				$results = $pdoFetch->fastProcess($results);
				$pagination = $pdoFetch->fastProcess($pagination);
			}
			else {
				$maxIterations= (integer) $modx->getOption('parser_max_iterations', null, 10);
				$modx->getParser()->processElementTags('', $results, false, false, '[[', ']]', array(), $maxIterations);
				$modx->getParser()->processElementTags('', $results, true, true, '[[', ']]', array(), $maxIterations);
				$modx->getParser()->processElementTags('', $pagination, false, false, '[[', ']]', array(), $maxIterations);
				$modx->getParser()->processElementTags('', $pagination, true, true, '[[', ']]', array(), $maxIterations);
			}
		}
		else {
			$results = $pagination = '';
		}

		$pdoFetch->timings = $log;
		$pdoFetch->addTime('Total filter operations: '.$mSearch2->filter_operations);
		$response = array(
			'success' => true,
			'message' => '',
			'data' => array(
				'results' => !empty($results) ? $results : $modx->lexicon('mse2_err_no_results'),
				'pagination' => $pagination,
				'total' => empty($total) ? 0 : $total,
				'suggestions' => $suggestions,
				'log' => ($modx->user->hasSessionContext('mgr') && !empty($scriptProperties['showLog'])) ? print_r($pdoFetch->getTime(), 1) : '',
			)
		);
		$response = $modx->toJSON($response);
		break;

	case 'search':
		$snippet = !empty($scriptProperties['element'])
			? $scriptProperties['element']
			: 'mSearch2';

		$results = array();
		$query = trim(@$_REQUEST[$scriptProperties['queryVar']]);
		if (empty($scriptProperties['limit'])) {$scriptProperties['limit'] = 5;}
		if (empty($scriptProperties['introCutAfter'])) {$scriptProperties['introCutAfter'] = 100;}

		if (!empty($scriptProperties['autocomplete'])) {
			switch (strtolower($scriptProperties['autocomplete'])) {
				case 'queries':
					$query = $string = preg_replace('/[^_-а-яёa-z0-9\s\.\/]+/iu', ' ', $modx->stripTags($query));
					$query = $mSearch2->addAliases($query);
					$condition = "`found` > 0 AND (`query` LIKE '%$query%'";
					$words = $mSearch2->getAllForms($query);
					foreach ($words as $tmp) {
						foreach ($tmp as $word) {
							$condition .= " OR `query` LIKE '%$word%'";
						}
					}
					$condition .= ')';

					$scriptProperties['sortby'] = 'quantity';
					$scriptProperties['sortdir'] = 'desc';
					$rows = $pdoFetch->getCollection('mseQuery', '["'.$condition.'"]', $scriptProperties);
					$i = 1;
					foreach ($rows as $row) {
						$intro = $mSearch2->Highlight($row['query'], $query);
						if (empty($intro)) {
							$intro = $row['query'];
						}
						$row['pagetitle'] = $row['title'] = $intro;
						$row['idx'] = $i;
						$results[] = array(
							//'id' => $row['id'],
							//'url' => $modx->makeUrl($row['id'], '', '', 'full'),
							'value' => html_entity_decode($row['query'], ENT_QUOTES, 'UTF-8'),
							'label' => preg_replace('/\[\[.*?\]\]/', '', $pdoFetch->getChunk($scriptProperties['tpl'], $row)),
						);
						$i++;
					}
					break;

				default:
					$found = $mSearch2->Search($query);
					if (!empty($found)) {
						$resources = strtolower($snippet) == 'msearch2'
							? $modx->toJSON($found)
							: implode(',', array_keys($found));

						if (!isset($scriptProperties['parents'])) {$scriptProperties['parents'] = 0;}
						if (empty($scriptProperties['sortby'])) {$scriptProperties['sortby'] = '';}
						if (!isset($scriptProperties['sortdir'])) {$scriptProperties['sortdir'] = '';}

						$scriptProperties['returnIds'] = 0;
						$scriptProperties['resources'] = $resources;
						$scriptProperties['outputSeparator'] = '<!-- msearch2 -->';

						$html = $modx->runSnippet($snippet, $scriptProperties);
						if ($modx->user->hasSessionContext('mgr') && !empty($scriptProperties['showLog'])) {
							preg_match('#<pre class=".*?Log">(.*?)</pre>#s', $html, $matches);
							$log = $matches[1];
							$html = str_replace($matches[0], '', $html);
						}
						$processed = explode('<!-- msearch2 -->', $html);

						$scriptProperties['select'] = 'id,pagetitle';
						$scriptProperties['resources'] = implode(',', array_keys($found));
						$rows = $pdoFetch->getCollection('modResource', null, $scriptProperties);

						$i = 0;
						foreach ($processed as $k => $v) {
							$row = $rows[$k];
							$results[] = array(
								'id' => $row['id'],
								'url' => $modx->makeUrl($row['id'], '', '', 'full'),
								'value' => html_entity_decode($row['pagetitle'], ENT_QUOTES, 'UTF-8'),
								'label' => preg_replace('/\[\[.*?\]\]/', '',
									isset($processed[$i])
										? $processed[$i]
										: $pdoFetch->getChunk($scriptProperties['tpl'], $row)),
							);
							$i++;
						}
					}
			}
		}

		$response = array(
			'success' => true,
			'message' => '',
			'data' => array(
				'results' => $results,
				'total' => count($results),
			)
		);
		if (!empty($log)) {
			$response['data']['log'] = $log;
		}
		$response = $modx->toJSON($response);
		break;

	case 'no_config':
		$response = $modx->toJSON(array('success' => false, 'message' => 'Could not load config'));
		break;
	default:
		$response = $modx->toJSON(array('success' => false, 'message' => 'Access denied'));
}

@session_write_close();
exit($response);