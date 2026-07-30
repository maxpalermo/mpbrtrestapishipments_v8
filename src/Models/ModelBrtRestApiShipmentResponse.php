<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

class ModelBrtRestApiShipmentResponse extends \ObjectModel
{
    public $id_order;
    public $numeric_sender_reference;
    public $alphanumeric_sender_reference;
    public $parcel_number_from;
    public $parcel_number_to;
    public $arrival_depot;
    public $arrival_terminal;
    public $delivery_zone;
    public $sandbox;
    public $execution_code;
    public $execution_severity;
    public $execution_message;
    public $response_json;
    public $labels_json;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'brt_restapi_shipment_response',
        'primary' => 'id_brt_restapi_shipment_response',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false],
            'numeric_sender_reference' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'alphanumeric_sender_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 15],
            'parcel_number_from' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 7],
            'parcel_number_to' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 7],
            'arrival_depot' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 3],
            'arrival_terminal' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 3],
            'delivery_zone' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 2],
            'sandbox' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'execution_code' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'execution_severity' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 10],
            'execution_message' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 512],
            'response_json' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'labels_json' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
    ];

    public static function install(): bool
    {
        $db = \Db::getInstance();
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'brt_restapi_shipment_response` (
            `id_brt_restapi_shipment_response` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order`                         INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `numeric_sender_reference`         BIGINT(20) NOT NULL DEFAULT 0,
            `alphanumeric_sender_reference`    VARCHAR(15) NOT NULL DEFAULT \'\',
            `parcel_number_from`               VARCHAR(7) NOT NULL DEFAULT \'\',
            `parcel_number_to`                 VARCHAR(7) NOT NULL DEFAULT \'\',
            `arrival_depot`                    VARCHAR(3) NOT NULL DEFAULT \'\',
            `arrival_terminal`                 VARCHAR(3) NOT NULL DEFAULT \'\',
            `delivery_zone`                    VARCHAR(2) NOT NULL DEFAULT \'\',
            `sandbox`                          TINYINT(1) NOT NULL DEFAULT 0,
            `execution_code`                   INT(11) NOT NULL DEFAULT 0,
            `execution_severity`               VARCHAR(10) NOT NULL DEFAULT \'\',
            `execution_message`                VARCHAR(512) NOT NULL DEFAULT \'\',
            `response_json`                    JSON NOT NULL,
            `labels_json`                      JSON NOT NULL,
            `date_add`                         DATETIME NOT NULL,
            `date_upd`                         DATETIME NOT NULL,
            PRIMARY KEY (`id_brt_restapi_shipment_response`),
            KEY `idx_id_order` (`id_order`),
            KEY `idx_numeric_sender_reference` (`numeric_sender_reference`),
            KEY `idx_alphanumeric_sender_reference` (`alphanumeric_sender_reference`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        return (bool) $db->execute($sql);
    }

    public static function ensureTableExists(): void
    {
        static $checked = false;
        if (!$checked) {
            self::install();
            $checked = true;
        }
    }

    public static function uninstall(): bool
    {
        return (bool) \Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`'
        );
    }

    /**
     * @param int $idOrder
     * @return self|false
     */
    public static function getByIdOrder(int $idOrder)
    {
        self::ensureTableExists();
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_shipment_response` FROM `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`
             WHERE `id_order` = ' . (int) $idOrder . '
             ORDER BY `date_add` DESC'
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    /**
     * @param int $idOrder
     * @return self|false
     */
    public static function getByOrderOrReference(int $idOrder)
    {
        self::ensureTableExists();
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_shipment_response` FROM `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`
             WHERE `id_order` = ' . (int) $idOrder . ' OR `numeric_sender_reference` = ' . (int) $idOrder . '
             ORDER BY `date_add` DESC'
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    /**
     * @param int $numericSenderReference
     * @return self|false
     */
    public static function getByNumericSenderReference(int $numericSenderReference)
    {
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_shipment_response` FROM `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`
             WHERE `numeric_sender_reference` = ' . (int) $numericSenderReference . '
             ORDER BY `date_add` DESC'
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    /**
     * @param string $alphanumericSenderReference
     * @return self|false
     */
    public static function getByAlphanumericSenderReference(string $alphanumericSenderReference)
    {
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_shipment_response` FROM `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`
             WHERE `alphanumeric_sender_reference` = \'' . pSQL($alphanumericSenderReference) . '\'
             ORDER BY `date_add` DESC'
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        self::ensureTableExists();

        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_shipment_response`
             ORDER BY `date_add` DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        ) ?: [];
    }

    public function getResponseDataArray(): array
    {
        return json_decode($this->response_json, true) ?: [];
    }

    public function getLabelsArray(): array
    {
        return json_decode($this->labels_json, true) ?: [];
    }

    public function setResponseData(array $data): void
    {
        $this->response_json = json_encode($data);
    }

    public function setLabelsData(array $data): void
    {
        $this->labels_json = json_encode($data);
    }

    /**
     * Returns list of parcel IDs (from labels_json).
     */
    public function getParcelIds(): array
    {
        $labels = $this->getLabelsArray();
        $labelList = $labels['label'] ?? [];
        $ids = [];
        foreach ($labelList as $label) {
            if (!empty($label['parcelID'])) {
                $ids[] = $label['parcelID'];
            }
        }

        return $ids;
    }
}
