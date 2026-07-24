<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Centralised access to all MPBRTRESTAPI_* configuration keys.
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

class BrtConfig
{
    const ACCOUNT_USER_ID = 'MPBRTRESTAPI_ACCOUNT_USER_ID';
    const ACCOUNT_PASSWORD = 'MPBRTRESTAPI_ACCOUNT_PASSWORD';
    const ACCOUNT_DEPARTURE_DEPOT = 'MPBRTRESTAPI_ACCOUNT_DEPARTURE_DEPOT';
    const ACCOUNT_CUSTOMER_CODE = 'MPBRTRESTAPI_ACCOUNT_CUSTOMER_CODE';

    const SANDBOX_USER_ID = 'MPBRTRESTAPI_SANDBOX_USER_ID';
    const SANDBOX_PASSWORD = 'MPBRTRESTAPI_SANDBOX_PASSWORD';
    const SANDBOX_DEPARTURE_DEPOT = 'MPBRTRESTAPI_SANDBOX_DEPARTURE_DEPOT';
    const SANDBOX_CUSTOMER_CODE = 'MPBRTRESTAPI_SANDBOX_CUSTOMER_CODE';
    const SANDBOX_ENABLED = 'MPBRTRESTAPI_SANDBOX_ENABLED';

    const NETWORK = 'MPBRTRESTAPI_NETWORK';
    const FREIGHT_TYPE = 'MPBRTRESTAPI_FREIGHT_TYPE';
    const SERVICE_TYPE = 'MPBRTRESTAPI_SERVICE_TYPE';
    const NATURA_MERCI = 'MPBRTRESTAPI_NATURA_MERCI';
    const COD_PAYMENT_MODULES = 'MPBRTRESTAPI_COD_PAYMENT_MODULES';

    const ORDERSTATES_DISPLAY = 'MPBRTRESTAPI_ORDERSTATES_DISPLAY';
    const ORDERSTATE_CHANGE = 'MPBRTRESTAPI_ORDERSTATE_CHANGE';
    const ORDERSTATES_START_DATE = 'MPBRTRESTAPI_ORDERSTATES_START_DATE';
    const ORDERSTATES_END_DATE = 'MPBRTRESTAPI_ORDERSTATES_END_DATE';

    const LABEL_OUTPUT_TYPE = 'MPBRTRESTAPI_LABEL_OUTPUT_TYPE';
    const LABEL_OFFSET_X = 'MPBRTRESTAPI_LABEL_OFFSET_X';
    const LABEL_OFFSET_Y = 'MPBRTRESTAPI_LABEL_OFFSET_Y';
    const LABEL_BORDER = 'MPBRTRESTAPI_LABEL_BORDER';
    const LABEL_LOGO = 'MPBRTRESTAPI_LABEL_LOGO';
    const LABEL_BARCODE = 'MPBRTRESTAPI_LABEL_BARCODE';
    const LABEL_FORMAT = 'MPBRTRESTAPI_LABEL_FORMAT';

    const PRICING_RULES = 'MPBRTRESTAPI_PRICING_RULES';
    const PRICING_DEFAULT_CODE = 'MPBRTRESTAPI_PRICING_DEFAULT_CODE';

    const CONNECTOR_URL = 'MPBRTRESTAPI_CONNECTOR_URL';
    const CONNECTOR_TOKEN = 'MPBRTRESTAPI_CONNECTOR_TOKEN';

    /**
     * Get all config values as associative array.
     */
    public static function getAll(): array
    {
        $keys = [
            self::ACCOUNT_USER_ID,
            self::ACCOUNT_PASSWORD,
            self::ACCOUNT_DEPARTURE_DEPOT,
            self::ACCOUNT_CUSTOMER_CODE,
            self::SANDBOX_USER_ID,
            self::SANDBOX_PASSWORD,
            self::SANDBOX_DEPARTURE_DEPOT,
            self::SANDBOX_CUSTOMER_CODE,
            self::SANDBOX_ENABLED,
            self::NETWORK,
            self::FREIGHT_TYPE,
            self::SERVICE_TYPE,
            self::NATURA_MERCI,
            self::COD_PAYMENT_MODULES,
            self::ORDERSTATES_DISPLAY,
            self::ORDERSTATE_CHANGE,
            self::ORDERSTATES_START_DATE,
            self::ORDERSTATES_END_DATE,
            self::LABEL_OUTPUT_TYPE,
            self::LABEL_OFFSET_X,
            self::LABEL_OFFSET_Y,
            self::LABEL_BORDER,
            self::LABEL_LOGO,
            self::LABEL_BARCODE,
            self::LABEL_FORMAT,
            self::CONNECTOR_URL,
            self::CONNECTOR_TOKEN,
            self::PRICING_RULES,
            self::PRICING_DEFAULT_CODE,
        ];

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = \Configuration::get($key);
        }

