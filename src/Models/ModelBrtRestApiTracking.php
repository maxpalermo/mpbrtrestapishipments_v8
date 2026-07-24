<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

class ModelBrtRestApiTracking extends \ObjectModel
{
    public $id_order;
    public $parcel_id;
    public $numeric_sender_reference;
    public $alphanumeric_sender_reference;
    public $esito;
    public $stato_spedizione;
    public $data_consegna;
    public $firmatario;
    public $response_json;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'brt_restapi_tracking',
        'primary' => 'id_brt_restapi_tracking',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false],
            'parcel_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 35],
            'numeric_sender_reference' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'alphanumeric_sender_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 15],
            'esito' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'stato_spedizione' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 35],
            'data_consegna' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => false],
            'firmatario' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 35],
            'response_json' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
    ];

    public static function install(): bool
    {
        $db = \Db::getInstance();
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'brt_restapi_tracking` (
            `id_brt_restapi_tracking`       INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order`                      INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `parcel_id`                     VARCHAR(35) NOT NULL DEFAULT \'\',
            `numeric_sender_reference`      BIGINT(20) NOT NULL DEFAULT 0,
            `alphanumeric_sender_reference` VARCHAR(15) NOT NULL DEFAULT \'\',
            `esito`                         INT(11) NOT NULL DEFAULT 0,
            `stato_spedizione`              VARCHAR(35) NOT NULL DEFAULT \'\',
            `data_consegna`                 DATE DEFAULT NULL,
            `firmatario`                    VARCHAR(35) NOT NULL DEFAULT \'\',
            `response_json`                 JSON NOT NULL,
            `date_add`                      DATETIME NOT NULL,
            `date_upd`                      DATETIME NOT NULL,
            PRIMARY KEY (`id_brt_restapi_tracking`),
            KEY `idx_id_order` (`id_order`),
            KEY `idx_parcel_id` (`parcel_id`),
            KEY `idx_numeric_sender_reference` (`numeric_sender_reference`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        return (bool) $db->execute($sql);
    }

    public static function uninstall(): bool
    {
        return (bool) \Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'brt_restapi_tracking`'
        );
    }

    /**
     * @param int $idOrder
     * @return self[]
     */
    public static function getByIdOrder(int $idOrder): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_brt_restapi_tracking` FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
             WHERE `id_order` = ' . (int) $idOrder . '
             ORDER BY `date_add` DESC'
        );

        if (!$rows) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $model = new self((int) $row['id_brt_restapi_tracking']);
            if (\Validate::isLoadedObject($model)) {
                $result[] = $model;
            }
        }

        return $result;
    }

    /**
     * @param string $parcelId
     * @return self|false
     */
    public static function getByParcelId(string $parcelId)
    {
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_brt_restapi_tracking` FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
             WHERE `parcel_id` = \'' . pSQL($parcelId) . '\'
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
            'SELECT `id_brt_restapi_tracking` FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
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
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
             ORDER BY `date_add` DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        ) ?: [];
    }

    public function getResponseDataArray(): array
    {
        return json_decode($this->response_json, true) ?: [];
    }

    public function setResponseData(array $data): void
    {
        $this->response_json = json_encode($data);
    }

    /**
     * Returns the list of events from response_json.
     */
    public function getEventi(): array
    {
        $data = $this->getResponseDataArray();
        $response = $data['ttParcelIdResponse'] ?? [];

        return $response['lista_eventi'] ?? [];
    }

    /**
     * Returns the shipment data from response_json.
     */
    public function getBolla(): array
    {
        $data = $this->getResponseDataArray();
        $response = $data['ttParcelIdResponse'] ?? [];

        return $response['bolla'] ?? [];
    }
}
