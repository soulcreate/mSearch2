<?php
/**
 * Properties for the mSearch2 snippet.
 *
 * @package msearch2
 * @subpackage build
 */

$properties = array();

$tmp = array(
	'pageId' => array(
		'type' => 'numberfield',
		'value' => ''
	),
	'tplForm' => array(
		'type' => 'textfield',
		'value' => 'tpl.mSearch2.form'
	),
	'tpl' => array(
		'type' => 'textfield',
		'value' => 'tpl.mSearch2.ac'
	),
	'element' => array(
		'type' => 'textfield',
		'value' => 'mSearch2'
	),
	'limit' => array(
		'type' => 'numberfield',
		'value' => 5
	),
	'autocomplete' => array(
		'type' => 'list',
		'options' => array(
			array('text' => 'Disabled', 'value' => 0),
			array('text' => 'Results', 'value' => 'results'),
			array('text' => 'Queries', 'value' => 'queries'),
		),
		'value' => 'results',
	),
	'queryVar' => array(
		'type' => 'textfield',
		'value' => 'query'
	),
	'minQuery' => array(
		'type' => 'textfield',
		'value' => 3
	),
);

foreach ($tmp as $k => $v) {
	$properties[] = array_merge(array(
			'name' => $k
			,'desc' => 'mse2_prop_'.$k
			,'lexicon' => 'msearch2:properties'
		), $v
	);
}

return $properties;