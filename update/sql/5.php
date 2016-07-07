<?php
    $query = "DROP TABLE IF EXISTS shop_integration_parameter";
    se_db_query($query);

    $query = "DROP TABLE IF EXISTS shop_integration";
    se_db_query($query);

    $query = "CREATE TABLE shop_integration_parameter (
                      id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      id_main int(10) UNSIGNED DEFAULT NULL COMMENT 'Ид. магазина',
                      code varchar(255) DEFAULT NULL COMMENT 'Код параметра',
                      value varchar(255) DEFAULT NULL COMMENT 'Значение параметра',
                      updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                      created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (id),
                      CONSTRAINT FK_shop_integration_parameter_main_id FOREIGN KEY (id_main)
                      REFERENCES main (id) ON DELETE CASCADE ON UPDATE RESTRICT
                    )
                    ENGINE = INNODB
                    CHARACTER SET utf8
                    COLLATE utf8_general_ci
                    COMMENT = 'Параметры интеграций с Яндекс.Маркет и другими сервисам';";
    se_db_query($query);
