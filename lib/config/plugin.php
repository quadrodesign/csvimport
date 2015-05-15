<?php
return array (
  'name' => 'CSV Import',
  'icon' => 'img/csvimport.gif',
  'shop_settings' => true,
  'handlers' => 
  array (
    'backend_menu' => 'backend_menu',
    'backend_product' => 'backend_product',  
    'csvimport_filter' => 'csvimport_filter',
  ),
);