        return $result;
    }

    /**
     * Save all config values from POST data.
     *
     * @param array $data
     * @return bool
     */
    public static function saveFromPost(array $data): bool
    {
        $ok = true;

        $stringKeys = [
            self::ACCOUNT_USER_ID,
            self::ACCOUNT_PASSWORD,
            self::ACCOUNT_DEPARTURE_DEPOT,
            self::ACCOUNT_CUSTOMER_CODE,
            self::SANDBOX_USER_ID,
            self::SANDBOX_PASSWORD,
            self::SANDBOX_DEPARTURE_DEPOT,
            self::SANDBOX_CUSTOMER_CODE,
            self::NETWORK,
            self::FREIGHT_TYPE,
            self::SERVICE_TYPE,
            self::NATURA_MERCI,
            self::LABEL_OUTPUT_TYPE,
            self::LABEL_OFFSET_X,
            self::LABEL_OFFSET_Y,
            self::LABEL_FORMAT,
            self::CONNECTOR_URL,
            self::CONNECTOR_TOKEN,
            self::PRICING_DEFAULT_CODE,
        ];

        foreach ($stringKeys as $key) {
            if (isset($data[$key])) {
                $ok = $ok && \Configuration::updateValue($key, pSQL($data[$key]));
            }
        }

        if (isset($data[self::PRICING_RULES])) {
            $val = $data[self::PRICING_RULES];
            if (is_array($val)) {
                $val = json_encode($val);
            }
            $ok = $ok && \Configuration::updateValue(self::PRICING_RULES, $val);
        }

        $boolKeys = [self::SANDBOX_ENABLED, self::LABEL_BORDER, self::LABEL_LOGO, self::LABEL_BARCODE];
        foreach ($boolKeys as $key) {
            $ok = $ok && \Configuration::updateValue($key, isset($data[$key]) ? 1 : 0);
        }

        $jsonIntKeys = [
            self::ORDERSTATES_DISPLAY,
            self::ORDERSTATES_START_DATE,
            self::ORDERSTATES_END_DATE,
        ];
        foreach ($jsonIntKeys as $key) {
            if (isset($data[$key])) {
                $val = is_array($data[$key]) ? json_encode(array_map('intval', $data[$key])) : pSQL($data[$key]);
                $ok = $ok && \Configuration::updateValue($key, $val);
            }
        }

        $jsonStringKeys = [self::COD_PAYMENT_MODULES];
        foreach ($jsonStringKeys as $key) {
            if (isset($data[$key])) {
                $val = is_array($data[$key]) ? json_encode(array_map('strval', $data[$key])) : pSQL($data[$key]);
                $ok = $ok && \Configuration::updateValue($key, $val);
            }
        }

        if (isset($data[self::ORDERSTATE_CHANGE])) {
            $ok = $ok && \Configuration::updateValue(self::ORDERSTATE_CHANGE, (int) $data[self::ORDERSTATE_CHANGE]);
        }

        return $ok;
    }

    /**
     * Network options.
     */
    public static function getNetworkOptions(): array
    {
        return [
            '' => 'Standard',
            'D' => 'DPD',
            'E' => 'EuroExpress',
            'S' => 'FED',
        ];
    }

    /**
     * Delivery freight type options (porto).
     */
    public static function getFreightTypeOptions(): array
    {
        return [
            'DAP' => 'Franco',
            'EXW' => 'Assegnato',
        ];
    }

    /**
     * Service type options.
     */
    public static function getServiceTypeOptions(): array
    {
        return [
            '' => 'Standard',
            'E' => 'Express',
            'H' => '10:30',
        ];
    }

    /**
     * Label output type options.
     */
    public static function getLabelOutputTypeOptions(): array
    {
        return [
            'PDF' => 'PDF',
            'ZPL' => 'ZPL',
            'PNG' => 'PNG',
        ];
    }

    /**
     * Label format options.
     */
    public static function getLabelFormatOptions(): array
    {
        return [
            '100x150' => '100x150 mm',
            'A4' => 'Foglio A4',
        ];
    }
}
