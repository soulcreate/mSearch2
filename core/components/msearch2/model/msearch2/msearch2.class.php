<?php
/**
 * The base class for mSearch2.
 *
 * @package msearch2
 */

class mSearch2 {
	/** @var modX $modx */
	public $modx;
	/** @var array $config */
	public $config = array();
	/** @var mSearch2ControllerRequest $request */
	protected $request;
	/** @var mse2FiltersHandler $filtersHandler */
	public $filtersHandler = null;
	/** @var array $phpMorphy */
	public $phpMorphy = array();
	/** Cache for filters methods */
	public $methods = array();
	/** Cache for filters values */
	public $filters = array();
	/** @var integer Total number of filter operations */
	public $filter_operations = 0;
	/** @var string Current search query */
	public $query = '';
	/** @var null|\pdoTools $pdoTools */
	public $pdoTools = null;


	public function __construct(modX &$modx,array $config = array(), pdoTools &$pdoTools = null) {
		$this->modx =& $modx;

		$corePath = $this->modx->getOption('msearch2.core_path', $config, $this->modx->getOption('core_path').'components/msearch2/');
		$assetsPath = $this->modx->getOption('msearch2.assets_path', $config, $this->modx->getOption('assets_path').'components/msearch2/');
		$assetsUrl = $this->modx->getOption('msearch2.assets_url', $config, $this->modx->getOption('assets_url').'components/msearch2/');
		$actionUrl = $this->modx->getOption('msearch2.action_url', $config, $assetsUrl.'action.php');
		$connectorUrl = $assetsUrl.'connector.php';

		$this->config = array_merge(array(
			'assetsUrl' => $assetsUrl
			,'cssUrl' => $assetsUrl.'css/'
			,'jsUrl' => $assetsUrl.'js/'
			,'jsPath' => $assetsPath.'js/'
			,'imagesUrl' => $assetsUrl.'images/'
			,'customPath' => $corePath.'custom/'
			,'dictsPath' => $corePath.'phpmorphy/dicts/'

			,'connectorUrl' => $connectorUrl
			,'actionUrl' => $actionUrl

			,'corePath' => $corePath
			,'modelPath' => $corePath.'model/'
			,'templatesPath' => $corePath.'elements/templates/'
			,'processorsPath' => $corePath.'processors/'

			,'cacheTime' => 1800
			,'min_word_length' => $this->modx->getOption('mse2_index_min_words_length', null, 3, true)
			,'exact_match_bonus' => $this->modx->getOption('mse2_search_exact_match_bonus', null, 5, true)
			,'like_match_bonus' => $this->modx->getOption('mse2_search_like_match_bonus', null, 3, true)
			,'all_words_bonus' => $this->modx->getOption('mse2_search_all_words_bonus', null, 5, true)
			,'introCutBefore' => 50
			,'introCutAfter' => 250
			,'filter_delimeter' => '|'
			,'method_delimeter' => ':'
			,'values_delimeter' => ','
			,'split_words' => $this->modx->getOption('mse2_search_split_words', null, '#\s#', true)
			,'split_all' => '#\s|[,.:;!?"\'(){}\\/\#]#'
			,'suggestionsRadio' => array()

			,'autocomplete' => 0
			,'queryVar' => 'query'
			,'minQuery' => 3
			,'config_file' => $assetsPath.'js/web/config.js'
		), $config);

		if (!is_array($this->config['suggestionsRadio'])) {
			$this->config['suggestionsRadio'] = array_map('trim', explode(',', $this->config['suggestionsRadio']));
		}

		$this->modx->addPackage('msearch2', $this->config['modelPath']);
		$this->modx->lexicon->load('msearch2:default');

		if (!empty($pdoTools) && $pdoTools instanceof pdoTools) {
			$this->pdoTools = $pdoTools;
		}
	}


