<?php
$xpdo_meta_map['mseWord']= array (
  'package' => 'msearch2',
  'version' => '1.1',
  'table' => 'mse2_words',
  'extends' => 'xPDOObject',
  'fields' => 
  array (
    'resource' => NULL,
    'field' => NULL,
    'word' => NULL,
    'count' => NULL,
  ),
  'fieldMeta' => 
  array (
    'resource' => 
    array (
      'dbtype' => 'integer',
      'precision' => '10',
      'phptype' => 'integer',
      'attributes' => 'unsigned',
      'null' => false,
      'index' => 'pk',
    ),
    'field' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '50',
      'phptype' => 'string',
      'null' => false,
      'index' => 'pk',
    ),
    'word' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '32',
      'phptype' => 'string',
      'null' => false,
      'index' => 'pk',
    ),
    'count' => 
    array (
      'dbtype' => 'integer',
      'precision' => '10',
      'phptype' => 'integer',
      'attributes' => 'unsigned',
      'null' => false,
    ),
  ),
  'indexes' => 
  array (
    'key' => 
    array (
      'alias' => 'key',
      'primary' => true,
      'unique' => true,
      'type' => 'BTREE',
      'columns' => 
      array (
        'resource' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
        'field' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
        'word' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
  'aggregates' => 
  array (
    'Resource' => 
    array (
      'class' => 'modResource',
      'local' => 'resource',
      'foreign' => 'id',
      'owner' => 'foreign',
      'cardinality' => 'one',
    ),
  ),
);
