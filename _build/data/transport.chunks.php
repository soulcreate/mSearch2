<?php

$chunks = array();

$tmp = array(
	'tpl.mSearch2.row' => 'msearch2.row',
	'tpl.mFilter2.outer' => 'mfilter2.outer',
	'tpl.mFilter2.filter.outer' => 'mfilter2.filter.outer',
	'tpl.mFilter2.filter.checkbox' => 'mfilter2.filter.checkbox',
	'tpl.mFilter2.filter.number' => 'mfilter2.filter.number',
	'tpl.mFilter2.filter.radio' => 'mfilter2.filter.radio',
	'tpl.mFilter2.filter.slider' => 'mfilter2.filter.slider'
);

foreach ($tmp as $k => $v) {
	/* @avr modChunk $chunk */
	$chunk = $modx->newObject('modChunk');
	$chunk->fromArray(array(
		'id' => 0
		,'name' => $k
		,'description' => ''
		,'snippet' => file_get_contents($sources['source_core'].'/elements/chunks/chunk.'.$v.'.tpl')
		,'static' => BUILD_CHUNK_STATIC
		,'source' => 1
		,'static_file' => 'core/components/'.PKG_NAME_LOWER.'/elements/chunks/chunk.'.$v.'.tpl'
	),'',true,true);

	$chunks[] = $chunk;

	$BUILD_CHUNKS[$k] = file_get_contents($sources['source_core'].'/elements/chunks/chunk.'.$v.'.tpl');
}

unset($tmp);
return $chunks;