	/**
	 * Initializes mSearch2 into different contexts.
	 *
	 * @param string $ctx The context to load. Defaults to web.
	 * @param array $scriptProperties
	 *
	 * @return boolean
	 */
	public function initialize($ctx = 'web', $scriptProperties = array()) {
		switch ($ctx) {
			case 'mgr':
				if (!$this->modx->loadClass('msearch2.request.mSearch2ControllerRequest', $this->config['modelPath'], true, true)) {
					return 'Could not load controller request handler.';
				}
				$this->request = new mSearch2ControllerRequest($this);
				return $this->request->handleRequest();
				break;
			default:
				$this->config = array_merge($this->config, $scriptProperties);
				$this->config['ctx'] = $ctx;
				//$initializing = !empty($this->modx->loadedjscripts[$this->config['jsUrl'] . 'web/config.js']);

				if (!defined('MODX_API_MODE') || !MODX_API_MODE) {
					$config = $this->makePlaceholders($this->config);
					if ($css = trim($this->modx->getOption('mse2_frontend_css'))) {
						$this->modx->regClientCSS(str_replace($config['pl'], $config['vl'], $css));
					}
					if ($js = trim($this->modx->getOption('mse2_frontend_js'))) {
						$this->modx->regClientScript(preg_replace(array('/^\n/', '/\t{7}/'), '', '
						<script type="text/javascript">
						if(typeof jQuery == "undefined") {
							document.write("<script src=\"'.$this->config['jsUrl'].'web/lib/jquery.min.js\" type=\"text/javascript\"><\/script>");
						}
						</script>
						'), true);
						$this->modx->regClientScript(str_replace($config['pl'], $config['vl'], $js));
					}
				}
		}

		return true;
	}


	/**
	 * Method loads custom classes from specified directory
	 *
	 * @var string $dir Directory for load classes
	 * @return void
	 */
	public function loadCustomClasses($dir) {
		$files = scandir($this->config['customPath'] . $dir);
		foreach ($files as $file) {
			if (preg_match('/.*?\.class\.php$/i', $file)) {
				include_once($this->config['customPath'] . $dir . '/' . $file);
			}
		}
	}


	/**
	 * Initializes phpMorphy for needed language
	 *
	 * @return boolean
	 */
	public function loadPhpMorphy() {
		if (!class_exists('phpMorphy_Exception')) {
			require_once $this->config['corePath'] . 'phpmorphy/src/common.php';
		}

		$dicts = $this->getDictionaries();
		foreach (array_keys($dicts) as $lang) {
			if (!empty($this->phpMorphy[$lang]) && $this->phpMorphy[$lang] instanceof phpMorphy) {
				continue;
			}
			else {
				try {
					$this->phpMorphy[$lang] = new phpMorphy(
						$this->config['corePath'] . 'phpmorphy/dicts/'
						,$lang
						,array(
							'storage' => 'file'
						)
					);
				} catch (phpMorphy_Exception $e) {
					$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Could not initialize phpMorphy for language .'.$lang.': .'.$e->getMessage());
				}
			}
		}

		if (!count($this->phpMorphy)) {
			$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Could not find any phpMorphy dictionary.');
			return false;
		}
		else {
			return true;
		}
	}


	/**
	 * Get dictionaries from phpMorphy directory
	 *
	 * @return array
	 */
	public function getDictionaries() {
		$dicts = array();

		$path = $this->config['dictsPath'];
		if (!file_exists($path)) {
			return $dicts;
		}

		$files = scandir($path);
		foreach ($files as $file) {
			if (preg_match('/\.([a-z]{2,2}\_[a-z]{2,2})\./i', $file, $matches)) {
				$dicts[$matches[1]][] = $file;
			}
		}

		return $dicts;
	}


	/**
	 * Returns array with words for search
	 *
	 * @param string $text
	 * @param string $pcre
	 *
	 * @return array
	 */
	public function getBulkWords($text = '', $pcre = '') {
		if (empty($pcre)) {$pcre = $this->config['split_words'];}
		$words = preg_split($pcre, $text, -1, PREG_SPLIT_NO_EMPTY);
		$bulk_words = array();
		foreach ($words as $v) {
			if (preg_match('/^[0-9]{2,}$/', $v) || mb_strlen($v,'UTF-8') >= $this->config['min_word_length']) {
				$word = mb_strtoupper($v, 'UTF-8');
				$bulk_words[$word] = $word;
			}
		}
		return $bulk_words;
	}


	/**
	 * Gets base form of the words
	 *
	 * @param array|string $text
	 * @param boolean $only_words
	 *
	 * @return array|string
	 */
	public function getBaseForms($text, $only_words = true) {
		$result = array();
		if (is_array($text)) {
			foreach ($text as $v) {
				$result = array_merge($result, $this->getBaseForms($v, $only_words));
			}
		}
		else {
			$text = str_ireplace('ё', 'е', $this->modx->stripTags($text));
			$text = preg_replace('#\[.*\]#isU', '', $text);

			$bulk_words = $this->getBulkWords($text, $this->config['split_all']);
			$this->loadPhpMorphy();
			/* @var phpMorphy $phpMorphy */
			$base_forms = array();
			foreach ($this->phpMorphy as $phpMorphy) {
				$locale = $phpMorphy->getLocale();
				$base_forms[$locale] = $phpMorphy->getBaseForm($bulk_words);
			}

			$result = array();
			foreach ($base_forms as $lang => $array) {
				if (!empty($array)) {
					foreach ($array as $word => $forms) {
						//if (!$forms) {$forms = array($word);}
						if (!$forms) {continue;}
						foreach ($forms as $form) {
							if (preg_match('/^[0-9]{2,}$/', $form) || mb_strlen($form,'UTF-8') >= $this->config['min_word_length']) {
								$result[$form] = $lang == 'en_EN'
									? iconv('WINDOWS-1250', 'UTF-8//IGNORE', $word)
									: $word;
							}
						}
					}
				}
			}
			if ($only_words) {
				$result = array_keys($result);
			}
		}

		return $result;
	}


	/**
	 * Gets all morphological forms of the words
	 *
	 * @param array|string $text
	 *
	 * @return array|string
	 */
	public function getAllForms($text) {
		$result = array();
		if (is_array($text)) {
			foreach ($text as $v) {
				$result = array_merge($result, $this->getAllForms($v));
			}
		}
		else {
			$text = str_ireplace('ё', 'е', $this->modx->stripTags($text));

			$bulk_words = $this->getBulkWords($text);
			$this->loadPhpMorphy();
			/* @var phpMorphy $phpMorphy */
			$all_forms = array();
			foreach ($this->phpMorphy as $phpMorphy) {
				$locale = $phpMorphy->getLocale();
				$all_forms[$locale] = $phpMorphy->getAllForms($bulk_words);
			}

			$result = array();
			if (!empty($all_forms)) {
				foreach ($all_forms as $lang => $array) {
					if (!empty($array)) {
						foreach ($array as $word => $forms) {
							if (!empty($forms)) {
								if ($lang == 'en_EN') {
									foreach ($forms as &$v) {
										$v = iconv('WINDOWS-1250', 'UTF-8//IGNORE', $v);
									}
								}
								$result[$word] = isset($result[$word])
									? array_merge($result[$word], $forms)
									: $forms;
							}
						}
					}
				}
			}
		}

		return $result;
	}


	/**
	 * Search and return array with resources ids as a key and sum of weight as value
	 *
	 * @param $query
	 *
	 * @return array
	 */
	public function Search($query) {
		$string = preg_replace('/[^_-а-яёa-z0-9\s\.\/]+/iu', ' ', $this->modx->stripTags($query));
		$this->log('Filtered search query: "'.mb_strtolower($query.'"', 'UTF-8'));
		$string = $this->query = $this->addAliases($string);
		$this->log('Search query with processed aliases: "'.mb_strtolower($string.'"', 'UTF-8'));
		$words = $this->getBaseForms($string, false);
		$result = $all_words = $found_words = array();

		// Search by words index
		if (!empty($words)) {
			$q = $this->modx->newQuery('mseWord');
			$q->select('`resource`, `word`, `weight`');
			$q->where(array('word:IN' => array_keys($words)));
			$tstart = microtime(true);
			if ($q->prepare() && $q->stmt->execute()) {
				$this->modx->queryTime += microtime(true) - $tstart;
				$this->modx->executedQueries++;
				while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
					if (isset($result[$row['resource']])) {
						$result[$row['resource']] += $row['weight'];
					}
					else {
						$result[$row['resource']] = (int) $row['weight'];
					}

					if (isset($words[$row['word']])) {
						$all_words[$row['resource']][$words[$row['word']]] = 1;
						$found_words[$words[$row['word']]] = 1;
					}
				}
			}
		}

		$added = 0;

		if (!empty($found_words)) {
			$this->log('Found results by words INDEX ('.implode(',',array_keys($words)).'): '.count($result));
		}
		else {
			$this->log('Nothing found by words INDEX');
		}

		// Add bonuses
		$bulk_words = $this->getBulkWords($query);
		$tmp_words = preg_split($this->config['split_words'], $query, -1, PREG_SPLIT_NO_EMPTY);
		if (count($bulk_words) > 1 || count($tmp_words) > 1 || empty($result)) {
			if (!empty($this->config['exact_match_bonus']) || !empty($this->config['all_words_bonus'])) {
				$exact = $this->simpleSearch($query);
				// Exact match bonus
				if (!empty($this->config['exact_match_bonus'])) {
					foreach ($exact as $v) {
						if (isset($result[$v])) {
							$result[$v] += $this->config['exact_match_bonus'];
							//$this->log('Added "exact match bonus" for a resource '.$v.' for words "'.implode(', ', $words).'": +'.$this->config['exact_match_bonus']);
						}
						else {
							$result[$v] = $this->config['exact_match_bonus'];
							//$this->log('Found resource '.$v.' by LIKE search with all words "'.implode(', ',$words).'": +'.$this->config['exact_match_bonus']);
							$added ++;
						}
					}
				}

				if (!empty($this->config['all_words_bonus'])) {
					if (count($bulk_words) > 1) {
						// All words bonus
						foreach ($all_words as $k => $v) {
							if (count($bulk_words) == count($v)) {
								$result[$k] += $this->config['all_words_bonus'];
								//$this->log('Added "all words bonus" for a resource '.$k.' for words "'.implode(', ', $words).'": +'.$this->config['all_words_bonus']);
							}
						}
					}
				}
			}
		}
		elseif (!empty($this->config['like_match_bonus'])) {
			// Add the whole possible results for query
			$all_results = $this->simpleSearch($query);
			foreach ($all_results as $v) {
				if (!isset($result[$v])) {
					$weight = round($this->config['like_match_bonus']);
					$result[$v] = $weight;
					//$this->log('Found resource '.$v.' by LIKE search with all words "'.$query.'": +'.$weight);
					$added ++;
				}
			}
		}

		// Add matches by %LIKE% search
		if (!empty($this->config['like_match_bonus']) && $not_found = array_diff($bulk_words, $words)) {
			foreach ($not_found as $word) {
				$found = $this->simpleSearch($word);
				foreach ($found as $v) {
					$weight = round($this->config['like_match_bonus']);
					if (!isset($result[$v])) {
						$result[$v] = $weight;
						//$this->log('Found resource '.$v.' by LIKE search with single word "'.$word.'": +'.$weight);
						$added ++;
					}
					else {
						$result[$v] += $weight;
						//$this->log('Added "LIKE bonus" for a resource '.$v.' for word "'.$word.'": +'.$weight);
					}

				}
			}
		}

		$this->log('Added resources by LIKE search: '.$added);

		// Log the search query
		/** @var mseQuery $object */
		if ($object = $this->modx->getObject('mseQuery', array('query' => $query))) {
			$object->set('quantity', $object->quantity + 1);
		}
		else {
			$object = $this->modx->newObject('mseQuery');
			$object->set('query', $query);
			$object->set('quantity', 1);
		}
		$object->set('found', count($result));
		$object->save();

		arsort($result);
		return $result;
	}


	/**
	 * Search and return array with resources that matched for LIKE search
	 *
	 * @param $query
	 *
	 * @return array
	 */
	public function simpleSearch($query) {
		$string = preg_replace('/[^_-а-яёa-z0-9\s\.\/]+/iu', ' ', $this->modx->stripTags($query));

		$result = array();
		$q = $this->modx->newQuery('mseIntro');
		$q->select('`resource`');
		$q->where(array('intro:LIKE' => '%'.$string.'%'));
		$tstart = microtime(true);
		if ($q->prepare() && $q->stmt->execute()) {
			$this->modx->queryTime += microtime(true) - $tstart;
			$this->modx->executedQueries++;
			$result = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
		}

		return $result;
	}


	/**
	 * @param $string
	 *
	 * @return mixed
	 */
	public function addAliases($string) {
		$string = mb_strtoupper(str_ireplace('ё', 'е', $this->modx->stripTags($string)), 'UTF-8');
		$string = preg_replace('#\[.*\]#isU', '', $string);

		$pcre = $this->config['split_words'];
		$words = preg_split($pcre, $string, -1, PREG_SPLIT_NO_EMPTY);
		$words = array_map('mb_strtolower', array_unique($words));
		$forms = $this->getBaseForms($words, false);

		$q = $this->modx->newQuery('mseAlias', array('word:IN' => array_merge($words, array_keys($forms))));
		$q->select('word,alias,replace');
		$tstart = microtime(true);
		if ($q->prepare() && $q->stmt->execute()) {
			$this->modx->queryTime += microtime(true) - $tstart;
			$this->modx->executedQueries++;
			while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($row['replace']) {
					$all = current($this->getAllForms($row['word']));
					$all[] = $row['word'];
					foreach ($all as $word) {
						$key = array_search(mb_strtolower($word), $words);
						if ($key !== false) {
							$words[$key] = $row['alias'];
						}
					}
				}
				else {
					$words[] = $row['alias'];
				}
			}
		}

		$words = array_unique($words);
		return implode(' ', $words);
	}


