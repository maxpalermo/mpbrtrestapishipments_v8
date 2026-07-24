<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    require_once dirname(__FILE__) . '/../src/Helpers/BrtConfig.php';
    require_once dirname(__FILE__) . '/../src/Helpers/BrtPricingRuleParser.php';

    // Pre-populate default rules if not configured yet
    $currentRules = \Configuration::get(\MpSoft\MpBrtRestApiShipments\Helpers\BrtConfig::PRICING_RULES);
    if ($currentRules === false || $currentRules === '') {
        $defaultRules = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::getDefaultRules();
        \Configuration::updateValue(\MpSoft\MpBrtRestApiShipments\Helpers\BrtConfig::PRICING_RULES, json_encode($defaultRules));
    }

    $currentDefaultCode = \Configuration::get(\MpSoft\MpBrtRestApiShipments\Helpers\BrtConfig::PRICING_DEFAULT_CODE);
    if ($currentDefaultCode === false || $currentDefaultCode === '') {
        \Configuration::updateValue(\MpSoft\MpBrtRestApiShipments\Helpers\BrtConfig::PRICING_DEFAULT_CODE, '020');
    }

    return true;
}
