<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

class ModelBrtRestApiWeight extends \ObjectModel
{
    public $id_weight;
    public $id_order;
    public $reference_number;
    public $progressivo;
    public $weight;
    public $volume;
    public $x;
    public $y;
    public $z;
    public $id_read;
    public $is_read;
    public $is_envelope;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'brt_restapi_weight',
        'primary' => 'id_weight',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false],
            'reference_number' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 64],
            'progressivo' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'weight' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'volume' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'x' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'y' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'z' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'id_read' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'is_read' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'is_envelope' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
    ];

    public static function install(): bool
    {
        $db = \Db::getInstance();
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'brt_restapi_weight` (
            `id_weight`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order`         INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `reference_number` VARCHAR(64) NOT NULL DEFAULT \'\',
            `progressivo`      INT(11) UNSIGNED NOT NULL DEFAULT 1,
            `weight`           FLOAT NOT NULL DEFAULT 0,
            `volume`           FLOAT NOT NULL DEFAULT 0,
            `x`                FLOAT NOT NULL DEFAULT 0,
            `y`                FLOAT NOT NULL DEFAULT 0,
            `z`                FLOAT NOT NULL DEFAULT 0,
            `id_read`          INT(11) NOT NULL DEFAULT 0,
            `is_read`          TINYINT(1) NOT NULL DEFAULT 0,
            `is_envelope`      TINYINT(1) NOT NULL DEFAULT 0,
            `date_add`         DATETIME NOT NULL,
            `date_upd`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_weight`),
            UNIQUE KEY `unique_ref_prog` (`reference_number`, `progressivo`),
            KEY `idx_id_order` (`id_order`),
            KEY `idx_reference_number` (`reference_number`)
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
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'brt_restapi_weight`'
        );
    }

    public static function getByReferenceAndProgressivo(string $referenceNumber, int $progressivo)
    {
        self::ensureTableExists();

        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_weight` FROM `' . _DB_PREFIX_ . 'brt_restapi_weight`
             WHERE `reference_number` = \'' . pSQL($referenceNumber) . '\'
               AND `progressivo` = ' . (int) $progressivo
        );

        if (!$id) {
            return false;
        }

        $model = new self((int) $id);

        return \Validate::isLoadedObject($model) ? $model : false;
    }

    public static function getParcelsByReference(string $referenceNumber): array
    {
        self::ensureTableExists();

        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_weight`
             WHERE `reference_number` = \'' . pSQL($referenceNumber) . '\'
             ORDER BY `progressivo` ASC'
        ) ?: [];
    }

    public static function getParcelsByOrderId(int $idOrder): array
    {
        self::ensureTableExists();

        if (!$idOrder) {
            return [];
        }

        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'brt_restapi_weight`
             WHERE `id_order` = ' . (int) $idOrder . '
             ORDER BY `progressivo` ASC'
        ) ?: [];
    }

    public static function saveParcel(
        string $referenceNumber,
        int $progressivo,
        float $weight,
        float $x = 0.0,
        float $y = 0.0,
        float $z = 0.0,
        float $volume = 0.0,
        int $idOrder = 0,
        int $isEnvelope = 0,
        int $idRead = 0,
        int $isRead = 0
    ): array {
        self::ensureTableExists();

        $referenceNumber = trim($referenceNumber);
        $progressivo = max(1, (int) $progressivo);

        if ($volume <= 0 && $x > 0 && $y > 0 && $z > 0) {
            $volume = round(($x * $y * $z) / 1000000.0, 6);
        }

        $model = self::getByReferenceAndProgressivo($referenceNumber, $progressivo);
        if (!$model) {
            $model = new self();
            $model->date_add = date('Y-m-d H:i:s');
        }

        $model->reference_number = $referenceNumber;
        $model->progressivo = $progressivo;
        $model->weight = (float) $weight;
        $model->x = (float) $x;
        $model->y = (float) $y;
        $model->z = (float) $z;
        $model->volume = (float) $volume;
        $model->id_order = (int) $idOrder;
        $model->is_envelope = (int) $isEnvelope ? 1 : 0;
        $model->id_read = (int) $idRead;
        $model->is_read = (int) $isRead ? 1 : 0;
        $model->date_upd = date('Y-m-d H:i:s');

        $saved = $model->save();

        if ($saved) {
            return [
                'success' => true,
                'message' => 'Pacco salvato con successo',
                'data' => [
                    'id_weight' => (int) $model->id,
                    'id_order' => (int) $model->id_order,
                    'reference_number' => (string) $model->reference_number,
                    'progressivo' => (int) $model->progressivo,
                    'barcode' => $model->reference_number . '-' . $model->progressivo,
                    'weight' => (float) $model->weight,
                    'x' => (float) $model->x,
                    'y' => (float) $model->y,
                    'z' => (float) $model->z,
                    'volume' => (float) $model->volume,
                    'id_read' => (int) $model->id_read,
                    'is_read' => (int) $model->is_read,
                    'is_envelope' => (int) $model->is_envelope,
                    'date_add' => $model->date_add,
                    'date_upd' => $model->date_upd,
                ],
            ];
        }

        return [
            'success' => false,
            'error' => 'Impossibile salvare i dati del pacco',
        ];
    }

    public static function deleteParcel(int $idWeight): bool
    {
        self::ensureTableExists();

        $model = new self((int) $idWeight);
        if (\Validate::isLoadedObject($model)) {
            return (bool) $model->delete();
        }

        return false;
    }

    public static function calculateTotalsByReference(string $referenceNumber): array
    {
        self::ensureTableExists();

        $parcels = self::getParcelsByReference($referenceNumber);
        $totalParcels = count($parcels);
        $totalWeight = 0.0;
        $totalVolume = 0.0;

        foreach ($parcels as $p) {
            $totalWeight += (float) $p['weight'];
            $totalVolume += (float) $p['volume'];
        }

        return [
            'numberOfParcels' => max(1, $totalParcels),
            'weightKG' => round(max(0.1, $totalWeight), 2),
            'volumeM3' => $totalVolume > 0 ? round($totalVolume, 4) : 0.001,
            'parcels' => $parcels,
        ];
    }

    public static function calculateTotalsByOrderId(int $idOrder): array
    {
        self::ensureTableExists();

        $parcels = self::getParcelsByOrderId($idOrder);
        $totalParcels = count($parcels);
        $totalWeight = 0.0;
        $totalVolume = 0.0;

        foreach ($parcels as $p) {
            $totalWeight += (float) $p['weight'];
            $totalVolume += (float) $p['volume'];
        }

        return [
            'numberOfParcels' => max(1, $totalParcels),
            'weightKG' => round(max(0.1, $totalWeight), 2),
            'volumeM3' => $totalVolume > 0 ? round($totalVolume, 4) : 0.001,
            'parcels' => $parcels,
        ];
    }
}