	/**
	 * Highlight search string in given text string
	 *
	 * @param $text
	 * @param string $query
	 * @param string $htag_open
	 * @param string $htag_close
	 * @param boolean $strict
	 *
	 * @return mixed
	 */
	public function Highlight($text, $query, $htag_open = '<b>', $htag_close = '</b>', $strict = true) {
		$from = $to = array();

		$tmp_words = preg_split($this->config['split_words'], $query, -1, PREG_SPLIT_NO_EMPTY);
		// Exact match
		$pcre = $strict ? '/\b'.$query.'\b/imu' : '/'.$query.'/imu';
		if (count($tmp_words) > 1 && preg_match($pcre, $text, $matches)) {
			$pos = mb_stripos($text, $matches[0], 0, 'UTF-8');
			if ($pos >= $this->config['introCutBefore']) {
				$text_cut = '... ';
				$pos -= $this->config['introCutBefore'];
			}
			else {
				$text_cut = '';
				$pos = 0;
			}
			$text_cut .= mb_substr($text, $pos, $this->config['introCutAfter'], 'UTF-8');
			if (mb_strlen($text,'UTF-8') > $this->config['introCutAfter']) {$text_cut .= ' ...';}

			foreach ($matches as $v) {
				$from[$v] = $v;
				$to[$v] = $htag_open.$v.$htag_close;
			}
		}
		// Matching by separate words
		else {
			if (empty($this->query)) {
				$this->query = $this->addAliases($query);
			}

			$tmp = array_merge(
				$tmp_words
				,explode(' ', $this->query)
			);
			$tmp = array_unique($tmp);
			$tmp = $this->getAllForms($tmp);

			$words = array_keys($tmp);
			foreach ($tmp as $v) {
				$words = array_merge($words, $v);
			}

			if (empty($words)) {
				$words = array($query => $query);
			}

			$text_cut = '';
			foreach ($words as $key => $word) {
				/*
				if (!preg_match('/^[0-9]{2,}$/', $word) && mb_strlen($word,'UTF-8') < $this->config['min_word_length']) {
					unset($words[$key]);
					continue;
				}
				*/
				$word = preg_quote($word, '/');
				$words[$key] = $word;

				// Cutting text on first occurrence
				$pcre = $strict ? '/\b'.$word.'\b/imu' : '/'.$word.'/imu';
				if (empty($text_cut) && !empty($word) && preg_match($pcre, $text, $matches)) {
					$pos = mb_stripos($text, $matches[0], 0, 'UTF-8');
					if ($pos >= $this->config['introCutBefore']) {
						$text_cut = '... ';
						$pos -= $this->config['introCutBefore'];
					}
					else {
						$text_cut = '';
						$pos = 0;
					}
					$text_cut .= mb_substr($text, $pos, $this->config['introCutAfter'], 'UTF-8');
					if (mb_strlen($text,'UTF-8') > $this->config['introCutAfter']) {$text_cut .= ' ...';}
				}
			}

			if (empty($text_cut) && $strict) {
				return $this->Highlight($text, $query, $htag_open, $htag_close, false);
			}

			$pcre = $strict ? '/\b('.implode('|',$words).')\b/imu' : '/('.implode('|',$words).')/imu';
			if (preg_match_all($pcre, $text_cut, $matches)) {
				foreach ($matches[0] as $v) {
					$from[$v] = $v;
					$to[$v] = $htag_open.$v.$htag_close;
				}
			}

			if (!empty($matches[1])) {
				foreach ($matches[1] as $v) {
					$from[$v] = $v;
					$to[$v] = $htag_open.$v.$htag_close;
				}
			}
			elseif ($strict) {
				return $this->Highlight($text, $query, $htag_open, $htag_close, false);
			}
		}

		return str_replace($from, $to, $text_cut);
	}


