-- Tabella richieste spedizione BRT (shipment request)
CREATE TABLE IF NOT EXISTS `PREFIX_brt_restapi_shipment_request` (
    `id_brt_restapi_shipment_request` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`                        INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `numeric_sender_reference`        BIGINT(20) NOT NULL DEFAULT 0,
    `alphanumeric_sender_reference`   VARCHAR(15) NOT NULL DEFAULT '',
    `sandbox`                         TINYINT(1) NOT NULL DEFAULT 0,
    `request_json`                    JSON NOT NULL,
    `date_add`                        DATETIME NOT NULL,
    `date_upd`                        DATETIME NOT NULL,
    PRIMARY KEY (`id_brt_restapi_shipment_request`),
    KEY `idx_id_order` (`id_order`),
    KEY `idx_numeric_sender_reference` (`numeric_sender_reference`),
    KEY `idx_alphanumeric_sender_reference` (`alphanumeric_sender_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella risposte spedizione BRT (shipment response)
CREATE TABLE IF NOT EXISTS `PREFIX_brt_restapi_shipment_response` (
    `id_brt_restapi_shipment_response` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`                         INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `numeric_sender_reference`         BIGINT(20) NOT NULL DEFAULT 0,
    `alphanumeric_sender_reference`    VARCHAR(15) NOT NULL DEFAULT '',
    `parcel_number_from`               VARCHAR(7) NOT NULL DEFAULT '',
    `parcel_number_to`                 VARCHAR(7) NOT NULL DEFAULT '',
    `arrival_depot`                    VARCHAR(3) NOT NULL DEFAULT '',
    `arrival_terminal`                 VARCHAR(3) NOT NULL DEFAULT '',
    `delivery_zone`                    VARCHAR(2) NOT NULL DEFAULT '',
    `sandbox`                          TINYINT(1) NOT NULL DEFAULT 0,
    `execution_code`                   INT(11) NOT NULL DEFAULT 0,
    `execution_severity`               VARCHAR(10) NOT NULL DEFAULT '',
    `execution_message`                VARCHAR(128) NOT NULL DEFAULT '',
    `response_json`                    JSON NOT NULL,
    `labels_json`                      JSON NOT NULL,
    `date_add`                         DATETIME NOT NULL,
    `date_upd`                         DATETIME NOT NULL,
    PRIMARY KEY (`id_brt_restapi_shipment_response`),
    KEY `idx_id_order` (`id_order`),
    KEY `idx_numeric_sender_reference` (`numeric_sender_reference`),
    KEY `idx_alphanumeric_sender_reference` (`alphanumeric_sender_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella tracking BRT
CREATE TABLE IF NOT EXISTS `PREFIX_brt_restapi_tracking` (
    `id_brt_restapi_tracking`       INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`                      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `parcel_id`                     VARCHAR(35) NOT NULL DEFAULT '',
    `numeric_sender_reference`      BIGINT(20) NOT NULL DEFAULT 0,
    `alphanumeric_sender_reference` VARCHAR(15) NOT NULL DEFAULT '',
    `esito`                         INT(11) NOT NULL DEFAULT 0,
    `stato_spedizione`              VARCHAR(35) NOT NULL DEFAULT '',
    `data_consegna`                 DATE DEFAULT NULL,
    `firmatario`                    VARCHAR(35) NOT NULL DEFAULT '',
    `response_json`                 JSON NOT NULL,
    `date_add`                      DATETIME NOT NULL,
    `date_upd`                      DATETIME NOT NULL,
    PRIMARY KEY (`id_brt_restapi_tracking`),
    KEY `idx_id_order` (`id_order`),
    KEY `idx_parcel_id` (`parcel_id`),
    KEY `idx_numeric_sender_reference` (`numeric_sender_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella borderò spedizioni BRT
CREATE TABLE IF NOT EXISTS `PREFIX_brt_restapi_bordero` (
    `id_brt_restapi_bordero`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`                      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `numeric_sender_reference`      BIGINT(20) NOT NULL DEFAULT 0,
    `alphanumeric_sender_reference` VARCHAR(15) NOT NULL DEFAULT '',
    `parcel_number_from`            VARCHAR(7) NOT NULL DEFAULT '',
    `parcel_number_to`              VARCHAR(7) NOT NULL DEFAULT '',
    `number_of_parcels`             INT(11) NOT NULL DEFAULT 1,
    `weight_kg`                     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cash_on_delivery`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `consignee_company_name`        VARCHAR(70) NOT NULL DEFAULT '',
    `consignee_city`                VARCHAR(35) NOT NULL DEFAULT '',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella misure e peso pacchi BRT
CREATE TABLE IF NOT EXISTS `PREFIX_brt_restapi_weight` (
    `id_weight`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`         INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `reference_number` VARCHAR(64) NOT NULL DEFAULT '',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


