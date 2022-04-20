/**

mysql --user hgv_dev -p < /var/www/aquila_dev/script/loadProdIntoDev.sql

**/

TRUNCATE TABLE `hgv_dev`.`pictureLink`;
TRUNCATE TABLE `hgv_dev`.`mentionedDate`;
TRUNCATE TABLE `hgv_dev`.`hgv` ;

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

INSERT INTO `hgv_dev`.`hgv`
SELECT * FROM `hgv`.`hgv`;

INSERT INTO `hgv_dev`.`mentionedDate`
SELECT * FROM `hgv`.`mentionedDate`;

INSERT INTO `hgv_dev`.`pictureLink`
SELECT * FROM `hgv`.`pictureLink`;