	/**
	 * Return array with filters
	 *
	 * @param array|string $ids
	 * @param boolean $build
	 *
	 * @return array
	 */
	public function getFilters($ids, $build = true) {
		// prepare ids
		if (!is_array($ids)) {
			$ids = array_map('trim', explode(',', $ids));
		}
		if (empty($ids)) {return false;}

		// Return results from cache
		if ($build && $prepared = $this->modx->cacheManager->get('msearch2/prep_' . md5(implode(',',$ids) . $this->config['filters']))) {
			return $prepared;
		}
		elseif (!$build && !empty($this->filters)) {
			return $this->filters;
		}
		elseif (!$build && $this->filters = $this->modx->cacheManager->get('msearch2/fltr_' . md5(implode(',',$ids)))) {
			return $this->filters;
		}

		if (!is_object($this->filtersHandler)) {$this->loadHandler();}

		// Preparing filters
		$filters = $built = array();
		$tmp_filters = array_map('trim', explode(',', $this->config['filters']));
		foreach ($tmp_filters as $v) {
			$v = strtolower($v);
			if (strpos($v, $this->config['filter_delimeter']) !== false) {
				@list($table, $filter) = explode($this->config['filter_delimeter'], $v);
			}
			else {
				$table = 'resource';
				$filter = $v;
			}

			$tmp = explode($this->config['method_delimeter'], $filter);
			$filters[$table][$tmp[0]] = array();
			$built[$table.$this->config['filter_delimeter'].$tmp[0]] = !empty($tmp[1]) ? $tmp[1] : 'default';
		}

		// Retrieving filters
		foreach ($filters as $table => &$fields) {
			$method = 'get'.ucfirst($table).'Values';
			$keys = array_keys($fields);
			if (method_exists($this->filtersHandler, $method)) {
				$fields = call_user_func_array(array($this->filtersHandler, $method), array(array_keys($fields), $ids));

				foreach ($keys as $key) {
					if (!isset($fields[$key])) {
						$fields[$key] = array();
					}
				}

			}
			else {
				$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Method "'.$method.'" not exists in class "'.get_class($this->filtersHandler).'". Could not retrieve filters from "'.$table.'"');
			}
		}

		$this->filters = $filters;
		// Building filters
		if ($build) {
			foreach ($built as $filter => &$value) {
				list($table, $filter) = explode($this->config['filter_delimeter'], $filter);
				$values = $filters[$table][$filter];

				$method = 'build'.ucfirst($value).'Filter';
				if (method_exists($this->filtersHandler, $method)) {
					$value = call_user_func_array(array($this->filtersHandler, $method), array($values));
				}
				else {
					$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Method "'.$method.'" not exists in class "'.get_class($this->filtersHandler).'". Could not build filter "'.$table.$this->config['filter_delimeter'].$filter.'"');
					$value = $values;
				}
			}
			$this->modx->cacheManager->set('msearch2/prep_' . md5(implode(',',$ids) . $this->config['filters']), $built, $this->config['cacheTime']);
			return $built;
		}
		else {
			return $filters;
		}
	}


