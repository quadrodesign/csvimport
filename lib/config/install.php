<?php

$model = new waModel();
try {
    $model->query("SELECT barcode FROM shop_product_skus WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_product_skus ADD barcode VARCHAR(40)");
}