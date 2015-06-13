<?php
return array(
  'shop_csvimport_config' => array(
    'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
    'name' => array('varchar', 255, 'null' => ''),
    'data' => array('text', 'null' => 0),
    ':keys' => array(
      'PRIMARY' => 'id'),
  ),
);