	/**
	 * Filters resources by given params
	 *
	 * @param array|string $ids
	 * @param array $request
	 *
	 * @return array
	 */
	public function Filter($ids, array $request) {
		if (!is_array($ids)) {$ids = explode(',', $ids);}
		if (!is_object($this->filtersHandler)) {$this->loadHandler();}

		$filters = $this->getFilters($ids, false);
		$methods = $this->getMethods();

		foreach ($request as $filter => $requested) {
			if (!preg_match('/(.*?)'.preg_quote($this->config['filter_delimeter'],'/').'(.*?)/', $filter)) {continue;}
			$method = !empty($methods[$filter]) ? 'filter' . ucfirst($methods[$filter]) : 'filterDefault';

			list($table, $filter) = explode($this->config['filter_delimeter'], $filter);
			if (isset($filters[$table][$filter])) {
				$values = $filters[$table][$filter];
				$requested = explode($this->config['values_delimeter'], $requested);

				if (method_exists($this->filtersHandler, $method)) {
					$ids = call_user_func_array(array($this->filtersHandler, $method), array($requested, $values, $ids));
				}
				else {
					//$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Method "'.$method.'" not exists in class "'.get_class($this->filtersHandler).'". Could not build filter "'.$table.$this->config['filter_delimeter'].$filter.'"');
					$ids = @call_user_func_array(array($this->filtersHandler, 'filterDefault'), array($requested, $values, $ids));
				}
			}
			$this->filter_operations++;
		}

		return $ids;
	}


