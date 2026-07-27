<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

class ModelBrtRestApiBordero extends \ObjectModel
{
    public $id_order;
    public $numeric_sender_reference;
    public $alphanumeric_sender_reference;
    public $parcel_number_from;
    public $parcel_number_to;
    public $number_of_parcels;
    public $weight_kg;
    public $cash_on_delivery;
    public $consignee_company_name;
    public $consignee_city;
    public $is_printed;
    public $date_printed;
    public $id_bordero_batch;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'brt_restapi_bordero',
        'primary' => 'id_brt_restapi_bordero',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false],
            'numeric_sender_reference' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => false],
            'alphanumeric_sender_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 15],
            'parcel_number_from' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 7],
            'parcel_number_to' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 7],
            'number_of_parcels' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'weight_kg' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'cash_on_delivery' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'consignee_company_name' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 70],
            'consignee_city' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 35],
            'is_printed' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_printed' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => false],
            'id_bordero_batch' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
    ];

    public static function install(): bool
    {
        $db = \Db::getInstance();
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'brt_restapi_bordero` (
            `id_brt_restapi_bordero`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order`                      INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `numeric_sender_reference`      BIGINT(20) NOT NULL DEFAULT 0,
            `alphanumeric_sender_reference` VARCHAR(15) NOT NULL DEFAULT \'\',
            `parcel_number_from`            VARCHAR(7) NOT NULL DEFAULT \'\',
            `parcel_number_to`              VARCHAR(7) NOT NULL DEFAULT \'\',
            `number_of_parcels`             INT(11) NOT NULL DEFAULT 1,
            `weight_kg`                     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `cash_on_delivery`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `consignee_company_name`        VARCHAR(70) NOT NULL DEFAULT \'\',
            `consignee_city`                VARCHAR(35) NOT NULL DEFAULT \'\',
            `is_printed`                    TINYINT(1) NOT NULL DEFAULT 0,
            `date_printed`                  DATETIME DEFAULT NULL,
            `id_bordero_batch`              INT(11) NOT NULL DEFAULT 0,
            `date_add`                      DATETIME NOT NULL,
            `date_upd`                      DATETIME NOT NULL,
            PRIMARY KEY (`id_brt_restapi_bordero`),
            KEY `idx_id_order` (`id_order`),
            KEY `idx_numeric_sender_reference` (`numeric_sender_reference`),
            KEY `idx_is_printed` (`is_printed`),
            KEY `idx_id_bordero_batch` (`id_bordero_batch`),
            KEY `idx_date_add` (`date_add`)
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
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'brt_restapi_bordero`'
        );
    }

    public static function getByNumericSenderReference(int $numericSenderReference)
    {
        self::ensureTableExists();

        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_bordero` FROM `' . _DB_PREFIX_ . 'brt_restapi_bordero`
             WHERE `numeric_sender_reference` = ' . (int) $numericSenderReference . '
             ORDER BY `date_add` DESC'
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    public static function getUnprinted(int $limit = 100): array
    {
        self::ensureTableExists();

        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_bordero`
             WHERE `is_printed` = 0
             ORDER BY `date_add` ASC
             LIMIT ' . (int) $limit
        ) ?: [];
    }

    public static function getByBatchId(int $batchId): array
    {
        self::ensureTableExists();

        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_bordero`
             WHERE `id_bordero_batch` = ' . (int) $batchId . '
             ORDER BY `date_add` ASC'
        ) ?: [];
    }

    public static function markAsPrinted(array $ids, int $batchId = 0): bool
    {
        if (empty($ids)) {
            return true;
        }

        $sanitizedIds = array_map('intval', $ids);
        $batchId = $batchId ?: time();
        $now = date('Y-m-d H:i:s');

        return \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'brt_restapi_bordero`
             SET `is_printed` = 1,
                 `date_printed` = \'' . pSQL($now) . '\',
                 `id_bordero_batch` = ' . (int) $batchId . ',
                 `date_upd` = \'' . pSQL($now) . '\'
             WHERE `id_brt_restapi_bordero` IN (' . implode(',', $sanitizedIds) . ')'
        );
    }

    public static function registerShipment(array $data): bool
    {
        $ref = (int) ($data['numeric_sender_reference'] ?? 0);
        $model = self::getByNumericSenderReference($ref);
        if (!$model) {
            $model = new self();
        }

        $model->id_order = (int) ($data['id_order'] ?? 0);
        $model->numeric_sender_reference = $ref;
        $model->alphanumeric_sender_reference = (string) ($data['alphanumeric_sender_reference'] ?? '');
        $model->parcel_number_from = (string) ($data['parcel_number_from'] ?? '');
        $model->parcel_number_to = (string) ($data['parcel_number_to'] ?? '');
        $model->number_of_parcels = (int) ($data['number_of_parcels'] ?? 1);
        $model->weight_kg = (float) ($data['weight_kg'] ?? 0);
        $model->cash_on_delivery = (float) ($data['cash_on_delivery'] ?? 0);
        $model->consignee_company_name = (string) ($data['consignee_company_name'] ?? '');
        $model->consignee_city = (string) ($data['consignee_city'] ?? '');
        $model->is_printed = 0;
        $model->date_add = date('Y-m-d H:i:s');
        $model->date_upd = date('Y-m-d H:i:s');

        return (bool) $model->save();
    }
}
