<?php
/** @var array $scriptProperties */
/** @var pdoTools $pdoTools */
$fqn = $modx->getOption('pdoTools.class', null, 'pdotools.pdofetch', true);
if (!$pdoClass = $modx->loadClass($fqn, '', false, true)) {return false;}
$pdoTools = new $pdoClass($modx, $scriptProperties);
$pdoTools->addTime('pdoTools loaded.');

/** @var mSearch2 $mSearch2 */
if (!$modx->loadClass('msearch2', MODX_CORE_PATH . 'components/msearch2/model/msearch2/', false, true)) {return false;}
$mSearch2 = new mSearch2($modx, $scriptProperties, $pdoFetch);
$mSearch2->initialize($modx->context->key);

if (empty($scriptProperties['pageId'])) {$scriptProperties['pageId'] = $modx->resource->id;}
if (empty($tplForm)) {$tplForm = 'tpl.mSearch2.form';}

$form = $pdoTools->getChunk($tplForm, $scriptProperties);

$hash = sha1(serialize($scriptProperties));
$_SESSION['mSearch2'][$hash] = $scriptProperties;

$form = str_ireplace('<form', '<form data-key="'.$hash.'"', $form);
// Place for enabled log
if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
	$form = str_ireplace('</form>', "</form>\n<pre class=\"mSearchFormLog\"></pre>", $form);
}

return $form;