	/**
	 * This method returns preliminary results for each filter
	 *
	 * @param $ids
	 * @param array $request
	 * @param array $current
	 *
	 * @return array
	 */
	public function getSuggestions($ids, array $request, array $current = array()) {
		if (!is_array($ids)) {$ids = explode(',', $ids);}
		if (!is_object($this->filtersHandler)) {$this->loadHandler();}

		if (method_exists($this->filtersHandler, 'getSuggestions')) {
			return $this->filtersHandler->getSuggestions($ids, $request, $current);
		}
		else {
			$current = array_flip($current);
			$filters = $this->getFilters($ids, false);
			$radio = $this->config['suggestionsRadio'];

			$suggestions = array();
			foreach ($filters as $table => $fields) {
				foreach ($fields as $field => $values) {
					foreach ($values as $value => $resources) {
						$suggest = $request;
						$key = $table.$this->config['filter_delimeter'].$field;

						$added = 0;
						if (isset($request[$key])) {
							// Types of suggestion can depend from method
							if (!empty($radio) && in_array($key, $radio)) {
								$suggest[$key] = $value;
							}
							else {
								$tmp2 = explode($this->config['values_delimeter'], $request[$key]);
								if (!in_array($value, $tmp2)) {
									$suggest[$key] .= $this->config['values_delimeter'] . $value;
									$added = 1;
								}
							}
							$res = $this->Filter($ids, $suggest);
							if ($added && !empty($res)) {
								foreach ($res as $k => $v) {
									if(isset($current[$v])) {
										unset($res[$k]);
									}
								}
								$count = count($res);
								if ($count != 0) {
									$count = '+' . $count;
								}
							}
							else {
								$count = count($res);
							}
						}
						else {
							$suggest[$key] = $value;
							$res = $this->Filter($ids, $suggest);
							$count = count($res);
						}

						$suggestions[$key][$value] = $count;
					}
				}
			}

			return $suggestions;
		}
	}


