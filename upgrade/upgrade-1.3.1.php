<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_1($module)
{
    require_once dirname(__FILE__) . '/../src/Models/autoload.php';

    // Upgrade execution_message column size to VARCHAR(512) if needed
    $db = \Db::getInstance();
    try {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'brt_restapi_shipment_response` MODIFY `execution_message` VARCHAR(512) NOT NULL DEFAULT \'\'');
    } catch (\Exception $e) {
    }

    return true;
}