	/**
	 * Returns methods names, that will filter values
	 *
	 * @return array
	 */
	public function getMethods() {
		if (!empty($this->methods)) {return $this->methods;}

		$tmp_filters = array_map('trim', explode(',', $this->config['filters']));
		foreach ($tmp_filters as $v) {
			$v = strtolower($v);
			if (strpos($v, $this->config['filter_delimeter']) !== false) {
				@list($table, $filter) = explode($this->config['filter_delimeter'], $v);
			}
			else {
				$table = 'resource';
				$filter = $v;
			}

			$tmp = explode($this->config['method_delimeter'], $filter);
			$this->methods[$table.$this->config['filter_delimeter'].$tmp[0]] = !empty($tmp[1]) ? $tmp[1] : 'default';
		}

		return $this->methods;
	}

	/**
	 * Method for transform array to placeholders
	 *
	 * @var array $array With keys and values
	 * @var string $prefix
	 *
	 * @return array $array Two nested arrays With placeholders and values
	 */
	public function makePlaceholders(array $array = array(), $prefix = '') {
		$result = array(
			'pl' => array(),
			'vl' => array(),
		);
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				$result = array_merge_recursive($result, $this->makePlaceholders($v, $prefix . $k.'.'));
			}
			else {
				$result['pl'][$prefix.$k] = '[[+'.$prefix.$k.']]';
				$result['vl'][$prefix.$k] = $v;
			}
		}
		return $result;
	}


	/**
	 * Returns string for insert into sorting properties of pdoTools snippet
	 *
	 * @param string
	 *
	 * @return string
	 */
	public function getSortFields($sort) {
		$this->loadHandler();
		return $this->filtersHandler->getSortFields($sort);
	}


	/**
	 * Loads custom filters handler class
	 *
	 * @return bool
	 */
	public function loadHandler() {
		if (!is_object($this->filtersHandler)) {
			require_once 'filters.class.php';
			$filters_class = $this->modx->getOption('mse2_filters_handler_class', null, 'mse2FiltersHandler', true);
			if ($filters_class != 'mse2FiltersHandler') {$this->loadCustomClasses('filters');}
			if (!class_exists($filters_class)) {$filters_class = 'mse2FiltersHandler';}

			$this->filtersHandler = new $filters_class($this, $this->config);
			if (!($this->filtersHandler instanceof mse2FiltersHandler)) {
				$this->modx->log(modX::LOG_LEVEL_ERROR, '[mSearch2] Could not initialize filters handler class: "'.$filters_class.'"');
				return false;
			}
		}

		return true;
	}


	/**
	 * Checks for exists of miniShop2
	 *
	 * @return bool
	 */
	public function checkMS2() {
		return file_exists(MODX_CORE_PATH . 'components/minishop2/model/minishop2/msproduct.class.php');
	}


	/**
	 * @param $entry
	 */
	public function log($entry) {
		if ($this->pdoTools && empty($this->config['hideSearchLog'])) {
			$this->pdoTools->addTime('[mSearch2] ' . $entry);
		}
	}

}